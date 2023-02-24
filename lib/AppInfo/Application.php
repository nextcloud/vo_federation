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

namespace OCA\VO_Federation\AppInfo;

use OC\EventDispatcher\EventDispatcher;
use OCA\VO_Federation\Backend\GroupBackend;
use OCA\VO_Federation\Service\GroupsService;
use OCA\VO_Federation\Service\ProviderService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\User\Events\UserLoggedInEvent;

/**
 * Class Application
 *
 * @package OCA\VO_Federation\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'vo_federation';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			IGroupManager $groupManager,
			GroupBackend $groupBackend,
		) {
			$groupManager->addBackend($groupBackend);
		});

		$context->injectFn(function (EventDispatcher $dispatcher,
			ProviderService $providerService,
			GroupsService $groupsService) {
			/*
			 * @todo move the OCP events and then move the registration to `register`
			 */
			$dispatcher->addListener(
				UserLoggedInEvent::class,
				function (\OCP\User\Events\UserLoggedInEvent $event) use ($providerService, $groupsService) {
					$providers = $providerService->getProvidersWithSettings();
					foreach ($providers as $provider) {
						$providerId = $provider['id'];
						$userId = $event->getUser()->getUID();
						try {
							$session = $providerService->getProviderSession($userId, $providerId);
							if ($session !== null) {
								$groupsService->syncUser($userId, $providerId);
							}
						} catch (DoesNotExistException $e) {
						} catch (\Exception $other) {
							// TODO: Handle server availability
						}
					}
				}
			);
		});
	}
}
