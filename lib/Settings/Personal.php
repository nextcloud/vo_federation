<?php

namespace OCA\VO_Federation\Settings;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Service\ProviderService;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {

	/** @var IInitialState */
	private $initialStateService;
	/** @var string|null */
	private $userId;
	/** @var ProviderService */
	private $providerService;

	public function __construct(IInitialState $initialStateService,
								ProviderService $providerService,
								?string $userId) {
		$this->initialStateService = $initialStateService;
		$this->providerService = $providerService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$providerService = $this->providerService;
		$userId = $this->userId;

		$providers = $providerService->getProvidersWithSettings();
		$providersWithSession = array_map(function (array $provider) use ($providerService, $userId) {
			$session = $providerService->getProviderSession($userId, $provider['id']);

			$providerWithSession = [
				'providerId' => $provider['id'],
				'identifier' => $provider['identifier'],
				'active' => false,
				'displayName' => '',
				'timestamp' => -1,
			];

			if ($session !== null) {
				$providerWithSession['active'] = true;
				$providerWithSession['displayName'] = $session->getUserinfoDisplayName();
				if ($session->getLastSync()) {
					$providerWithSession['timestamp'] = $session->getLastSync()->getTimestamp();
				}
			}

			return $providerWithSession;
		}, $providers);
		
		$this->initialStateService->provideInitialState('user-config', $providersWithSession);

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
