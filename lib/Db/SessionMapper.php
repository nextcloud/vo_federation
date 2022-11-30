<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use DateTime;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Utility\ITimeFactory;


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
			$session->setProviderId($uid);
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

