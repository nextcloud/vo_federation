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
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\VO_Federation\Backend\GroupBackend;
use OCA\VO_Federation\FederatedGroupShareProvider;
use OCA\VO_Federation\Federation\CloudIdResolver;
use OCA\VO_Federation\Listeners\LoadAdditionalScriptsListener;
use OCA\VO_Federation\Listeners\UserRemovedListener;
use OCA\VO_Federation\Middleware\ShareAPIMiddleware;
use OCA\VO_Federation\OCM\CloudGroupFederationProviderFiles;
use OCA\VO_Federation\Service\GroupsService;
use OCA\VO_Federation\Service\ProviderService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\IGroupManager;
use OCP\Share\IManager;
use OCP\User\Events\UserLoggedInEvent;
use OCP\Group\Events\UserRemovedEvent;
use Psr\Container\ContainerInterface;

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
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(UserRemovedEvent::class, UserRemovedListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			IGroupManager $groupManager,
			GroupBackend $groupBackend,
			IManager $shareManager,
			ICloudFederationProviderManager $federationProviderManager,
			ICloudIdManager $cloudIdManager,
			CloudIdResolver $resolver,
			ContainerInterface $appContainer
		) {
			$groupManager->addBackend($groupBackend);
			$shareManager->registerShareProvider(FederatedGroupShareProvider::class);
			$federationProviderManager->addCloudFederationProvider('file', 'Federated Files Sharing (federated_group)',
				function () use ($appContainer): CloudGroupFederationProviderFiles {
					return $appContainer->get(CloudGroupFederationProviderFiles::class);
				});
			$cloudIdManager->registerCloudIdResolver($resolver);
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
		
		$filesSharingAppContainer = \OC::$server->getRegisteredAppContainer('files_sharing');
		$filesSharingAppContainer->registerMiddleWare(ShareAPIMiddleware::class);
	}
}
