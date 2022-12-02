<?php

namespace OCA\VO_Federation\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TrustedInstanceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vo_trusted_instances', TrustedInstance::class);
	}

	/**
	 * @param int $providerId
	 * @return array
	 */
	public function findAll(int $providerId): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('provider_id', $qb->createNamedParameter($providerId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function deleteAll(int $providerId): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('provider_id', $qb->createNamedParameter($providerId, IQueryBuilder::PARAM_INT))
			);
		$result = $qb->executeStatement();
		return $result;
	}
}
