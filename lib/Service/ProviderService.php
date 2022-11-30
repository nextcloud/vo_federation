<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\VO_Federation\Service;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Db\Provider;
use OCA\VO_Federation\Db\ProviderMapper;
use OCA\VO_Federation\Db\Session;
use OCA\VO_Federation\Db\SessionMapper;
use OCA\VO_Federation\Db\TrustedInstance;
use OCA\VO_Federation\Db\TrustedInstanceMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;

class ProviderService {
	public const SETTING_AUTHORIZATION_ENDPOINT = 'authorizationEndpoint';
	public const SETTING_TOKEN_ENDPOINT = 'tokenEndpoint';
	public const SETTING_JWKS_ENDPOINT = 'jwksEndpoint';
	public const SETTING_USERINFO_ENDPOINT = 'userinfoEndpoint';
	public const SETTING_EXTRA_CLAIMS = 'extraClaims';
	public const SETTING_JWKS_CACHE = 'jwksCache';
	public const SETTING_JWKS_CACHE_TIMESTAMP = 'jwksCacheTimestamp';

	/** @var IConfig */
	private $config;
	/** @var ProviderMapper */
	private $providerMapper;
	/** @var SessionMapper */
	private $sessionMapper;
	/** @var TrustedInstanceMapper */
	private $trustedInstanceMapper;

	/** @var GroupsService */
	private $groupsService;

	public function __construct(IConfig $config, ProviderMapper $providerMapper, SessionMapper $sessionMapper, TrustedInstanceMapper $trustedInstanceMapper, GroupsService $groupsService) {
		$this->config = $config;
		$this->providerMapper = $providerMapper;
		$this->sessionMapper = $sessionMapper;
		$this->trustedInstanceMapper = $trustedInstanceMapper;
		$this->groupsService = $groupsService;
	}


	public function getProvidersWithSettings(): array {
		$trustedInstanceMapper = $this->trustedInstanceMapper;
		$providers = $this->providerMapper->getProviders();
		return array_map(function($provider) use ($trustedInstanceMapper) {
			$trustedInstances = $trustedInstanceMapper->findAll($provider->getId());
			$provider = $provider->jsonSerialize();
			$provider['trustedInstances'] = array_map(function($trustedInstance) {
				return $trustedInstance->getInstanceUrl();
			}, $trustedInstances);
			return $provider;
		}, $providers);
	}

	public function getProvider(int $providerId): Provider {
		return $this->providerMapper->getProvider($providerId);
	}	

	public function getProviderByIdentifier(string $identifier): ?Provider {
		try {
			return $this->providerMapper->findProviderByIdentifier($identifier);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	public function getProviderTrustedInstances($providerId) {
		return $this->trustedInstanceMapper->findAll($providerId);
	}

	public function getProviderSession(string $uid, int $providerId) : Session {
		$deletedSession = $this->sessionMapper->findSessionByProviderId($uid, $providerId);

		return $deletedSession;
	}

	public function deleteProvider(int $providerId) : Provider {
		$provider = $this->getProvider($providerId);
		$this->providerMapper->delete($provider);
		$this->trustedInstanceMapper->deleteAll($providerId);
		$this->sessionMapper->deleteAllSessions($providerId);

		$this->groupsService->removeAllProviderMemberships($provider);

		return $provider;
	}

	public function deleteProviderSession(string $uid, int $providerId) : Session {
		$deletedSession = $this->sessionMapper->deleteSession($uid, $providerId);

		$this->groupsService->removeAllSessionMemberships($deletedSession);

		return $deletedSession;
	}

	public function createOrUpdateTrustedInstances(int $providerId, array $newTrustedInstances): bool {
		$currentTrustedInstanceEntities = $this->trustedInstanceMapper->findAll($providerId);
		$existingNewTrustedInstances = [];
		$insertedOrDeleted = 0;
		foreach ($currentTrustedInstanceEntities as $current) {
			$instanceUrl = $current->getInstanceUrl();
			if (in_array($instanceUrl, $newTrustedInstances)) {
				$existingNewTrustedInstances[] = $instanceUrl;
			} else {
				$this->trustedInstanceMapper->delete($current);
				$insertedOrDeleted++;
			}
		}
		foreach($newTrustedInstances as $instanceUrl) {
			if (in_array($instanceUrl, $existingNewTrustedInstances)) {
				continue;
			}
			$trustedInstance = new TrustedInstance();
			$trustedInstance->setProviderId($providerId);
			$trustedInstance->setInstanceUrl($instanceUrl);
			$this->trustedInstanceMapper->insert($trustedInstance);
			$insertedOrDeleted++;
		}
		return $insertedOrDeleted !== 0;
	}

	private function getSupportedSettings(): array {
		return [
			self::SETTING_AUTHORIZATION_ENDPOINT,
			self::SETTING_TOKEN_ENDPOINT,
			self::SETTING_JWKS_ENDPOINT,
			self::SETTING_USERINFO_ENDPOINT,
			self::SETTING_EXTRA_CLAIMS,
		];
	}

}
