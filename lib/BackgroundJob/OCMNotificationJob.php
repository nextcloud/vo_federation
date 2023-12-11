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

namespace OCA\VO_Federation\BackgroundJob;

use OCA\VO_Federation\AddressHandler;
use OCA\VO_Federation\Db\ShareMapper as VOShareMapper;
use OCA\VO_Federation\FederatedGroupShareProvider;
use OCA\VO_Federation\Notifications;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Federation\ICloudIdManager;
use OCP\IL10N;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Class OCMNotificationJob
 *
 * Background job to re-send update of federated re-shares to the remote server in
 * case the server was not available on the first try
 *
 * @package OCA\VO_Federation\BackgroundJob
 */
class OCMNotificationJob extends TimedJob {
	private FederatedGroupShareProvider $shareProvider;
	private VOShareMapper $voShareMapper;

	private IUserManager $userManager;
	private ICloudIdManager $cloudIdManager;
	
	/** @var AddressHandler */
	private $addressHandler;

	private Notifications $notifications;

	/** @var IL10N */
	private $l;
	/** @var LoggerInterface */
	private $logger;

	public const MAX_TRIES = 5;

	public function __construct(FederatedGroupShareProvider $shareProvider,
								VOShareMapper $voShareMapper,
								IUserManager $userManager,
								ICloudIdManager $cloudIdManager,
								Notifications $notifications,
								AddressHandler $addressHandler,
								IL10N $l10n,
								LoggerInterface $logger,
								ITimeFactory $time) {
		parent::__construct($time);
		$this->shareProvider = $shareProvider;
		$this->voShareMapper = $voShareMapper;
		$this->userManager = $userManager;
		$this->cloudIdManager = $cloudIdManager;
		$this->notifications = $notifications;
		$this->addressHandler = $addressHandler;
		$this->l = $l10n;
		$this->logger = $logger;

		// Run once every 30 seconds
		$this->setInterval(30);
	}

	protected function run($arguments) {
		$voShares = $this->voShareMapper->getUnsentShares(self::MAX_TRIES);

		foreach ($voShares as $voShare) {
			try {
				$shareId = $voShare->getFederatedGroupShareId();
				$token = $voShare->getToken();
				$notification_action = $voShare->getNotification();

				$send = false;
				// TODO: Refactor switch, use constants for actions
				if ($notification_action === 'share') {
					$share = $this->shareProvider->getShareById($shareId);
					$sharedByFederatedId = $share->getSharedBy();
					if ($this->userManager->userExists($sharedByFederatedId)) {
						$cloudId = $this->cloudIdManager->getCloudId($sharedByFederatedId, $this->addressHandler->generateRemoteURL());
						$sharedByFederatedId = $cloudId->getId();
					}
					$ownerCloudId = $this->cloudIdManager->getCloudId($share->getShareOwner(), $this->addressHandler->generateRemoteURL());
					$send = $this->notifications->sendRemoteShare(
						$token,
						$voShare->getCloudId(),
						$share->getNode()->getName(),
						$shareId,
						$share->getShareOwner(),
						$ownerCloudId->getId(),
						$share->getSharedBy(),
						$sharedByFederatedId,
						$share->getShareType()
					);
				} elseif ($notification_action === 'unshare') {
					[, $remote] = $this->addressHandler->splitUserRemote($voShare->getCloudId());

					$send = $this->notifications->sendRemoteUnShare($remote, $shareId, $token);
					//$this->revokeShare($share, true);
				} elseif ($notification_action === 'unshare_reshare') {
					[, $remote] = $this->addressHandler->splitUserRemote($voShare->getCloudId());

					$remoteId = $this->shareProvider->getRemoteIdInt($shareId);
					$send = $this->notifications->sendRemoteUnShare($remote, $remoteId, $token);
					//$this->revokeShare($share, false);
				} elseif ($notification_action === 'reshare') {
					$share = $this->shareProvider->getShareById($shareId);
					$cloudId = $this->cloudIdManager->resolveCloudId($share->getShareOwner());
					$share->setShareOwner($cloudId->getUser());
					$remoteShare = $this->shareProvider->getShareFromExternalShareTable($share);
					$token = $remoteShare['share_token'];
					$remoteId = $remoteShare['remote_id'];
					$remote = $remoteShare['remote'];

					[$token, $remoteId] = $this->notifications->requestReShare(
						// the token for the original external share
						$token,
						$remoteId,
						$shareId,
						$remote,
						$share->getSharedBy(),
						$voShare->getCloudId(),
						$share->getPermissions(),
						$share->getNode()->getName()
					);

					// remote share was create successfully if we get a valid token as return
					$send = is_string($token) && $token !== '';
					if ($send) {
						$voShare->setToken($token);
						$this->shareProvider->storeRemoteId($shareId, (string)$remoteId);
					} else {
						// TODO: Other reasons
						$message_t = $this->l->t('File is already shared with %s', [$voShare->getCloudId()]);
						throw new \Exception($message_t);
					}
				}

				if ($send === false) {
					//$this->removeShareFromTableById($shareId);
					$message_t = $this->l->t('Sharing %1$s failed, could not find %2$s, maybe the server is currently unreachable or uses a self-signed certificate.',
						[$share->getNode()->getName(), $share->getSharedWith()]);
					throw new \Exception($message_t);
				}

				$voShare->setNotification(null);
			} catch (\Exception $e) {
				$this->logger->error('Failed to notify remote server of federated share, removing share.', [
					'exception' => $e,
					'app' => 'federatedfilesharing'
				]);
				$voShare->setTry($voShare->getTry() + 1);
			} finally {
				$this->voShareMapper->update($voShare);
			}
		}
	}
}
