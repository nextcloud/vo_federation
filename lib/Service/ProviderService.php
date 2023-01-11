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
use OCA\VO_Federation\Avatar\NamedAvatar;
use OCA\VO_Federation\Db\Provider;
use OCA\VO_Federation\Db\ProviderMapper;
use OCA\VO_Federation\Db\Session;
use OCA\VO_Federation\Db\SessionMapper;
use OCA\VO_Federation\Db\TrustedInstance;
use OCA\VO_Federation\Db\TrustedInstanceMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\IAppContainer;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IAvatar;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class ProviderService {
	public const SETTING_AUTHORIZATION_ENDPOINT = 'authorizationEndpoint';
	public const SETTING_TOKEN_ENDPOINT = 'tokenEndpoint';
	public const SETTING_JWKS_ENDPOINT = 'jwksEndpoint';
	public const SETTING_USERINFO_ENDPOINT = 'userinfoEndpoint';
	public const SETTING_EXTRA_CLAIMS = 'extraClaims';
	public const SETTING_JWKS_CACHE = 'jwksCache';
	public const SETTING_JWKS_CACHE_TIMESTAMP = 'jwksCacheTimestamp';

	/** @var ProviderMapper */
	private $providerMapper;
	/** @var SessionMapper */
	private $sessionMapper;
	/** @var TrustedInstanceMapper */
	private $trustedInstanceMapper;
	/** @var IAppContainer */
	private $appContainer;

	private IConfig $config;
	private IL10N $l;
	private LoggerInterface $logger;
	private IAppData $appData;
	private IURLGenerator $urlGenerator;

	public function __construct(IConfig $config,
								IL10N $l10n,
								LoggerInterface $logger,
								IAppData $appData,
								ProviderMapper $providerMapper,
								SessionMapper $sessionMapper,
								TrustedInstanceMapper $trustedInstanceMapper,
								IAppContainer $appContainer,
								IURLGenerator $urlGenerator) {
		$this->config = $config;
		$this->l = $l10n;
		$this->logger = $logger;
		$this->appData = $appData;
		$this->providerMapper = $providerMapper;
		$this->sessionMapper = $sessionMapper;
		$this->trustedInstanceMapper = $trustedInstanceMapper;
		$this->appContainer = $appContainer;
		$this->urlGenerator = $urlGenerator;
	}

	public function getProvidersWithSettings(): array {
		$trustedInstanceMapper = $this->trustedInstanceMapper;
		$providers = $this->providerMapper->getProviders();
		return array_map(function ($provider) use ($trustedInstanceMapper) {
			$providerId = $provider->getId();
			$trustedInstances = $trustedInstanceMapper->findAll($providerId);
			$provider = $provider->jsonSerialize();
			$provider['trustedInstances'] = array_map(function ($trustedInstance) {
				return $trustedInstance->getInstanceUrl();
			}, $trustedInstances);

			$avatar = $this->getAvatar($providerId);
			if (!is_null($avatar) && $avatar->exists()) {
				$provider['avatarUrl'] = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.avatar.getAvatar', ['providerId' => $providerId, 'size' => 32]);
			}			
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

	public function getProviderSession(string $uid, int $providerId): ?Session {
		try {
			return $this->sessionMapper->findSessionByProviderId($uid, $providerId);
		} catch (DoesNotExistException $e) {
			return null;
		}		
	}

	public function deleteProvider(int $providerId): Provider {
		$provider = $this->getProvider($providerId);
		$this->providerMapper->delete($provider);
		$this->trustedInstanceMapper->deleteAll($providerId);
		$this->sessionMapper->deleteAllSessions($providerId);

		$groupsService = $this->appContainer->get(GroupsService::class);
		$groupsService->removeAllProviderMemberships($provider);

		return $provider;
	}

	public function deleteProviderSession(string $uid, int $providerId): Session {
		$deletedSession = $this->sessionMapper->deleteSession($uid, $providerId);

		$groupsService = $this->appContainer->get(GroupsService::class);
		$groupsService->removeAllSessionMemberships($deletedSession);

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
		foreach ($newTrustedInstances as $instanceUrl) {
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

	public function getAvatar(int $providerId): ?IAvatar {
		try {
			$providerFolder = $this->appData->getFolder('provider');
		} catch (NotFoundException $e) {
			$providerFolder = $this->appData->newFolder('provider');
		}
		try {
			$folder = $providerFolder->getFolder((string) $providerId);
		} catch (NotFoundException $e) {
			$folder = $providerFolder->newFolder((string) $providerId);
		}
	
		try {
			$provider = $this->providerMapper->getProvider($providerId);
			return new NamedAvatar($folder, $this->l, $provider->getIdentifier(), $this->logger, $this->config);	
		} catch (\Exception $e) {
			return null;
		}
	}
}
