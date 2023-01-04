<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

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
