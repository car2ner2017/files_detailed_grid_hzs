<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20260923000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('hzs_files_detailed_grid')) {
			$table = $schema->createTable('hzs_files_detailed_grid');

			$table->addColumn('fileid', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('uploaded_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('last_modified_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
				'default' => null,
			]);

			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
				'default' => null,
			]);

			$table->setPrimaryKey(['fileid'], 'hzs_fdg_pk');
			$table->addIndex(['uploaded_by'], 'hzs_fdg_up_idx');
			$table->addIndex(['last_modified_by'], 'hzs_fdg_mod_idx');
		}

		return $schema;
	}
}

