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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000000Date20221128000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vo_oidc_providers')) {
			$table = $schema->createTable('vo_oidc_providers');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('identifier', Types::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('client_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('client_secret', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('discovery_endpoint', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('scope', Types::STRING, [
				'length' => 128,
				'default' => 'openid email profile',
				'notnull' => true,
			]);
			$table->addColumn('uid_claim', Types::STRING, [
				'length' => 128,
				'default' => 'uid',
				'notnull' => true,
			]);
			$table->addColumn('display_name_claim', Types::STRING, [
				'length' => 128,
				'default' => 'name',
				'notnull' => true,
			]);
			$table->addColumn('groups_claim', Types::STRING, [
				'length' => 128,
				'default' => 'groups',
				'notnull' => true,
			]);
			$table->addColumn('groups_regex', Types::STRING, [
				'length' => 255,
				'default' => '.*',
				'notnull' => true,
			]);
			$table->addColumn('settings', TYPES::JSON);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['identifier']);
		}

		if (!$schema->hasTable('vo_trusted_instances')) {
			$table = $schema->createTable('vo_trusted_instances');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('provider_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('instance_url', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['provider_id', 'instance_url']);
		}

		if (!$schema->hasTable('vo_oidc_sessions')) {
			$table = $schema->createTable('vo_oidc_sessions');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('provider_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			// https://openid.net/specs/openid-connect-core-1_0.html#IDToken
			$table->addColumn('id_token', Types::TEXT);
			$table->addColumn('id_token_sub', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('id_token_exp', Types::BIGINT);
			$table->addColumn('access_token', Types::TEXT);
			$table->addColumn('access_token_exp', Types::BIGINT);
			$table->addColumn('refresh_token', Types::TEXT);
			$table->addColumn('refresh_token_exp', Types::BIGINT);
			$table->addColumn('userinfo_display_name', Types::STRING, [
				'notnull' => false,
				'length' => 128,
			]);
			$table->addColumn('last_sync', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['uid', 'provider_id']);
		}
		
		return $schema;
	}
}
