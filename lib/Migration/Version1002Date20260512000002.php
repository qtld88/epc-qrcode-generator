<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1002Date20260512000002 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('epc_qr_presets')) {
			$table = $schema->getTable('epc_qr_presets');
			if ($table->hasColumn('logo_file') && $table->hasColumn('style_options')) {
				$column = $table->getColumn('logo_file');
				$textType = $table->getColumn('style_options')->getType();
				$type = $column->getType()->getName();

				if ($type !== 'text') {
					$column->setType($textType);
					$column->setNotnull(false);
					$column->setDefault(null);
				}
			}
		}

		return $schema;
	}
}
