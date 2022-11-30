<?php

/** @noinspection AdditionOperationOnArraysInspection */

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\VO_Federation\Controller;

use OCA\VO_Federation\Vendor\Firebase\JWT\JWT;
use OCA\VO_Federation\Vendor\Firebase\JWT\JWK;
use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Db\SessionMapper;
use OCA\VO_Federation\Service\ProviderService;
use OCA\VO_Federation\Service\GroupsService;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

class LoginController extends Controller {
	private const STATE = 'vo_federation.oidc.state';
	private const NONCE = 'vo_federation.oidc.nonce';
	public const PROVIDERID = 'vo_federation.oidc.providerid';

	/** @var ISecureRandom */
	private $random;

	/** @var ISession */
	private $session;

	/** @var IClientService */
	private $clientService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var ProviderService */
	private $providerService;

	/** @var GroupsService */
	private $groupsService;

	/** @var SessionMapper */
	private $sessionMapper;

	/** @var ILogger */
	private $logger;

	/** @var IConfig */
	private $config;

	/** @var string|null */
	private $userId;

	public function __construct(
		IRequest $request,
		ISecureRandom $random,
		ISession $session,
		IClientService $clientService,
		IURLGenerator $urlGenerator,
		ITimeFactory $timeFactory,
		IConfig $config,
		ProviderService $providerService,
		GroupsService $groupsService,
		SessionMapper $sessionMapper,
		ILogger $logger,
		?string $userId
	) {
		parent::__construct(Application::APP_ID, $request);

		$this->random = $random;
		$this->session = $session;
		$this->clientService = $clientService;
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;
		$this->providerService = $providerService;
		$this->groupsService = $groupsService;
		$this->sessionMapper = $sessionMapper;
		$this->logger = $logger;
		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * @return bool
	 */
	private function isSecure(): bool {
		// no restriction in debug mode
		return $this->config->getSystemValueBool('debug', false) || $this->request->getServerProtocol() === 'https';
	}

	/**
	 * @return TemplateResponse
	 */
	private function generateProtocolErrorResponse(): TemplateResponse {
		$response = new TemplateResponse('', 'error', [
			'errors' => [
				['error' => 'You must access Nextcloud with HTTPS to use OpenID Connect.']
			]
		], TemplateResponse::RENDER_AS_ERROR);
		$response->setStatus(Http::STATUS_NOT_FOUND);
		return $response;
	}	

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param int $providerId
	 * @param string|null $redirectUrl
	 * @return DataDisplayResponse|RedirectResponse|TemplateResponse
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function login(int $providerId) {
		if (!$this->isSecure()) {
			return $this->generateProtocolErrorResponse();
		}		
		$this->logger->debug('Initiating login for provider with id: ' . $providerId);

		//TODO: handle exceptions
		$provider = $this->providerMapper->getProvider($providerId);

		$state = $this->random->generate(32, ISecureRandom::CHAR_DIGITS . ISecureRandom::CHAR_UPPER);
		$this->session->set(self::STATE, $state);

		$nonce = $this->random->generate(32, ISecureRandom::CHAR_DIGITS . ISecureRandom::CHAR_UPPER);
		$this->session->set(self::NONCE, $nonce);

		$this->session->set(self::PROVIDERID, $providerId);
		$this->session->close();
		
		// get attribute mapping settings
		$clientId = $provider->getClientId();
		$scope = $provider->getScope() ?? 'openid profile';
		$uidAttribute = $provider->getUidClaim() ?? 'sub';
		$displaynameAttribute = $provider->getDisplayNameClaim() ?? 'name';

		$groupsAttribute = $provider->getGroupsClaim() ?? 'groups';

		$providerSettings = $provider->getSettings() ?? [];
		$authorizationEndpoint = $providerSettings[ProviderService::SETTING_AUTHORIZATION_ENDPOINT];

		$claims = [
			// more details about requesting claims:
			// https://openid.net/specs/openid-connect-core-1_0.html#IndividualClaimsRequests
			'id_token' => [
				// ['essential' => true] means it's mandatory but it won't trigger an error if it's not there
				// null means we want it
				$displaynameAttribute => null,
				$groupsAttribute => null,
			],
			'userinfo' => [
				$displaynameAttribute => null,
				$groupsAttribute => null,
			],
		];

		if ($uidAttribute !== 'sub') {
			$claims['id_token'][$uidAttribute] = ['essential' => true];
			$claims['userinfo'][$uidAttribute] = ['essential' => true];
		}

		$extraClaimsString = $providerSettings[ProviderService::SETTING_EXTRA_CLAIMS];
		if ($extraClaimsString) {
			$extraClaims = explode(' ', $extraClaimsString);
			foreach ($extraClaims as $extraClaim) {
				$claims['id_token'][$extraClaim] = null;
				$claims['userinfo'][$extraClaim] = null;
			}
		}

		$data = [
			'client_id' => $clientId,
			'response_type' => 'code',
			'scope' => $scope,
			'redirect_uri' => $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.login.code'),
			'claims' => json_encode($claims),
			'state' => $state,
			'nonce' => $nonce
		];

		$authorizationUrl = $this->buildAuthorizationUrl($authorizationEndpoint, $data);

		$this->logger->debug('Redirecting user to: ' . $authorizationUrl);

		// Workaround to avoid empty session on special conditions in Safari
		// https://github.com/nextcloud/user_oidc/pull/358
		if ($this->request->isUserAgent(['/Safari/']) && !$this->request->isUserAgent(['/Chrome/'])) {
			return new Http\DataDisplayResponse('<meta http-equiv="refresh" content="0; url=' . $authorizationUrl . '" />');
		}

		return new RedirectResponse($authorizationUrl);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @param string $state
	 * @param string $code
	 * @param string $scope
	 * @param string $error
	 * @param string $error_description
	 * @return JSONResponse|RedirectResponse|TemplateResponse
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws SessionNotAvailableException
	 * @throws \JsonException
	 */
	public function code(string $state = '', string $code = '', string $scope = '', string $error = '', string $error_description = '') {
		if (!$this->isSecure()) {
			return $this->generateProtocolErrorResponse();
		}
		$this->logger->debug('Code login with core: ' . $code . ' and state: ' . $state);

		if ($error !== '') {
			return new JSONResponse([
				'error' => $error,
				'error_description' => $error_description,
			], Http::STATUS_FORBIDDEN);
		}

		if ($this->session->get(self::STATE) !== $state) {
			$this->logger->debug('state does not match');

			// TODO show page with forbidden
			return new JSONResponse([
				'got' => $state,
				'expected' => $this->session->get(self::STATE),
			], Http::STATUS_FORBIDDEN);
		}

		$providerId = (int)$this->session->get(self::PROVIDERID);
		$provider = $this->providerMapper->getProvider($providerId);

		$clientId = $provider->getClientId();
		$clientSecret = $provider->getClientSecret();

		$providerSettings = $provider->getSettings() ?? [];
		$tokenEndpoint = $providerSettings[ProviderService::SETTING_TOKEN_ENDPOINT];
		$jwksEndpoint = $providerSettings[ProviderService::SETTING_JWKS_ENDPOINT];

		$this->logger->debug('Obtainting data from: ' . $tokenEndpoint);

		$client = $this->clientService->newClient();
		$result = $client->post(
			$tokenEndpoint,
			[
				'body' => [
					'code' => $code,
					'client_id' => $clientId,
					'client_secret' => $clientSecret,
					'redirect_uri' => $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.login.code'),
					'grant_type' => 'authorization_code',
				],
			]
		);

		$data = json_decode($result->getBody(), true);
		$this->logger->debug('Received code response: ' . json_encode($data, JSON_THROW_ON_ERROR));

		// TODO: proper error handling
		$idTokenRaw = $data['id_token'];
		$accessToken = $data['access_token'];
		$refreshToken = $data['refresh_token'];

		$responseBody = $client->get($jwksEndpoint)->getBody();
		$result = json_decode($responseBody, true);
		$jwks = JWK::parseKeySet($result);

		// Missing kid workaround
		if (array_keys($jwks)[0] == 0 && count($jwks) == 1) {
			$jwks = reset($jwks);
		}

		JWT::$leeway = 60;
		$idTokenPayload = JWT::decode($idTokenRaw, $jwks, array_keys(JWT::$supported_algs));

		$this->logger->debug('Parsed the JWT payload: ' . json_encode($idTokenPayload, JSON_THROW_ON_ERROR));

		if ($idTokenPayload->exp < $this->timeFactory->getTime()) {
			$this->logger->debug('Token expired');
			// TODO: error properly
			return new JSONResponse(['token expired']);
		}

		// Verify audience
		if (!(($idTokenPayload->aud === $clientId || in_array($clientId, $idTokenPayload->aud, true)))) {
			$this->logger->debug('This token is not for us');
			// TODO: error properly
			return new JSONResponse(['audience does not match']);
		}

		if (isset($idTokenPayload->nonce) && $idTokenPayload->nonce !== $this->session->get(self::NONCE)) {
			$this->logger->debug('Nonce does not match');
			// TODO: error properly
			return new JSONResponse(['invalid nonce']);
		}

		// get user ID attribute
		$uidAttribute = $provider->getUidClaim() ?? 'sub';
		$userId = $idTokenPayload->{$uidAttribute} ?? null;
		if ($userId === null) {
			return new JSONResponse(['Failed to load user']);
		}

		$displaynameAttribute = $provider->getDisplaynameAttribute() ?? 'name';
		$displayname = $idTokenPayload->{$displaynameAttribute} ?? $userId;

		$this->sessionMapper->createOrUpdateSession($this->userId, $providerId, $idTokenRaw, $userId, $idTokenPayload->exp, $accessToken, 0, $refreshToken, 0, $displayname);
		$this->groupsService->syncUser($this->userId, $providerId);

		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?aaiToken=success#vo_federation-personal-settings'
		);
	}

	/**
	* @param string $authorizationEndpoint
	* @param array $extraGetParameters
	* @return string
	*/
   public function buildAuthorizationUrl(string $authorizationEndpoint, array $extraGetParameters = []): string {
	   $parsedUrl = parse_url($authorizationEndpoint);

	   $urlWithoutParams =
		   (isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '')
		   . ($parsedUrl['host'] ?? '')
		   . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '')
		   . ($parsedUrl['path'] ?? '');

	   $queryParams = $extraGetParameters;
	   if (isset($parsedUrl['query'])) {
		   parse_str($parsedUrl['query'], $parsedQueryParams);
		   $queryParams = array_merge($queryParams, $parsedQueryParams);
	   }

	   // sanitize everything before the query parameters
	   // and trust http_build_query to sanitize the query parameters
	   return htmlentities(filter_var($urlWithoutParams, FILTER_SANITIZE_URL), ENT_QUOTES)
		   . (empty($queryParams) ? '' : '?' . http_build_query($queryParams));
   }
}
