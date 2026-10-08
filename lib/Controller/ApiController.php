<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Controller;

use OCA\EPCQRCodeGenerator\Db\History;
use OCA\EPCQRCodeGenerator\Db\HistoryMapper;
use OCA\EPCQRCodeGenerator\Service\ExportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * OCS API for scripts and automations (Basic Auth + app password).
 * OCS routes skip the CSRF check when the client sends "OCS-APIRequest: true",
 * so the browser routes in the other controllers keep their CSRF protection.
 */
class ApiController extends OCSController {
	private HistoryMapper $mapper;
	private ExportService $exportService;
	private IUserSession $userSession;
	private IGroupManager $groupManager;

	public function __construct(
		IRequest $request,
		HistoryMapper $mapper,
		ExportService $exportService,
		IUserSession $userSession,
		IGroupManager $groupManager
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->mapper = $mapper;
		$this->exportService = $exportService;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	 * List the history entries visible to the current user
	 */
	#[NoAdminRequired]
	public function listHistory(): DataResponse {
		$user = $this->userSession->getUser();
		$groupIds = $this->groupManager->getUserGroupIds($user);
		$entries = $this->mapper->findAllVisible($user->getUID(), $groupIds);

		return new DataResponse(array_map(fn (History $entry) => $entry->toArray(), $entries));
	}

	/**
	 * Create a history entry
	 */
	#[NoAdminRequired]
	public function createHistory(string $beneficiary, string $iban, string $amount = '', string $remittance = '', string $epcString = '', int $createdAt = 0): DataResponse {
		$entry = new History();
		$entry->setUserId($this->userSession->getUser()->getUID());
		$entry->setBeneficiary($beneficiary);
		$entry->setIban($iban);
		$entry->setAmount($amount);
		$entry->setRemittance($remittance);
		$entry->setEpcString($epcString);
		$entry->setCreatedAt($createdAt ?: time());

		return new DataResponse($this->mapper->insert($entry)->toArray(), Http::STATUS_CREATED);
	}

	/**
	 * Save a base64 PNG to the current user's Files
	 */
	#[NoAdminRequired]
	public function saveToFiles(string $pngData, string $filename = 'qr-epc', string $targetFolder = '/'): DataResponse {
		$result = $this->exportService->savePngToFiles($pngData, $filename, $targetFolder);
		if (!$result['success']) {
			return new DataResponse(['error' => $result['error']], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['path' => $result['path']], Http::STATUS_CREATED);
	}
}
