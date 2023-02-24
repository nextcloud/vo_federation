<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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
namespace OCA\VO_Federation\Collaboration;

use OCA\VO_Federation\AppInfo\Application;
use OCA\VO_Federation\Backend\GroupBackend;
use OCA\VO_Federation\Service\ProviderService;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IShare;

class FederatedGroupPlugin implements ISearchPlugin {
	/** @var bool */
	protected $hideFromCollaboration;
	/** @var bool */
	protected $shareeEnumeration;
	/** @var bool */
	protected $shareWithGroupOnly;
	/** @var bool */
	protected $shareeEnumerationInGroupOnly;
	/** @var bool */
	protected $groupSharingDisabled;

	/** @var IGroupManager */
	private $groupManager;
	/** @var IConfig */
	private $config;
	/** @var IUserSession */
	private $userSession;
	/** @var GroupBackend */
	private $voGroupBackend;
	/** @var ProviderService */
	private $providerService;

	private IURLGenerator $urlGenerator;

	public function __construct(IConfig $config, IGroupManager $groupManager, IUserSession $userSession, GroupBackend $voGroupBackend, ProviderService $providerService, IURLGenerator $urlGenerator) {
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->userSession = $userSession;
		$this->voGroupBackend = $voGroupBackend;
		$this->providerService = $providerService;
		$this->urlGenerator = $urlGenerator;

		$this->hideFromCollaboration = $this->config->getAppValue('vo_federation', 'hide_from_collaboration', 'no') === 'yes';
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->shareWithGroupOnly = $this->config->getAppValue('core', 'shareapi_only_share_with_group_members', 'no') === 'yes';
		$this->shareeEnumerationInGroupOnly = $this->shareeEnumeration && $this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
		$this->groupSharingDisabled = $this->config->getAppValue('core', 'shareapi_allow_group_sharing', 'yes') === 'no';
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		// TODO: check app manager has vo_federation enabled and/or share provider exists
		if ($this->groupSharingDisabled) {
			return false;
		}

		$hasMoreResults = false;
		$result = ['wide' => [], 'exact' => []];

		$groups = $this->groupManager->search($search, $limit, $offset);
		$groupIds = array_map(function (IGroup $group) {
			return $group->getGID();
		}, $groups);

		if (!$this->shareeEnumeration || count($groups) < $limit) {
			$hasMoreResults = true;
		}

		$userGroups = [];
		if (!empty($groups) && ($this->shareWithGroupOnly || $this->shareeEnumerationInGroupOnly)) {
			// Intersect all the groups that match with the groups this user is a member of
			$userGroups = $this->groupManager->getUserGroups($this->userSession->getUser());
			$userGroups = array_map(function (IGroup $group) {
				return $group->getGID();
			}, $userGroups);
			$groupIds = array_intersect($groupIds, $userGroups);
		}
		
		$voGroupBackend = $this->voGroupBackend;
		$federatedGroups = array_filter($groups, function (IGroup $group) use ($voGroupBackend) {
			return $voGroupBackend->groupExists($group->getGID());
		});
		$federatedGroups = array_map(function (IGroup $group) {
			return $group->getGID();
		}, $federatedGroups);
		$groupIds = array_intersect($groupIds, $federatedGroups);

		$lowerSearch = strtolower($search);
		foreach ($groups as $group) {
			if ($this->hideFromCollaboration && $group->hideFromCollaboration()) {
				continue;
			}

			// FIXME: use a more efficient approach
			$gid = $group->getGID();
			if (!in_array($gid, $groupIds)) {
				continue;
			}
			if (strtolower($gid) === $lowerSearch || strtolower($group->getDisplayName()) === $lowerSearch) {
				$result['exact'][] = [
					'label' => $group->getDisplayName(),
					'value' => [
						'shareType' => IShare::TYPE_FEDERATED_GROUP,
						'shareWith' => $gid
					],
					'shareWithDescription' => $this->getShareWithDescription($gid),
					'shareWithAvatar' => $this->getShareWithAvatarUrl($gid),
				];
			} else {
				if ($this->shareeEnumerationInGroupOnly && !in_array($group->getGID(), $userGroups, true)) {
					continue;
				}
				$result['wide'][] = [
					'label' => $group->getDisplayName(),
					'value' => [
						'shareType' => IShare::TYPE_FEDERATED_GROUP,
						'shareWith' => $gid
					],
					'shareWithDescription' => $this->getShareWithDescription($gid),
					'shareWithAvatar' => $this->getShareWithAvatarUrl($gid)
				];
			}
		}

		if ($offset === 0 && empty($result['exact'])) {
			// On page one we try if the search result has a direct hit on the
			// user id and if so, we add that to the exact match list
			$group = $this->groupManager->get($search);
			if ($group instanceof IGroup && !$group->hideFromCollaboration() && (!$this->shareWithGroupOnly || in_array($group->getGID(), $userGroups))) {
				$result['exact'][] = [
					'label' => $group->getDisplayName(),
					'value' => [
						'shareType' => IShare::TYPE_FEDERATED_GROUP,
						'shareWith' => $group->getGID()
					],
					'shareWithDescription' => $this->getShareWithDescription($group->getGID()),
					'shareWithAvatar' => $this->getShareWithAvatarUrl($group->getGID())
				];
			}
		}

		if (!$this->shareeEnumeration) {
			$result['wide'] = [];
		}

		$type = new SearchResultType('federated_groups');
		$searchResult->addResultSet($type, $result['wide'], $result['exact']);

		return $hasMoreResults;
	}

	private function getShareWithDescription($gid) : string {
		$providerId = $this->voGroupBackend->getProviderId($gid);
		$provider = $this->providerService->getProvider($providerId);
		return $provider->getIdentifier();
	}

	private function getShareWithAvatarUrl($gid) : ?string {
		$providerId = $this->voGroupBackend->getProviderId($gid);
		$avatar = $this->providerService->getAvatar($providerId);
		if (!is_null($avatar) && $avatar->exists()) {
			return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.avatar.getAvatar', ['providerId' => $providerId, 'size' => 32]);
		}
		return null;
	}
}
