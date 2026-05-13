<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20260501000001 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('epc_qr_presets')) {
			$table = $schema->createTable('epc_qr_presets');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('style_options', 'text', [
				'notnull' => true,
				'comment' => 'JSON-encoded style options',
			]);
			$table->addColumn('logo_file', 'text', [
				'notnull' => false,
				'default' => null,
			]);
			$table->addColumn('created_at', 'bigint', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', 'bigint', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'epc_qr_presets_user_id');
			$table->addUniqueConstraint(['user_id', 'name'], 'epc_qr_presets_user_name');
		}

		return $schema;
	}
}
