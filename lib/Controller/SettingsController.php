<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
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

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Db\Provider;
use OCA\VO_Federation\Db\ProviderMapper;
use OCA\VO_Federation\Event\VOFederationChangeEvent;
use OCA\VO_Federation\Service\GroupsService;
use OCA\VO_Federation\Service\ProviderService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

class SettingsController extends Controller {
	/** @var ProviderMapper */
	private $providerMapper;
	/** @var ProviderService */
	private $providerService;
	/** @var GroupsService */
	private $groupsService;
	private IEventDispatcher $eventDispatcher;
	/** @var string|null */
	private $userId;

	public function __construct(
		IRequest $request,
		ProviderMapper $providerMapper,
		ProviderService $providerService,
		GroupsService $groupsService,
		?string $userId,
		IEventDispatcher $eventDispatcher,
		) {
		parent::__construct(Application::APP_ID, $request);

		$this->providerMapper = $providerMapper;
		$this->providerService = $providerService;
		$this->groupsService = $groupsService;
		$this->eventDispatcher = $eventDispatcher;

		$this->userId = $userId;
	}

	public function createProvider(string $identifier, string $clientId, string $clientSecret, string $discoveryEndpoint = null,
								   array $settings = [], string $scope = "openid email profile",
								   string $uidClaim, string $displayNameClaim, string $groupsClaim, string $groupsRegex, array $trustedInstances = []): JSONResponse {
		if ($this->providerService->getProviderByIdentifier($identifier) !== null) {
			return new JSONResponse(['message' => 'Provider with the given identifier already exists'], Http::STATUS_CONFLICT);
		}

		$provider = new Provider();
		$provider->setIdentifier($identifier);
		$provider->setClientId($clientId);
		$provider->setClientSecret($clientSecret);
		$provider->setDiscoveryEndpoint($discoveryEndpoint);
		$provider->setScope($scope);
		$provider->setSettings($settings);

		$provider->setUidClaim($uidClaim);
		$provider->setDisplayNameClaim($displayNameClaim);
		$provider->setGroupsClaim($groupsClaim);
		$provider->setGroupsRegex($groupsRegex);

		$provider = $this->providerMapper->insert($provider);
		$this->providerService->createOrUpdateTrustedInstances($provider->getId(), $trustedInstances);

		// TODO: include trusted instances in reponse
		return new JSONResponse($provider);
	}

	public function updateProvider(int $providerId, string $identifier, string $clientId, string $clientSecret = null, string $discoveryEndpoint = null,
								   array $settings = [], string $scope = "openid email profile",
								   string $uidClaim, string $displayNameClaim, string $groupsClaim, string $groupsRegex, array $trustedInstances = []): JSONResponse {
		$provider = $this->providerMapper->getProvider($providerId);

		if ($this->providerService->getProviderByIdentifier($identifier) === null) {
			return new JSONResponse(['message' => 'Provider with the given identifier does not exist'], Http::STATUS_NOT_FOUND);
		}

		$provider->setIdentifier($identifier);
		$provider->setClientId($clientId);
		if ($clientSecret) {
			$provider->setClientSecret($clientSecret);
		}
		$provider->setDiscoveryEndpoint($discoveryEndpoint);
		$provider->setScope($scope);
		$provider->setSettings($settings);

		$provider->setUidClaim($uidClaim);
		$provider->setDisplayNameClaim($displayNameClaim);

		$oldGroupsClaim = $provider->getGroupsClaim();
		$oldGroupsRegex = $provider->getGroupsRegex();
		
		$provider->setGroupsClaim($groupsClaim);
		$provider->setGroupsRegex($groupsRegex);

		$provider = $this->providerMapper->update($provider);
		// TODO: Create Event to enable other components to react to changes in federation composition
		$changedTrustedInstances = $this->providerService->createOrUpdateTrustedInstances($providerId, $trustedInstances);
		if ($changedTrustedInstances) {
			$this->eventDispatcher->dispatchTyped(new VOFederationChangeEvent($providerId, $trustedInstances));
		}

		if ($groupsClaim !== $oldGroupsClaim || $oldGroupsRegex !== $oldGroupsRegex) {
			$this->groupsService->updateAllProviderGroups($provider);
		}

		// invalidate JWKS cache
		//$this->providerService->setSetting($providerId, ProviderService::SETTING_JWKS_CACHE, '');
		//$this->providerService->setSetting($providerId, ProviderService::SETTING_JWKS_CACHE_TIMESTAMP, '');

		// TODO: include trusted instances in reponse
		return new JSONResponse($provider);
	}

	public function deleteProvider(int $providerId): JSONResponse {
		try {
			$provider = $this->providerMapper->getProvider($providerId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->providerService->deleteProvider($provider->getId());

		return new JSONResponse([], Http::STATUS_OK);
	}

	public function getProviders(): JSONResponse {
		return new JSONResponse($this->providerService->getProvidersWithSettings());
	}

	/**
	 * @NoAdminRequired
	 */
	public function logoutProvider(int $providerId): JSONResponse {
		$this->providerService->deleteProviderSession($this->userId, $providerId);
		return new JSONResponse([], Http::STATUS_OK);
	}
}
