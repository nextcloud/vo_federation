<?php

declare(strict_types=1);
/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\VO_Federation\Settings;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Service\ProviderService;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	/** @var ProviderService */
	private $providerService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IInitialState */
	private $initialStateService;

	public function __construct(ProviderService $providerService,
								IURLGenerator $urlGenerator,
								IInitialState $initialStateService) {
		$this->providerService = $providerService;
		$this->urlGenerator = $urlGenerator;
		$this->initialStateService = $initialStateService;
	}

	public function getForm() {
		$this->initialStateService->provideInitialState('providers',
			$this->providerService->getProvidersWithSettings()
		);
		$this->initialStateService->provideInitialState('redirectUrl',
			$this->urlGenerator->linkToRouteAbsolute('vo_federation.login.code')
		);

		Util::addScript(Application::APP_ID, Application::APP_ID . '-adminSettings');
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection() {
		return 'community-aais';
	}

	public function getPriority() {
		return 90;
	}
}
