<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Service;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Backend\GroupBackend;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\ILogger;

class VirtualOrganisationService {
	/** @var IConfig */
	private $config;

	private $groupManager;
	private $userManager;
	private $voGroupBackend;

	/** @var ProviderService */
	private $providerService;

	/** @var ILogger */
	private $logger;	

	public function __construct(IConfig $config, IGroupManager $groupManager, IUserManager $userManager, GroupBackend $voGroupBackend, ProviderService $providerService, IClientService $clientService, ILogger $logger
	) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->voGroupBackend = $voGroupBackend;
		$this->providerService = $providerService;
		$this->clientService = $clientService;
		$this->logger = $logger;
	}

	public function addVOUser($gid, $userId, $displayName, $aai) {
		$this->voGroupBackend->createVOGroup($gid, $displayName, $aai);
		$group = $this->groupManager->get($gid);
		$group->addUser($this->userManager->get($userId));
	}

	public function syncUser($userId, $aai) {
		$provider = $this->providerService->getProviderWithSettingsForClientId($aai);

		$displaynameAttribute = $provider[ProviderService::SETTING_MAPPING_DISPLAYNAME] ?? 'preferred_displayname';
		$groupsAttribute = $provider[ProviderService::SETTING_MAPPING_GROUPS] ?? 'groups';
		$regexAttribute = $provider[ProviderService::SETTING_MAPPING_REGEX_PATTERN] ?? '.*';

		$userinfoEndpoint = $provider[ProviderService::SETTING_USERINFO_ENDPOINT];

		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, $aai . '-accessToken');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, $aai . '-refreshToken');

		$this->logger->debug('Fetching token endpoint for access_token');
		$client = $this->clientService->newClient();

		$clientSecret = $provider[ProviderService::SETTING_CLIENT_SECRET];
		$tokenEndpoint = $provider[ProviderService::SETTING_TOKEN_ENDPOINT];
		$scope = $provider[ProviderService::SETTING_SCOPE] ?? 'openid profile';

		$result = $client->post(
			$tokenEndpoint,
			[
				'body' => [
					'refresh_token' => $refreshToken,
					'client_id' => $aai,
					'grant_type' => 'refresh_token',
					'scope' => $scope,
				],
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($aai . ':' . $clientSecret),
				],					
			]
		);

		$data = json_decode($result->getBody(), true);

		$accessToken = $data['access_token'];
		$this->config->setUserValue($userId, Application::APP_ID, $aai . '-accessToken', $accessToken);

		$options = [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
			],
		];
		$this->logger->debug('Fetching user info endpoint');
		$result = $client->get($userinfoEndpoint, $options);

		$userinfo = json_decode($result->getBody(), true);

		$displayName = $userinfo[$displaynameAttribute] ?? $userId;
		$groups = $userinfo[$groupsAttribute] ?? array();

		$this->logger->info('Userinfo: ' . json_encode($userinfo, JSON_THROW_ON_ERROR));

		$this->config->setUserValue($userId, Application::APP_ID, $aai . '-displayName', $displayName);

		$userGroups = $this->voGroupBackend->getUserGroups($userId);

		foreach ($groups as $gid) {			
			$matches = [];
			$displayName = $gid;
			$pattern = "/" . $regexAttribute . "/";
			if (preg_match($pattern, $gid, $matches)) {
				$displayName = $matches[1] ?? $matches[0];
			}
			$this->addVOUser($gid, $userId, $displayName, $aai);
			// Remove $gid from $userGroups
 			$userGroups = array_diff($userGroups, [$gid]);
		}

		foreach ($userGroups as $gid) {
			$group = $this->groupManager->get($gid);
			$groupAAI = $this->voGroupBackend->getAAI($gid);
			if ($aai === $groupAAI) {
				$group->removeUser($this->userManager->get($userId));
			}
		}

		$this->config->setUserValue($userId, Application::APP_ID, $aai . '-timestamp', time());
	}
}

