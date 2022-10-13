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
use OCP\IConfig;

class ProviderService {
	public const SETTING_CLIENT_NAME = 'identifier';
	public const SETTING_CLIENT_ID = 'clientId';
	public const SETTING_CLIENT_SECRET = 'clientSecret';
	public const SETTING_AUTHORIZATION_ENDPOINT = 'authorizationEndpoint';
	public const SETTING_TOKEN_ENDPOINT = 'tokenEndpoint';
	public const SETTING_JWKS_ENDPOINT = 'jwksEndpoint';
	public const SETTING_USERINFO_ENDPOINT = 'userinfoEndpoint';
	public const SETTING_SCOPE = 'scope';
	public const SETTING_EXTRA_CLAIMS = 'extraClaims';
	public const SETTING_MAPPING_UID = 'mappingUid';
	public const SETTING_MAPPING_UID_DEFAULT = 'sub';
	public const SETTING_MAPPING_DISPLAYNAME = 'mappingDisplayName';
	public const SETTING_MAPPING_GROUPS = 'mappingGroups';
	public const SETTING_MAPPING_REGEX_PATTERN = 'mappingRegexPattern';
	public const SETTING_TRUSTED_INSTANCES = 'trustedInstances';
	public const SETTING_JWKS_CACHE = 'jwksCache';
	public const SETTING_JWKS_CACHE_TIMESTAMP = 'jwksCacheTimestamp';

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	public function getProvidersWithSettings(): array {
		$providerIdLatest = (int) $this->config->getAppValue(Application::APP_ID, 'providerIdLatest', -1);
		$providers = [];
		for ($i = 0; $i <= $providerIdLatest;$i++) {
			$providerSettings = $this->getProviderWithSettings($i);
			$providers[] = array_merge($providerSettings, ['providerId' => $i]);
		}
		return $providers;
	}

	public function getProviderWithSettings(int $id): array {
		$providerSettings = $this->getSettings($id);
		return $providerSettings;
	}

	public function getSettings(int $providerId): array {
		$result = [];
		foreach ($this->getSupportedSettings() as $setting) {
			$value = $this->getSetting($providerId, $setting);
			$result[$setting] = $this->convertToJSON($setting, $value);
		}
		return $result;
	}

	public function setSettings(int $providerId, array $settings): array {
		$storedSettings = $this->getSettings($providerId);
		foreach ($settings as $setting => $value) {
			if (!in_array($setting, $this->getSupportedSettings(), true)) {
				continue;
			}
			$this->setSetting($providerId, $setting, $this->convertFromJSON($setting, $value));
			$storedSettings[$setting] = $value;
		}
		return $storedSettings;
	}

	public function deleteSettings(int $providerId): void {
		foreach ($this->getSupportedSettings() as $setting) {
			$this->config->deleteAppValue(Application::APP_ID, $this->getSettingsKey($providerId, $setting));
		}
	}

	public function setSetting(int $providerId, string $key, string $value): void {
		$this->config->setAppValue(Application::APP_ID, $this->getSettingsKey($providerId, $key), $value);
	}

	public function getSetting(int $providerId, string $key, string $default = ''): string {
		$value = $this->config->getAppValue(Application::APP_ID, $this->getSettingsKey($providerId, $key), '');
		if ($value === '') {
			return $default;
		}
		return $value;
	}

	private function getSettingsKey(int $providerId, string $key): string {
		return 'provider-' . $providerId . '-' . $key;
	}

	private function getSupportedSettings(): array {
		return [
			self::SETTING_CLIENT_NAME,
			self::SETTING_CLIENT_ID,
			self::SETTING_CLIENT_SECRET,
			self::SETTING_AUTHORIZATION_ENDPOINT,
			self::SETTING_TOKEN_ENDPOINT,
			self::SETTING_JWKS_ENDPOINT,
			self::SETTING_USERINFO_ENDPOINT,
			self::SETTING_SCOPE,
			self::SETTING_EXTRA_CLAIMS,
			self::SETTING_MAPPING_UID,
			self::SETTING_MAPPING_DISPLAYNAME,
			self::SETTING_MAPPING_GROUPS,
			self::SETTING_MAPPING_REGEX_PATTERN,
			self::SETTING_TRUSTED_INSTANCES,
		];
	}

	private function convertFromJSON(string $key, $value): string {
		if ($key === self::SETTING_TRUSTED_INSTANCES) {
			$value = json_encode($value);
		}
		//if ($key === self::SETTING_UNIQUE_UID || $key === self::SETTING_CHECK_BEARER) {
		//	$value = $value ? '1' : '0';
		//}
		return (string)$value;
	}

	private function convertToJSON(string $key, $value) {
		// default is disabled (if not set)
		//if ($key === self::SETTING_UNIQUE_UID || $key === self::SETTING_CHECK_BEARER) {
		//	return $value === '1';
		//}
		if ($key === self::SETTING_TRUSTED_INSTANCES) {
			return json_decode($value);
		}
		return (string)$value;
	}

	private $settingsCache = [];

	public function getSettingClientNameForClientId($clientId) {
		if (isset($this->settingsCache[$clientId])) {
			$displayName = $this->settingsCache[$clientId][self::SETTING_CLIENT_NAME];

			if (isset($displayName) && trim($displayName) !== '') {
				return $displayName;
			}
		}

		$providers = $this->getProvidersWithSettings();
		foreach ($providers as $provider) {
			if ($provider[self::SETTING_CLIENT_ID] === $clientId) {
				$displayName = $provider[self::SETTING_CLIENT_NAME];
				$this->settingsCache[$clientId][self::SETTING_CLIENT_NAME] = $displayName;
				return $displayName;
			}
		}
		return '';
	}

	public function getSettingTrustedInstancesForClientId($clientId) {
		if (isset($this->settingsCache[$clientId])) {
			$trustedInstances = $this->settingsCache[$clientId][self::SETTING_TRUSTED_INSTANCES];

			if (is_array($trustedInstances)) {
				return $trustedInstances;
			}
		}

		$providers = $this->getProvidersWithSettings();
		foreach ($providers as $provider) {
			if ($provider[self::SETTING_CLIENT_ID] === $clientId) {
				$trustedInstances = $provider[self::SETTING_TRUSTED_INSTANCES];
				$this->settingsCache[$clientId][self::SETTING_TRUSTED_INSTANCES] = $trustedInstances;
				return $trustedInstances;
			}
		}
		return [];
	}
}
