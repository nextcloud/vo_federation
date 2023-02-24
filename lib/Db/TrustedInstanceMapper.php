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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

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
