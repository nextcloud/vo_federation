<?php

namespace OCA\VO_Federation\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ShareMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vo_shares', Share::class);
	}

	public function getShare(int $id): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param int $federatedGroupShareId
	 * @return array
	 */
	public function findAll(int $federatedGroupShareId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('federated_group_share_id', $qb->createNamedParameter($federatedGroupShareId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	* @param int|null $maxTries 
	* @param int|null $limit max results
	 * @return array
	 */
	public function getUnsentShares(int $maxTries = null, int $limit = null): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNotNull('notification'))
			->orderBy('try', 'ASC')
			->setMaxResults($limit);

		if ($maxTries) {
			$qb->andWhere($qb->expr()->lt('try', $qb->createNamedParameter($maxTries, IQueryBuilder::PARAM_INT)));
		}

		return $this->findEntities($qb);
	}

	/**
	* @param int|null $maxTries 
	* @param int|null $limit max results
	 * @return array
	 */
	public function getLocalGroupShareIds(): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('parent')
			->from('share')
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share\IShare::TYPE_FEDERATED_GROUP, IQueryBuilder::PARAM_INT))
			);

		$result = $qb->executeQuery();
		try {
			$ids = [];
			while ($row = $result->fetch()) {
				$ids[] = $row['parent'];
			}
			return $ids;
		} finally {
			$result->closeCursor();
		}
	}

}
