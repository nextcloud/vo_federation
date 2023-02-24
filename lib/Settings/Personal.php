<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
 *
 * @author Sandro Mesterheide <sandro.mesterheide@extern.publicplan.de>
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
