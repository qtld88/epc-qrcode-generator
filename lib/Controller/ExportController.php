<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Controller;

use OCA\EPCQRCodeGenerator\Service\ExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ExportController extends Controller {
	private ExportService $exportService;

	public function __construct(
		IRequest $request,
		ExportService $exportService
	) {
		parent::__construct('epc_qrcode_generator', $request);
		$this->exportService = $exportService;
	}

	/**
	 * Save QR code PNG to user's Files
	 *
	 * @param string $pngData Base64-encoded PNG data
	 * @param string $filename Desired filename
	 * @return JSONResponse
	 */
	#[PublicPage]
	public function saveToFiles(string $pngData, string $filename = 'qr-epc', string $targetFolder = '/'): JSONResponse {
		$result = $this->exportService->savePngToFiles($pngData, $filename, $targetFolder);

		if ($result['success']) {
			return new JSONResponse([
				'success' => true,
				'path' => $result['path'],
			]);
		}

		return new JSONResponse([
			'success' => false,
			'error' => $result['error'],
		], 500);
	}
}
