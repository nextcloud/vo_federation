<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Knut Ahlers <knut@ahlers.me>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\VO_Federation\Backend;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OCA\VO_Federation\Service\ProviderService;
use OCP\Group\Backend\ABackend;
use OCP\Group\Backend\IGetDisplayNameBackend;
use OCP\Group\Backend\ISetDisplayNameBackend;
use OCP\Group\Backend\IDeleteGroupBackend;
use OCP\Group\Backend\IAddToGroupBackend;
use OCP\Group\Backend\IRemoveFromGroupBackend;
use OCP\Group\Backend\INamedBackend;
use OCP\Group\Backend\IFederationGroupBackend;
use OCP\IDBConnection;
use OCP\ILogger;

/**
 * Abstract base class for user management
 */
class GroupBackend extends ABackend implements
	IGetDisplayNameBackend,
	ISetDisplayNameBackend,
	IDeleteGroupBackend,
	IAddToGroupBackend,
	IRemoveFromGroupBackend,
	INamedBackend,
	IFederationGroupBackend {

	/**
	 * @var string The application name.
	 */
	private $appName;
	/**
	 * @var ILogger The logger instance.
	 */
	private $logger;

	/** @var string[] */
	private $groupCache = [];

	/** @var IDBConnection */
	private $dbConn;

	private ProviderService $providerService;

	public function __construct($AppName, ILogger $logger, IDBConnection $dbConn = null, ProviderService $providerService) {
		$this->appName = $AppName;
		$this->logger = $logger;
		$this->dbConn = $dbConn;
		$this->providerService = $providerService;
	}

	private function fixDI() {
		if ($this->dbConn === null) {
			$this->dbConn = \OC::$server->getDatabaseConnection();
		}
	}

	/**
	 * Try to create a new group
	 * @param string $gid The name of the group to create
	 * @return bool
	 *
	 * Tries to create a new group. If the group name already exists, false will
	 * be returned.
	 */
	public function createGroup(string $gid, string $displayName): bool {
		$this->fixDI();

		try {
			// Add group
			$builder = $this->dbConn->getQueryBuilder();
			$result = $builder->insert('vo_groups')
				->setValue('gid', $builder->createNamedParameter($gid))
				->setValue('displayname', $builder->createNamedParameter($displayName))
				->execute();
		} catch (UniqueConstraintViolationException $e) {
			$result = 0;
		}

		// Add to cache
		$this->groupCache[$gid] = [
			'gid' => $gid,
			'displayname' => $displayName
		];

		return $result === 1;
	}

	/**
	 * delete a group
	 * @param string $gid gid of the group to delete
	 * @return bool
	 *
	 * Deletes a group and removes it from the group_user-table
	 */
	public function deleteGroup(string $gid): bool {
		$this->fixDI();

		// Delete the group
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('vo_groups')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->execute();

		// Delete the group-user relation
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('vo_group_user')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->execute();

		// Delete from cache
		unset($this->groupCache[$gid]);

		return true;
	}

	/**
	 * Add a user to a group
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 *
	 * Adds a user to a group.
	 */
	public function addToGroup(string $uid, string $gid): bool {
		$this->fixDI();

		// No duplicate entries!
		if (!$this->inGroup($uid, $gid)) {
			$qb = $this->dbConn->getQueryBuilder();
			$qb->insert('vo_group_user')
				->setValue('uid', $qb->createNamedParameter($uid))
				->setValue('gid', $qb->createNamedParameter($gid))
				->execute();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Removes a user from a group
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 *
	 * removes the user from a group.
	 */
	public function removeFromGroup(string $uid, string $gid): bool {
		$this->fixDI();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('vo_group_user')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->execute();

		return true;
	}

	/**
	 * is user in group?
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 *
	 * Checks whether the user is member of a group or not.
	 */
	public function inGroup($uid, $gid) {
		$this->fixDI();

		// check
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('uid')
			->from('vo_group_user')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->execute();

		$result = $cursor->fetch();
		$cursor->closeCursor();

		return $result ? true : false;
	}

	/**
	 * Get all groups a user belongs to
	 * @param string $uid Name of the user
	 * @return array an array of group names
	 *
	 * This function fetches all groups a user belongs to. It does not check
	 * if the user exists at all.
	 */
	public function getUserGroups($uid) {
		//guests has empty or null $uid
		if ($uid === null || $uid === '') {
			return [];
		}

		$this->fixDI();

		// No magic!
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('gu.gid', 'g.displayname', 'g.provider_id')
			->from('vo_group_user', 'gu')
			->leftJoin('gu', 'vo_groups', 'g', $qb->expr()->eq('gu.gid', 'g.gid'))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->execute();

		$groups = [];
		while ($row = $cursor->fetch()) {
			$groups[] = $row['gid'];
			$this->groupCache[$row['gid']] = [
				'gid' => $row['gid'],
				'displayname' => $row['displayname'],
				'providerId' => $row['provider_id'],
			];
		}
		$cursor->closeCursor();

		return $groups;
	}

	/**
	 * get a list of all groups
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of group names
	 *
	 * Returns a list with all groups
	 */
	public function getGroups($search = '', $limit = null, $offset = null) {
		$this->fixDI();

		$query = $this->dbConn->getQueryBuilder();
		$query->select('gid')
			->from('vo_groups')
			->orderBy('gid', 'ASC');

		if ($search !== '') {
			$query->where($query->expr()->iLike('gid', $query->createNamedParameter(
				'%' . $this->dbConn->escapeLikeParameter($search) . '%'
			)));
			$query->orWhere($query->expr()->iLike('displayname', $query->createNamedParameter(
				'%' . $this->dbConn->escapeLikeParameter($search) . '%'
			)));
		}

		$query->setMaxResults($limit)
			->setFirstResult($offset);
		$result = $query->execute();

		$groups = [];
		while ($row = $result->fetch()) {
			$groups[] = $row['gid'];
		}
		$result->closeCursor();

		return $groups;
	}

	/**
	 * check if a group exists
	 * @param string $gid
	 * @return bool
	 */
	public function groupExists($gid) {
		$this->fixDI();

		// Check cache first
		if (isset($this->groupCache[$gid])) {
			return true;
		}

		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('gid', 'displayname', 'provider_id')
			->from('vo_groups')
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->execute();
		$result = $cursor->fetch();
		$cursor->closeCursor();

		if ($result !== false) {
			$this->groupCache[$gid] = [
				'gid' => $gid,
				'displayname' => $result['displayname'],
				'providerId' => $result['provider_id'],
			];
			return true;
		}
		return false;
	}

	/**
	 * get a list of all users in a group
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array an array of user ids
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		$this->fixDI();

		$query = $this->dbConn->getQueryBuilder();
		$query->select('g.uid')
			->from('vo_group_user', 'g')
			->where($query->expr()->eq('gid', $query->createNamedParameter($gid)))
			->orderBy('g.uid', 'ASC');

		if ($search !== '') {
			$query->leftJoin('g', 'users', 'u', $query->expr()->eq('g.uid', 'u.uid'))
				->leftJoin('u', 'preferences', 'p', $query->expr()->andX(
					$query->expr()->eq('p.userid', 'u.uid'),
					$query->expr()->eq('p.appid', $query->expr()->literal('settings')),
					$query->expr()->eq('p.configkey', $query->expr()->literal('email')))
				)
				// sqlite doesn't like re-using a single named parameter here
				->andWhere(
					$query->expr()->orX(
						$query->expr()->ilike('g.uid', $query->createNamedParameter('%' . $this->dbConn->escapeLikeParameter($search) . '%')),
						$query->expr()->ilike('u.displayname', $query->createNamedParameter('%' . $this->dbConn->escapeLikeParameter($search) . '%')),
						$query->expr()->ilike('p.configvalue', $query->createNamedParameter('%' . $this->dbConn->escapeLikeParameter($search) . '%'))
					)
				)
				->orderBy('u.uid_lower', 'ASC');
		}

		if ($limit !== -1) {
			$query->setMaxResults($limit);
		}
		if ($offset !== 0) {
			$query->setFirstResult($offset);
		}

		$result = $query->execute();

		$users = [];
		while ($row = $result->fetch()) {
			$users[] = $row['uid'];
		}
		$result->closeCursor();

		return $users;
	}

	public function getDisplayName(string $gid): string {
		if (isset($this->groupCache[$gid])) {
			$displayName = $this->groupCache[$gid]['displayname'];

			if (isset($displayName) && trim($displayName) !== '') {
				return $displayName;
			}
		}

		$this->fixDI();

		$query = $this->dbConn->getQueryBuilder();
		$query->select('displayname')
			->from('vo_groups')
			->where($query->expr()->eq('gid', $query->createNamedParameter($gid)));

		$result = $query->execute();
		$displayName = $result->fetchOne();
		$result->closeCursor();

		return (string) $displayName;
	}

	public function setDisplayName(string $gid, string $displayName): bool {
		$this->fixDI();

		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->update('vo_groups')
			->set('displayname', $qb->createNamedParameter($displayName))
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->executeStatement();

		$this->groupCache[$gid]['displayname'] = $displayName;

		return $result === 1;
	}

	public function getProviderId(string $gid): int {
		if (isset($this->groupCache[$gid])) {
			$providerId = $this->groupCache[$gid]['providerId'];

			if (isset($providerId)) {
				return $providerId;
			}
		}

		$this->fixDI();

		$query = $this->dbConn->getQueryBuilder();
		$query->select('provider_id')
			->from('vo_groups')
			->where($query->expr()->eq('gid', $query->createNamedParameter($gid)));

		$result = $query->execute();
		$providerId = $result->fetchOne();
		$result->closeCursor();

		return (int) $providerId;
	}

	public function setProviderId(string $gid, int $providerId) : bool {
		$this->fixDI();

		$qb = $this->dbConn->getQueryBuilder();
		$result = $qb->update('vo_groups')
			->set('provider_id', $qb->createNamedParameter($providerId))
			->where($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->executeStatement();

		$this->groupCache[$gid]['providerId'] = $providerId;

		return $result === 1;
	}

	public function getBackendName(): string {
		return 'VO';
	}

	public function isFederatedGroup(string $groupId): bool {
		return strpos($groupId, 'urn') === 0;
	}
}
