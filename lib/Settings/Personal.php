<?php

namespace OCA\VO_Federation\Settings;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Service\ProviderService;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;

	/** @var ProviderService */
	private $providerService;

	public function __construct(IConfig $config,
								IInitialState $initialStateService,
								ProviderService $providerService,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->providerService = $providerService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$providerIdLatest = (int) $this->config->getAppValue(Application::APP_ID, 'providerIdLatest', -1);
		$providers = [];
		for ($i = 0; $i <= $providerIdLatest;$i++) {
			$providerSettings = $this->providerService->getProviderWithSettings($i);
			$clientId = $providerSettings['clientId'];
			if (!$clientId) {
				continue;
			}

			$displayName = $this->config->getUserValue($this->userId, Application::APP_ID, $clientId . '-displayName', '');
			$timestamp = $this->config->getUserValue($this->userId, Application::APP_ID, $clientId . '-timestamp', '');

			$providers[] = [
				'providerId' => $i,
				'identifier' => $providerSettings['identifier'],
				'clientId' => $clientId,
				'displayName' => $displayName,
				'timestamp' => $timestamp
			];
		}
		$this->initialStateService->provideInitialState('user-config', $providers);

		Util::addScript(Application::APP_ID, Application::APP_ID . '-personalSettings');
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
