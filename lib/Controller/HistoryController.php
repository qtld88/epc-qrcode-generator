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
use OCP\IGroupManager;
use OCP\IUserManager;

class HistoryController extends Controller {
	private HistoryMapper $mapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private IUserManager $userManager;

	public function __construct(
		IRequest $request,
		HistoryMapper $mapper,
		IUserSession $userSession,
		IGroupManager $groupManager,
		IUserManager $userManager
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
	}

	private function getUserId(): ?string {
		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : null;
	}

	private function getUserGroupIds(string $userId): array {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return [];
		}
		return $this->groupManager->getUserGroupIds($user);
	}

	private function enrich(History $entry, string $currentUserId): array {
		$data = $entry->toArray();
		$ownerId = $entry->getUserId();
		$owner = $this->userManager->get($ownerId);
		$data['ownerDisplayName'] = $owner !== null ? $owner->getDisplayName() : $ownerId;
		$data['isOwner'] = ($ownerId === $currentUserId);
		return $data;
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

		$groupIds = $this->getUserGroupIds($userId);
		$entries = $this->mapper->findAllVisible($userId, $groupIds);
		$result = array_map(fn(History $entry) => $this->enrich($entry, $userId), $entries);

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
	 * Share / un-share a history entry with one group. Owner only.
	 * Pass group = null (or empty) to make it private again.
	 */
	#[PublicPage]
	public function share(int $id, ?string $group = null): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = $this->mapper->find($id);
		if ($entry === null) {
			return new JSONResponse(['error' => 'Entry not found'], 404);
		}
		if ($entry->getUserId() !== $userId) {
			return new JSONResponse(['error' => 'Only the owner can change sharing'], 403);
		}

		$target = ($group === null || $group === '') ? null : $group;
		if ($target !== null) {
			$groupIds = $this->getUserGroupIds($userId);
			if (!in_array($target, $groupIds, true)) {
				return new JSONResponse(['error' => 'You are not a member of that group'], 403);
			}
		}

		$entry->setSharedGroup($target);
		$updated = $this->mapper->update($entry);

		return new JSONResponse($this->enrich($updated, $userId));
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
