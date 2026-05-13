<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Controller;

use OCA\EPCQRCodeGenerator\Service\FolderService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FolderController extends Controller {
	private FolderService $folderService;

	public function __construct(
		IRequest $request,
		FolderService $folderService
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->folderService = $folderService;
	}

	/**
	 * List subdirectories at the given path for the current user.
	 *
	 * @param string $path Relative path from user's root (e.g., '' or 'Documents/Subfolder')
	 * @return JSONResponse
	 */
	#[PublicPage]
	public function listFolders(string $path = ''): JSONResponse {
		try {
			$result = $this->folderService->listFolders($path);
			return new JSONResponse($result);
		} catch (\Exception $e) {
			return new JSONResponse([
				'success' => false,
				'error' => $e->getMessage(),
			], 500);
		}
	}
}
