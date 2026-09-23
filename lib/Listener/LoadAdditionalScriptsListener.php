<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesDetailedGridHzs\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	public function __construct(
		private IAppManager $appManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		if (!$this->appManager->isEnabledForUser(Application::APP_ID)) {
			return;
		}

		Util::addInitScript(Application::APP_ID, Application::APP_ID . '-files-init');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-files-init');
	}
}
