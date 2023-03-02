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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000000Date20220823234000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vo_groups')) {
			$table = $schema->createTable('vo_groups');
			$table->addColumn('gid', 'text', [
				'notnull' => true,
				'default' => '',
			]);
			$table->addColumn('displayname', 'string', [
				'notnull' => true,
				'length' => 255,
				// Will be overwritten in postSchemaChange, but Oracle can not save
				// empty strings in notnull columns
				'default' => 'name',
			]);
			$table->addColumn('provider_id', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			//$table->setPrimaryKey(['gid']);
		}
		
		if (!$schema->hasTable('vo_group_user')) {
			$table = $schema->createTable('vo_group_user');
			$table->addColumn('gid', 'text', [
				'notnull' => true,
				'default' => '',
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			//$table->setPrimaryKey(['gid', 'uid']);
			//$table->addIndex(['uid'], 'vgu_uid_index');
		}

		return $schema;
	}
}
