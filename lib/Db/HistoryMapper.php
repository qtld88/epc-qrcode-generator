<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class HistoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'epc_qr_history', History::class);
	}

	/**
	 * Get all history entries for a user, ordered by creation date descending
	 *
	 * @param string $userId
	 * @return History[]
	 */
	public function findAll(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_history')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Find a single history entry by ID
	 *
	 * @param int $id
	 * @return History|null
	 */
	public function find(int $id): ?History {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('epc_qr_history')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Delete a history entry
	 *
	 * @param int $id
	 * @param string $userId For ownership verification
	 * @return bool
	 */
	public function deleteById(int $id, string $userId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('epc_qr_history')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $qb->executeStatement() > 0;
	}
}
