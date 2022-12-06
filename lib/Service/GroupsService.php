<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Service;

use OC\Group\Group;
use OCA\VO_Federation\Backend\GroupBackend;
use OCA\VO_Federation\Db\Session;
use OCA\VO_Federation\Db\SessionMapper;
use OCP\Http\Client\IClientService;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\ILogger;

use OCP\AppFramework\Utility\ITimeFactory;

class GroupsService {
	private $groupManager;
	private $userManager;
	private $voGroupBackend;

	/** @var ProviderService */
	private $providerService;

	/** @var SessionMapper */
	private $sessionMapper;

	/** @var ILogger */
	private $logger;

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IGroupManager $groupManager, IUserManager $userManager, GroupBackend $voGroupBackend, ProviderService $providerService, SessionMapper $sessionMapper, IClientService $clientService, ITimeFactory $timeFactory, ILogger $logger
	) {
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->voGroupBackend = $voGroupBackend;
		$this->providerService = $providerService;
		$this->sessionMapper = $sessionMapper;
		$this->clientService = $clientService;
		$this->timeFactory = $timeFactory;
		$this->logger = $logger;
	}

	// TODO: Throws Exception indicating session must be terminated
	private function refreshAccessToken($session) : Session {
		// TODO: Check for expiration
		$provider = $this->providerService->getProvider($session->getProviderId());
		$providerSettings = $provider->getSettings() ?? [];
		$tokenEndpoint = $providerSettings[ProviderService::SETTING_TOKEN_ENDPOINT];

		$this->logger->debug('Fetching token endpoint for access_token');
		$client = $this->clientService->newClient();

		$result = $client->post(
			$tokenEndpoint,
			[
				'body' => [
					'refresh_token' => $session->getRefreshToken(),
					'client_id' => $provider->getClientId(),
					'grant_type' => 'refresh_token',
					'scope' => $provider->getScope(),
				],
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($provider->getClientId() . ':' . $provider->getClientSecret()),
				],
			]
		);

		$data = json_decode($result->getBody(), true);

		$accessToken = $data['access_token'];
		$session->setAccessToken($accessToken);

		return $this->sessionMapper->update($session);
	}

	private function fetchUserinfo($session) : array {
		$provider = $this->providerService->getProvider($session->getProviderId());
		$providerSettings = $provider->getSettings() ?? [];
		$userinfoEndpoint = $providerSettings[ProviderService::SETTING_USERINFO_ENDPOINT];
						
		$this->logger->debug('Fetching user info endpoint');
		$client = $this->clientService->newClient();
		
		$result = $client->get(
			$userinfoEndpoint,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $session->getAccessToken(),
				],
			]
		);

		$userinfo = json_decode($result->getBody(), true);

		return $userinfo;
	}

	public function syncUser($userId, $providerId) {
		$provider = $this->providerService->getProvider($providerId);
		$session = $this->providerService->getProviderSession($userId, $providerId);

		$displaynameAttribute = $provider->getDisplayNameClaim() ?? 'name';
		$groupsAttribute = $provider->getGroupsClaim() ?? 'groups';

		$session = $this->refreshAccessToken($session);
		$userinfo = $this->fetchUserinfo($session);

		// Update AAI display name in Connected accounts
		$userinfoDisplayName = $userinfo[$displaynameAttribute];
		if ($userinfoDisplayName) {
			$session->setUserinfoDisplayName($userinfoDisplayName);
		}

		// TODO: Throw exception on missing groups attribute
		$serverGroups = $userinfo[$groupsAttribute] ?? array();

		$localGroups = $this->voGroupBackend->getUserGroups($userId);
		$user = $this->userManager->get($userId);

		foreach ($serverGroups as $gid) {
			$group = $this->createOrUpdateLocalGroup($provider, $gid);
			if (!$group->inGroup($user)) {
				$group->addUser($user);
			}
			// Remove gid from localGroups to be deleted
			$localGroups = array_diff($localGroups, [$gid]);
		}

		foreach ($localGroups as $gid) {
			$group = $this->groupManager->get($gid);
			$groupProviderId = $this->voGroupBackend->getProviderId($gid);
			// Only delete existing groups for current provider
			if ($groupProviderId === $provider->getId()) {
				$group->removeUser($user);
			}
		}

		// Update sync timestamp
		$session->setLastSync($this->timeFactory->getDateTime());
		$session = $this->sessionMapper->update($session);
	}

	//
	private function createOrUpdateLocalGroup($provider, $gid) : Group {
		// Generate new display name
		$groupsRegex = $provider->getGroupsRegex();
		$matches = [];
		$displayName = $gid;
		$pattern = "/" . $groupsRegex . "/";
		if (preg_match($pattern, $gid, $matches)) {
			$displayName = $matches[1] ?? $matches[0];
		}
		
		if ($this->voGroupBackend->groupExists($gid)) {
			$group = $this->groupManager->get($gid);
			if ($group->getDisplayName() !== $displayName) {
				$group->setDisplayName($displayName);
			}
			if ($this->voGroupBackend->getProviderId($gid) !== $provider->getId()) {
				$this->voGroupBackend->setProviderId($gid, $provider->getId());
			}
		} else {
			$this->voGroupBackend->createGroup($gid, $displayName);
			$this->voGroupBackend->setProviderId($gid, $provider->getId());
			$group = $this->groupManager->get($gid);
		}

		return $group;
	}

	// TODO: Improve performance
	public function updateAllProviderGroups($provider) {
		$providerId = $provider->getId();
		// All groups managed by the backend
		$groups = $this->voGroupBackend->getGroups();
		foreach ($groups as $gid) {
			// Filter by provider
			if ($this->voGroupBackend->getProviderId($gid) === $providerId) {
				$this->createOrUpdateLocalGroup($provider, $gid);
			}
		}
	}

	// TODO: Improve performance while maintaining proper event management
	public function removeAllProviderMemberships($provider) {
		$providerId = $provider->getId();
		// All groups managed by the backend
		$groups = $this->voGroupBackend->getGroups();
		foreach ($groups as $gid) {
			// Filter by provider
			if ($this->voGroupBackend->getProviderId($gid) === $providerId) {
				$group = $this->groupManager->get($gid);
				$groupUsers = $group->getUsers();
				// Remove all users
				foreach ($groupUsers as $user) {
					$group->removeUser($user);
				}
			}
		}
	}

	// TODO: Improve performance while maintaining proper event management
	public function removeAllSessionMemberships($session) {
		$userId = $session->getUid();
		$providerId = $session->getProviderId();

		// All groups managed by the backend for session user
		$groups = $this->voGroupBackend->getUserGroups($userId);
		foreach ($groups as $gid) {
			// Filter by provider
			if ($this->voGroupBackend->getProviderId($gid) === $providerId) {
				$group = $this->groupManager->get($gid);
				$user = $this->userManager->get($userId);
				$group->removeUser($user);
			}
		}
	}
}
