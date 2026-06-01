<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Db;

use OCP\AppFramework\Db\Entity;

class History extends Entity {
	protected string $userId = '';
	protected string $beneficiary = '';
	protected string $iban = '';
	protected string $amount = '';
	protected string $remittance = '';
	protected string $epcString = '';
	protected int $createdAt = 0;
	protected ?string $sharedGroup = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('beneficiary', 'string');
		$this->addType('iban', 'string');
		$this->addType('amount', 'string');
		$this->addType('remittance', 'string');
		$this->addType('epcString', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('sharedGroup', 'string');
	}

	/**
	 * Convert to array for JSON response
	 */
	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'beneficiary' => $this->getBeneficiary(),
			'iban' => $this->getIban(),
			'amount' => $this->getAmount(),
			'remittance' => $this->getRemittance(),
			'epcString' => $this->getEpcString(),
			'createdAt' => $this->getCreatedAt(),
			'sharedGroup' => $this->getSharedGroup(),
		];
	}
}
