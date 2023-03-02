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

namespace OCA\VO_Federation\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000000Date20221229000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vo_shares')) {
			$table = $schema->createTable('vo_shares');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('federated_group_share_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('instance_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('cloud_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('accepted', Types::SMALLINT, [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('notification', Types::STRING, [
				'notnull' => false
			]);
			$table->addColumn('try', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
		}
		
		return $schema;
	}
}
