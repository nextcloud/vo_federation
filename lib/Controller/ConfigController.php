<?php
/**
 * Nextcloud - VO Federation
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\VO_Federation\Controller;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Service\ProviderService;

class ConfigController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var string|null
	 */
	private $userId;

	/** @var ProviderService */
	private $providerService;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IL10N $l,
								?string $userId,
								ProviderService $providerService) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
		$this->userId = $userId;
		$this->providerService = $providerService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getUsername(): DataResponse {
		$username = $this->config->getUserValue($this->userId, Application::APP_ID, 'display_name');
		return new DataResponse($username);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array key/val pairs of config values
	 * @return DataResponse useless result
	 */
	public function setConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * set admin config values
	 *
	 * @param array key/val pairs of config values
	 * @return DataResponse useless result
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	public function updateProvider(int $providerId, array $values): DataResponse {
		$providerSettings = $this->providerService->setSettings($providerId, $values);

		return new DataResponse(1);
	}


	public function createProvider(array $values): JSONResponse {
		$providerId = (int) $this->config->getAppValue(Application::APP_ID, 'providerIdLatest', -1);
		$this->providerService->setSettings(++$providerId, $values);
		$this->config->setAppValue(Application::APP_ID, 'providerIdLatest', $providerId);

		return new JSONResponse(array_merge($values, ['providerId' => $providerId]));
	}

	public function deleteProvider(int $providerId): JSONResponse {
		$providerIdLatest = (int) $this->config->getAppValue(Application::APP_ID, 'providerIdLatest', -1);

		if ($providerId < 0 || $providerId > $providerIdLatest) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} elseif ($providerId <= $providerIdLatest) {
			for ($i = $providerId; $i < $providerIdLatest; $i++) {
				$providerSettingsNext = $this->providerService->getSettings($i + 1);
				$this->providerService->setSettings($i, $providerSettingsNext);
			}
			$this->config->setAppValue(Application::APP_ID, 'providerIdLatest', $providerIdLatest - 1);
		}

		return new JSONResponse([], Http::STATUS_OK);
	}


	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array key/val pairs of config values
	 * @return DataResponse useless result
	 */
	public function logoutProvider(int $providerId): DataResponse {
		$providerSettings = $this->providerService->getSettings($providerId);
		$clientId = $providerSettings['clientId'];
		if ($clientId) {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $clientId . '-accessToken');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $clientId . '-refreshToken');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $clientId . '-displayName');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $clientId . '-groups');
		}
		return new DataResponse(1);
	}
}
