<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\EPCQRCodeGenerator\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Controller for serving the main application page.
 *
 * Provides initial state and renders the EPC QR Code Generator frontend.
 */
class PageController extends Controller {
	private IInitialStateService $initialStateService;
	private IL10N $l10n;
	private IUserSession $userSession;
	private IURLGenerator $urlGenerator;
	private IConfig $config;

	/**
	 * @param IRequest $request The HTTP request
	 * @param IInitialStateService $initialStateService Service to pass initial data to the frontend
	 * @param IL10N $l10n Localisation service
	 * @param IUserSession $userSession The current user session
	 * @param IURLGenerator $urlGenerator URL generation service
	 * @param IConfig $config System configuration service
	 * @param string|null $appName Override app name (auto-resolved by DI container)
	 */
	public function __construct(
		IRequest $request,
		IInitialStateService $initialStateService,
		IL10N $l10n,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
		IConfig $config,
		?string $appName = null,
	) {
		parent::__construct($appName ?? 'epc_qrcode_generator', $request);

		$this->initialStateService = $initialStateService;
		$this->l10n = $l10n;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
	}

	/**
	 * Render the main application page.
	 *
	 * Provides initial state data (app version, current user ID) to the frontend
	 * and returns the main template response.
	 *
	 * @return TemplateResponse
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	public function index(): TemplateResponse {
		$user = $this->userSession->getUser();

		$this->initialStateService->provideInitialState(
			'epc_qrcode_generator',
			'initialState',
			[
				'appVersion' => '1.0.2',
				'userId' => $user?->getUID(),
			],
		);

		return new TemplateResponse('epc_qrcode_generator', 'main');
	}
}
