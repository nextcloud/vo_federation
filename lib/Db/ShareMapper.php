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
