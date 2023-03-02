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

namespace OCA\VO_Federation\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SessionMapper extends QBMapper {
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $db, ITimeFactory $timeFactory) {
		parent::__construct($db, 'vo_oidc_sessions', Session::class);
		$this->timeFactory = $timeFactory;
	}

	public function getSession(int $id): Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id))
			);

		return $this->findEntity($qb);
	}

	public function findSessionByProviderId(string $uid, int $providerId) : Session {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR))
			)->andWhere(
				$qb->expr()->eq('provider_id', $qb->createNamedParameter($providerId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	public function deleteSession(string $uid, int $providerId) : Session {
		return $this->delete($this->findSessionByProviderId($uid, $providerId));
	}

	public function deleteAllSessions(int $providerId) : int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('provider_id', $qb->createNamedParameter($providerId, IQueryBuilder::PARAM_INT))
			);
		$result = $qb->executeStatement();
		return $result;
	}

	public function createOrUpdateSession(string $uid, int $providerId,
									string $idToken, string $idTokenSub, int $idTokenExp,
									string $accessToken, int $accessTokenExp,
									string $refreshToken, int $refreshTokenExp,
									string $userinfoDisplayName = null, DateTime $lastSync = null) {
		try {
			$session = $this->findSessionByProviderId($uid, $providerId);
		} catch (DoesNotExistException $eNotExist) {
			$session = null;
		}

		if ($session === null) {
			$session = new Session();
			if (($uid === null) || ($providerId === null)) {
				throw new DoesNotExistException('Session must be created. Missing required parameters.');
			}
			$session->setUid($uid);
			$session->setProviderId($providerId);
			$session->setIdToken($idToken);
			$session->setIdTokenSub($idTokenSub);
			$session->setIdTokenExp($idTokenExp);
			$session->setAccessToken($accessToken);
			$session->setAccessTokenExp($accessTokenExp);
			$session->setRefreshToken($refreshToken);
			$session->setRefreshTokenExp($refreshTokenExp);
			if ($userinfoDisplayName !== null) {
				$session->setUserinfoDisplayName($userinfoDisplayName);
			}
			if ($lastSync !== null) {
				//$this->timeFactory->getDateTime()
				$session->setLastSync($lastSync);
			}
			return $this->insert($session);
		} else {
			$session->setIdToken($idToken);
			$session->setIdTokenSub($idTokenSub);
			$session->setIdTokenExp($idTokenExp);
			$session->setAccessToken($accessToken);
			$session->setAccessTokenExp($accessTokenExp);
			$session->setRefreshToken($refreshToken);
			$session->setRefreshTokenExp($refreshTokenExp);
			if ($userinfoDisplayName !== null) {
				$session->setUserinfoDisplayName($userinfoDisplayName);
			}
			if ($lastSync !== null) {
				//$this->timeFactory->getDateTime()
				$session->setLastSync($lastSync);
			}
			return $this->update($session);
		}
	}
}
