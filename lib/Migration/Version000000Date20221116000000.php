<?php

declare(strict_types=1);

namespace OCA\VO_Federation\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000000Date20221116000000 extends SimpleMigrationStep {

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
				'notnull' => true,
			]);
			$table->addColumn('parent', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addUniqueIndex(['id', 'parent']);
		}
		
		return $schema;
	}
}
