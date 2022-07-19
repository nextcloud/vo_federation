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

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use OCA\VO_Federation\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

class LoginController extends Controller {
	private const STATE = 'oidc.state';
	private const NONCE = 'oidc.nonce';
	public const PROVIDERID = 'oidc.providerid';
	private const REDIRECT_AFTER_LOGIN = 'oidc.redirect';

	/** @var ISecureRandom */
	private $random;

	/** @var ISession */
	private $session;

	/** @var IClientService */
	private $clientService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IUserSession */
	private $userSession;

	/** @var IUserManager */
	private $userManager;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var ILogger */
	private $logger;

	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(
		IRequest $request,
		ISecureRandom $random,
		ISession $session,
		IClientService $clientService,
		IURLGenerator $urlGenerator,
		IUserSession $userSession,
		IUserManager $userManager,
		ITimeFactory $timeFactory,
		IConfig $config,
		ILogger $logger,
		?string $userId
	) {
		parent::__construct(Application::APP_ID, $request);

		$this->random = $random;
		$this->session = $session;
		$this->clientService = $clientService;
		$this->urlGenerator = $urlGenerator;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 */
	public function login(string $providerId) {
		$this->logger->debug('Initiating login for provider with id: ' . $providerId);

		$state = $this->random->generate(32, ISecureRandom::CHAR_DIGITS . ISecureRandom::CHAR_UPPER);
		$this->session->set(self::STATE, $state);

		$nonce = $this->random->generate(32, ISecureRandom::CHAR_DIGITS . ISecureRandom::CHAR_UPPER);
		$this->session->set(self::NONCE, $nonce);

		$this->session->set(self::PROVIDERID, $providerId);
		$this->session->close();

		// get attribute mapping settings
		$uidAttribute = 'sub';
		$emailAttribute = 'email';
		$displaynameAttribute = 'name';
		$quotaAttribute = 'quota';

		$authEndpoint = 'https://keycloak.home.arpa/auth/realms/Example/protocol/openid-connect/auth';
		$clientId = 'nextcloud';
		$scope = 'openid email profile';
		$clientSecret = 'ef1baa4f-97dc-46c3-82ec-861cf4a2f8fb';

		$claims = [
			// more details about requesting claims:
			// https://openid.net/specs/openid-connect-core-1_0.html#IndividualClaimsRequests
			'id_token' => [
				// ['essential' => true] means it's mandatory but it won't trigger an error if it's not there
				// null means we want it
				$emailAttribute => null,
				$displaynameAttribute => null,
				$quotaAttribute => null,
			],
			'userinfo' => [
				$emailAttribute => null,
				$displaynameAttribute => null,
				$quotaAttribute => null,
			],
		];

		if ($uidAttribute !== 'sub') {
			$claims['id_token'][$uidAttribute] = ['essential' => true];
			$claims['userinfo'][$uidAttribute] = ['essential' => true];
		}

		$extraClaimsString = '';
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
			'nonce' => $nonce,
		];

		$url = $authEndpoint . '?' . http_build_query($data);
		$this->logger->debug('Redirecting user to: ' . $url);

		// Workaround to avoid empty session on special conditions in Safari
		// https://github.com/nextcloud/user_oidc/pull/358
		if ($this->request->isUserAgent(['/Safari/']) && !$this->request->isUserAgent(['/Chrome/'])) {
			return new Http\DataDisplayResponse('<meta http-equiv="refresh" content="0; url=' . $url . '" />');
		}

		return new RedirectResponse($url);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @UseSession
	 */
	public function code($state = '', $code = '', $scope = '', $error = '', $error_description = '') {
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

		$tokenEndpoint = 'https://keycloak.home.arpa/auth/realms/Example/protocol/openid-connect/token';
		$clientId = 'nextcloud';
		$clientSecret = 'ef1baa4f-97dc-46c3-82ec-861cf4a2f8fb';

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

		$jwksUri = 'http://keycloak.home.arpa/auth/realms/Example/protocol/openid-connect/certs';

		$responseBody = $client->get($jwksUri)->getBody();
		$result = json_decode($responseBody, true);
		$jwks = JWK::parseKeySet($result);

		JWT::$leeway = 60;
		$idTokenPayload = JWT::decode($data['id_token'], $jwks, array_keys(JWT::$supported_algs));

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
		$uidAttribute = 'sub';
		$name = $idTokenPayload->{$uidAttribute} ?? null;
		if ($name === null) {
			return new JSONResponse(['Failed to load user']);
		}

        $this->config->setUserValue($this->userId, Application::APP_ID, 'accessToken', $data['access_token']);
        $this->config->setUserValue($this->userId, Application::APP_ID, 'refreshToken', $data['refresh_token']);
        $this->config->setUserValue($this->userId, Application::APP_ID, 'name', $idTokenPayload->preferred_username);

		return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
            '?aaiToken=success#vo_federation_prefs'
        );
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @UseSession
	 *
	 * @return Http\RedirectResponse
	 * @throws Error
	 */
	public function singleLogoutService() {
		$oidcSystemConfig = $this->config->getSystemValue('user_oidc', []);
		$targetUrl = $this->urlGenerator->getAbsoluteURL('/');
		if (!isset($oidcSystemConfig['single_logout']) || $oidcSystemConfig['single_logout']) {
			$providerId = (int)$this->session->get(self::PROVIDERID);
			$provider = $this->providerMapper->getProvider($providerId);
			$targetUrl = $this->discoveryService->obtainDiscovery($provider)['end_session_endpoint'] ?? $this->urlGenerator->getAbsoluteURL('/');
			if ($targetUrl) {
				$targetUrl .= '?post_logout_redirect_uri=' . $this->urlGenerator->getAbsoluteURL('/');
			}
		}
		$this->userSession->logout();
		// make sure we clear the session to avoid messing with Backend::isSessionActive
		$this->session->clear();
		return new RedirectResponse($targetUrl);
	}
}
