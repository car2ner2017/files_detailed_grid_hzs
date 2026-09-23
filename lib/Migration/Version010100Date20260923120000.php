<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010100Date20260923120000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// 1. Update hzs_files_detailed_grid with soft delete fields
		if ($schema->hasTable('hzs_files_detailed_grid')) {
			$table = $schema->getTable('hzs_files_detailed_grid');

			if (!$table->hasColumn('is_deleted')) {
				$table->addColumn('is_deleted', Types::BOOLEAN, [
					'notnull' => true,
					'default' => false,
				]);
			}

			if (!$table->hasColumn('deleted_at')) {
				$table->addColumn('deleted_at', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
					'default' => null,
				]);
			}
		}

		// 2. Create hzs_files_access_log table for audit and event tracking
		if (!$schema->hasTable('hzs_files_access_log')) {
			$table = $schema->createTable('hzs_files_access_log');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('fileid', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->addColumn('action', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);

			$table->addColumn('user_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);

			$table->addColumn('target_type', Types::STRING, [
				'notnull' => false,
				'length' => 32,
			]);

			$table->addColumn('target_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('details', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);

			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id'], 'hzs_fal_pk');
			$table->addIndex(['fileid'], 'hzs_fal_file_idx');
			$table->addIndex(['action'], 'hzs_fal_act_idx');
			$table->addIndex(['user_id'], 'hzs_fal_user_idx');
			$table->addIndex(['timestamp'], 'hzs_fal_time_idx');
		}

		return $schema;
	}
}

