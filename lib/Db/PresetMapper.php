<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PresetMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'epc_qr_presets', Preset::class);
	}

	public function findAll(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_presets')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Own presets plus presets shared to any of the given groups.
	 *
	 * @param string $userId
	 * @param string[] $groupIds
	 * @return Preset[]
	 */
	public function findAllVisible(string $userId, array $groupIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_presets');

		$ownExpr = $qb->expr()->eq('user_id', $qb->createNamedParameter($userId));
		if (count($groupIds) > 0) {
			$sharedExpr = $qb->expr()->andX(
				$qb->expr()->isNotNull('shared_group'),
				$qb->expr()->in('shared_group', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY)),
			);
			$qb->where($qb->expr()->orX($ownExpr, $sharedExpr));
		} else {
			$qb->where($ownExpr);
		}

		$qb->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function find(int $id): ?Preset {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_presets')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		}
	}

	public function findByName(string $userId, string $name): ?Preset {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_presets')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		} catch (\OCP\AppFramework\Db\MultipleObjectsReturnedException) {
			return $this->findEntities($qb)[0] ?? null;
		}
	}

	public function deleteById(int $id, string $userId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('epc_qr_presets')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $qb->executeStatement() > 0;
	}
}
