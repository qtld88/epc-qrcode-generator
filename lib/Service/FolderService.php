<?php

declare(strict_types=1);

namespace OCA\EPCQRCodeGenerator\Service;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserSession;

class FolderService {
	private IRootFolder $rootFolder;
	private IUserSession $userSession;

	public function __construct(
		IRootFolder $rootFolder,
		IUserSession $userSession
	) {
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
	}

	/**
	 * List subdirectories at the given path for the current user.
	 *
	 * @param string $path Relative path from user's root (e.g., '' or 'Documents/Subfolder')
	 * @return array{folders: array<array{name: string, path: string}>}
	 */
	public function listFolders(string $path = ''): array {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return ['folders' => []];
		}

		$userFolder = $this->rootFolder->getUserFolder($user->getUID());
		$path = trim($path, '/');

		try {
			$current = $path === ''
				? $userFolder
				: $userFolder->get($path);

			if (!($current instanceof \OCP\Files\Folder)) {
				return ['folders' => []];
			}

			$folders = [];
			$nodes = $current->getDirectoryListing();
			foreach ($nodes as $node) {
				if ($node instanceof \OCP\Files\Folder) {
					$nodePath = $path === ''
						? $node->getName()
						: $path . '/' . $node->getName();
					$folders[] = [
						'name' => $node->getName(),
						'path' => $nodePath,
					];
				}
			}

			usort($folders, fn($a, $b) => strcmp($a['name'], $b['name']));

			return ['folders' => $folders];
		} catch (NotFoundException $e) {
			return ['folders' => []];
		}
	}
}
