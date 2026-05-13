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

class PresetController extends Controller {
	private PresetMapper $mapper;
	private IUserSession $userSession;

	public function __construct(
		IRequest $request,
		PresetMapper $mapper,
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

	#[PublicPage]
	public function index(): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entries = $this->mapper->findAll($userId);
		$result = array_map(fn(Preset $entry) => $entry->toArray(), $entries);

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
	public function create(string $name, string $styleOptions, ?string $logoFile = null): JSONResponse {
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

		try {
			$inserted = $this->mapper->insert($entry);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => 'DB error: ' . $e->getMessage()], 500);
		}

		return new JSONResponse($inserted->toArray());
	}

	#[PublicPage]
	public function update(int $id, string $name, string $styleOptions, ?string $logoFile = null): JSONResponse {
		$userId = $this->getUserId();
		if ($userId === null) {
			return new JSONResponse(['error' => 'Not authenticated'], 401);
		}

		$entry = $this->mapper->find($id);
		if ($entry === null) {
			return new JSONResponse(['error' => 'Preset not found'], 404);
		}

		$entry->setName($name);
		$entry->setStyleOptions($styleOptions);
		$entry->setLogoFile($logoFile);
		$entry->setUpdatedAt(time());

		$updated = $this->mapper->update($entry);

		return new JSONResponse($updated->toArray());
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
