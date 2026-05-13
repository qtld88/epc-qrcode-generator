<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1003Date20260512000003 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('epc_qr_presets')) {
			return null;
		}

		$table = $schema->getTable('epc_qr_presets');
		if (!$table->hasColumn('logo_file')) {
			return null;
		}

		$column = $table->getColumn('logo_file');
		if ($column->getType()->getName() === 'blob') {
			return null;
		}

		$column->setType(\Doctrine\DBAL\Types\Type::getType('blob'));
		$column->setNotnull(false);
		$column->setDefault(null);

		return $schema;
	}
}
