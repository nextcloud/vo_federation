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

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Share\IShare;
use OCP\Group\Backend\IFederationGroupBackend;
use OCP\Group\Backend\IGroupDetailsBackend;

class FederatedGroupPlugin implements ISearchPlugin {
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

	public function __construct(IConfig $config, IGroupManager $groupManager, IUserSession $userSession) {
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->userSession = $userSession;

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
		
		$federatedGroups = array_filter($groups, function(IGroup $group) {
			$federationGroupBackend = $this->getFederationGroupBackend();			
			if (!is_null($federationGroupBackend)) {
				return $federationGroupBackend->groupExists($group->getGID());
			}
			return false;
		});
		$federatedGroups = array_map(function (IGroup $group) {
			return $group->getGID();
		}, $federatedGroups);
		$groupIds = array_intersect($groupIds, $federatedGroups);

		$lowerSearch = strtolower($search);
		foreach ($groups as $group) {
			if ($group->hideFromCollaboration()) {
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
					'shareWithDescription' => $this->getShareWithDescription($group)
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
					'shareWithDescription' => $this->getShareWithDescription($group)
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
					'shareWithDescription' => $this->getShareWithDescription($group)					
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

	/**
	 * Return first group backend implementing IFederationGroupBackend
	 */
	private function getFederationGroupBackend() {
		$backends = $this->groupManager->getBackends();
		foreach ($backends as $backend) {
			if ($backend instanceof IFederationGroupBackend) {
				return $backend;
			}
		}
		return null;		
	}

	private function getGroupDetailsBackend() {
		$federationGroupBackend = $this->getFederationGroupBackend();
		if ($federationGroupBackend instanceof IGroupDetailsBackend) {
			return $federationGroupBackend;
		}

		$backends = $this->groupManager->getBackends();
		foreach ($backends as $backend) {
			if ($backend instanceof IGroupDetailsBackend) {
				return $backend;
			}
		}
		return null;		
	}

	private function getShareWithDescription(IGroup $group) {
		$groupDetailsBackend = $this->getGroupDetailsBackend();
		if (!is_null($groupDetailsBackend)) {
			$groupDetails = $groupDetailsBackend->getGroupDetails($group->getGID());
			return $groupDetails['shareWithDescription'];
		}
		return null;
	}
}
