<?php
namespace OCA\VO_Federation\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\VO_Federation\AppInfo\Application;

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
        $accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'accessToken');
        $refreshToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'refreshToken');
        $name = $this->config->getUserValue($this->userId, Application::APP_ID, 'name');

        $userConfig = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'name' => $name,
        ];
        $this->initialStateService->provideInitialState('user-config', $userConfig);
        return new TemplateResponse(Application::APP_ID, 'personalSettings');
    }

    public function getSection(): string {
        return 'connected-accounts';
    }

    public function getPriority(): int {
        return 10;
    }
}
