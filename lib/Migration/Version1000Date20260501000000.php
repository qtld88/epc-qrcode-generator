<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260501000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('epc_qr_history')) {
			$table = $schema->createTable('epc_qr_history');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('beneficiary', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('iban', 'string', [
				'notnull' => true,
				'length' => 34,
			]);
			$table->addColumn('amount', 'string', [
				'notnull' => false,
				'length' => 20,
				'default' => '',
			]);
			$table->addColumn('remittance', 'string', [
				'notnull' => false,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('epc_string', 'text', [
				'notnull' => true,
			]);
			$table->addColumn('created_at', 'bigint', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'epc_qr_history_user_id');
		}

		return $schema;
	}
}
