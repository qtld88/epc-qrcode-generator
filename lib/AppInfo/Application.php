<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 EPCQRCodeGenerator
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\EPCQRCodeGenerator\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'epc_qrcode_generator';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Navigation is handled via info.xml <navigations>
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (AddContentSecurityPolicyEvent $event) {
			$policy = new ContentSecurityPolicy();

			// Allow blob: and data: URIs for canvas operations (qr-code-styling)
			$policy->addAllowedImageDomain('blob:');
			$policy->addAllowedImageDomain('data:');
			$policy->addAllowedConnectDomain('blob:');

			// Allow inline styles for qr-code-styling
			$policy->addAllowedStyleDomain('\'self\'');
			$policy->addAllowedScriptDomain('\'self\'');
			$policy->addAllowedFontDomain('data:');

			$event->addPolicy($policy);
		});

		$context->injectFn(function (LoggerInterface $logger) {
			$logger->info(self::APP_ID . ' app booted successfully');
		});
	}
}
