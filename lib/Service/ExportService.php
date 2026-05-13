<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Service;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ExportService {
	private IRootFolder $rootFolder;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRootFolder $rootFolder,
		IUserSession $userSession,
		LoggerInterface $logger
	) {
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Save a PNG image to the user's Files at the specified location
	 *
	 * @param string $pngData Base64-encoded PNG data
	 * @param string $filename Desired filename (without extension)
	 * @param string $targetFolder Target folder path (e.g., '/Documents' or '/')
	 * @return array{success: bool, path: ?string, error: ?string}
	 */
	public function savePngToFiles(string $pngData, string $filename = 'qr-epc', string $targetFolder = '/'): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return [
				'success' => false,
				'path' => null,
				'error' => 'User not logged in',
			];
		}

		$uid = $user->getUID();
		$userFolder = $this->rootFolder->getUserFolder($uid);

		// Navigate to the target folder, creating intermediate dirs if needed
		$targetFolder = trim($targetFolder, '/');
		$current = $userFolder;
		if ($targetFolder !== '') {
			$segments = explode('/', $targetFolder);
			foreach ($segments as $segment) {
				if ($segment === '') continue;
				try {
					$node = $current->get($segment);
					if ($node instanceof \OCP\Files\Folder) {
						$current = $node;
					} else {
						return [
							'success' => false,
							'path' => null,
							'error' => "Cannot navigate — '{$segment}' is not a folder",
						];
					}
				} catch (\OCP\Files\NotFoundException) {
					$current = $current->newFolder($segment);
				}
			}
		}

		// Decode the base64 data
		if (str_starts_with($pngData, 'data:image/png;base64,')) {
			$pngData = substr($pngData, strlen('data:image/png;base64,'));
		}
		$binaryData = base64_decode($pngData, true);
		if ($binaryData === false) {
			return [
				'success' => false,
				'path' => null,
				'error' => 'Invalid base64 data',
			];
		}

		// Determine final filename
		$baseName = $filename ?: 'qr-epc';
		$finalFilename = $baseName . '.png';

		// Handle duplicates
		$counter = 1;
		while ($current->nodeExists($finalFilename)) {
			$finalFilename = sprintf('%s_%d.png', $baseName, $counter);
			$counter++;
		}

		try {
			$file = $current->newFile($finalFilename);
			$file->putContent($binaryData);

			$this->logger->info('EPC QR Code saved to Files: ' . $finalFilename);

			return [
				'success' => true,
				'path' => ($targetFolder ? $targetFolder . '/' : '') . $finalFilename,
				'error' => null,
			];
		} catch (\Exception $e) {
			$this->logger->error('Failed to save EPC QR Code to Files: ' . $e->getMessage());
			return [
				'success' => false,
				'path' => null,
				'error' => $e->getMessage(),
			];
		}
	}
}
