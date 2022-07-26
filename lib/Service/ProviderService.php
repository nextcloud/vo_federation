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
	public const SETTING_JWKS_CACHE = 'jwksCache';
	public const SETTING_JWKS_CACHE_TIMESTAMP = 'jwksCacheTimestamp';

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
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
		];
	}

	private function convertFromJSON(string $key, $value): string {
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
		return (string)$value;
	}
}
