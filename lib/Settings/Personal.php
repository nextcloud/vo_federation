<?php

namespace OCA\VO_Federation\Settings;

use OCA\VO_Federation\AppInfo\Application;

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

	public function __construct(IConfig $config,
								IInitialState $initialStateService,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$displayName = $this->config->getUserValue($this->userId, Application::APP_ID, 'displayName');
		$groups = $this->config->getUserValue($this->userId, Application::APP_ID, 'groups');

		$userConfig = [
			'displayName' => $displayName,
			'groups' => $groups
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);

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
