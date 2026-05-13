<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Controller;

use OCA\EPCQRCodeGenerator\Db\History;
use OCA\EPCQRCodeGenerator\Db\HistoryMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class HistoryController extends Controller {
	private HistoryMapper $mapper;
	private IUserSession $userSession;

	public function __construct(
		IRequest $request,
		HistoryMapper $mapper,
		IUserSession $userSession
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
	}

	private function getUserId(): ?string {
		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : null;
	}

	/**
	 * List all history entries for the current user
	 */
	#[PublicPage]
	public function index(): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entries = $this->mapper->findAll($userId);
		$result = array_map(fn(History $entry) => $entry->toArray(), $entries);

		return new JSONResponse($result);
	}

	/**
	 * Get a single history entry
	 */
	#[PublicPage]
	public function show(int $id): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = $this->mapper->find($id);
		if ($entry === null) {
			return new JSONResponse(['error' => 'Entry not found'], 404);
		}

		return new JSONResponse($entry->toArray());
	}

	/**
	 * Create a new history entry
	 */
	#[PublicPage]
	public function create(string $beneficiary, string $iban, string $amount = '', string $remittance = '', string $epcString = '', int $createdAt = 0): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = new History();
		$entry->setUserId($userId);
		$entry->setBeneficiary($beneficiary);
		$entry->setIban($iban);
		$entry->setAmount($amount);
		$entry->setRemittance($remittance);
		$entry->setEpcString($epcString);
		$entry->setCreatedAt($createdAt ?: time());

		$inserted = $this->mapper->insert($entry);

		return new JSONResponse($inserted->toArray());
	}

	/**
	 * Delete a history entry
	 */
	#[PublicPage]
	public function destroy(int $id): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$deleted = $this->mapper->deleteById($id, $userId);
		if (!$deleted) {
			return new JSONResponse(['error' => 'Entry not found or access denied'], 404);
		}

		return new JSONResponse(['success' => true]);
	}
}
