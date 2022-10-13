<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

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
			$table->addColumn('aai', 'string', [
				'length' => 255,
				'default' => '',
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
