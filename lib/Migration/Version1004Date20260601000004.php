<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1004Date20260601000004 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		foreach (['epc_qr_history', 'epc_qr_presets'] as $tableName) {
			if (!$schema->hasTable($tableName)) {
				continue;
			}
			$table = $schema->getTable($tableName);
			if (!$table->hasColumn('shared_group')) {
				$table->addColumn('shared_group', Types::STRING, [
					'notnull' => false,
					'length' => 64,
					'default' => null,
				]);
			}
			if (!$table->hasIndex('epc_qr_' . ($tableName === 'epc_qr_history' ? 'h' : 'p') . '_shgrp_idx')) {
				$table->addIndex(['shared_group'], 'epc_qr_' . ($tableName === 'epc_qr_history' ? 'h' : 'p') . '_shgrp_idx');
			}
		}

		return $schema;
	}
}
