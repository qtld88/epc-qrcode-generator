<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Controller;

use OCA\EPCQRCodeGenerator\Db\Preset;
use OCA\EPCQRCodeGenerator\Db\PresetMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\IUserManager;

class PresetController extends Controller {
	private PresetMapper $mapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	private IUserManager $userManager;

	public function __construct(
		IRequest $request,
		PresetMapper $mapper,
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

	private function normalizeSharedGroup(?string $group, string $userId): ?string {
		if ($group === null || $group === '') {
			return null;
		}
		return in_array($group, $this->getUserGroupIds($userId), true) ? $group : null;
	}

	private function enrich(Preset $entry, string $currentUserId): array {
		$data = $entry->toArray();
		$ownerId = $entry->getUserId();
		$owner = $this->userManager->get($ownerId);
		$data['ownerDisplayName'] = $owner !== null ? $owner->getDisplayName() : $ownerId;
		$data['isOwner'] = ($ownerId === $currentUserId);
		return $data;
	}

	#[PublicPage]
	public function index(): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$groupIds = $this->getUserGroupIds($userId);
		$entries = $this->mapper->findAllVisible($userId, $groupIds);
		$result = array_map(fn(Preset $entry) => $this->enrich($entry, $userId), $entries);

		return new JSONResponse($result);
	}

	#[PublicPage]
	public function show(int $id): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = $this->mapper->find($id);
		if ($entry === null) {
			return new JSONResponse(['error' => 'Preset not found'], 404);
		}

		return new JSONResponse($entry->toArray());
	}

	#[PublicPage]
	public function create(string $name, string $styleOptions, ?string $logoFile = null, ?string $sharedGroup = null): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		// Check if preset with same name exists
		$existing = $this->mapper->findByName($userId, $name);
		if ($existing !== null) {
			return new JSONResponse(['error' => 'Preset with this name already exists'], 409);
		}

		$now = time();
		$entry = new Preset();
		$entry->setUserId($userId);
		$entry->setName($name);
		$entry->setStyleOptions($styleOptions);
		$entry->setLogoFile($logoFile);
		$entry->setCreatedAt($now);
		$entry->setUpdatedAt($now);
		$entry->setSharedGroup($this->normalizeSharedGroup($sharedGroup, $userId));

		try {
			$inserted = $this->mapper->insert($entry);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => 'DB error: ' . $e->getMessage()], 500);
		}

		return new JSONResponse($this->enrich($inserted, $userId));
	}

	#[PublicPage]
	public function update(int $id, string $name, string $styleOptions, ?string $logoFile = null, ?string $sharedGroup = null): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = $this->mapper->find($id);
		if ($entry === null) {
			return new JSONResponse(['error' => 'Preset not found'], 404);
		}

		if ($entry->getUserId() !== $userId) {
			return new JSONResponse(['error' => 'Only the owner can edit this preset'], 403);
		}

		$entry->setName($name);
		$entry->setStyleOptions($styleOptions);
		$entry->setLogoFile($logoFile);
		$entry->setUpdatedAt(time());
		$entry->setSharedGroup($this->normalizeSharedGroup($sharedGroup, $userId));

		$updated = $this->mapper->update($entry);

		return new JSONResponse($this->enrich($updated, $userId));
	}

	#[PublicPage]
	public function destroy(int $id): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$deleted = $this->mapper->deleteById($id, $userId);
		if (!$deleted) {
			return new JSONResponse(['error' => 'Preset not found or access denied'], 404);
		}

		return new JSONResponse(['success' => true]);
	}
}
