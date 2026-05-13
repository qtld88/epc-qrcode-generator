<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Db;

use OCP\AppFramework\Db\Entity;

class Preset extends Entity {
	protected string $userId = '';
	protected string $name = '';
	protected string $styleOptions = '';
	protected ?string $logoFile = null;
	protected int $createdAt = 0;
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('userId', 'string');
		$this->addType('name', 'string');
		$this->addType('styleOptions', 'string');
		$this->addType('logoFile', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function toArray(): array {
		return [
			'id' => $this->getId(),
			'userId' => $this->getUserId(),
			'name' => $this->getName(),
			'styleOptions' => json_decode($this->getStyleOptions(), true) ?: [],
			'logoFile' => $this->getLogoFile(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}

	public function setStyleOptionsFromArray(array $options): void {
		$this->setStyleOptions(json_encode($options));
	}
}
