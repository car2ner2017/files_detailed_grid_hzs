<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\AppInfo;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\FilesDetailedGridHzs\Listener\FileAccessListener;
use OCA\FilesDetailedGridHzs\Listener\LoadAdditionalScriptsListener;
use OCA\FilesDetailedGridHzs\Listener\NodeCreatedListener;
use OCA\FilesDetailedGridHzs\Listener\NodeDeletedListener;
use OCA\FilesDetailedGridHzs\Listener\NodeWrittenListener;
use OCA\FilesDetailedGridHzs\Listener\SabrePluginAddListener;
use OCA\FilesDetailedGridHzs\Listener\ShareCreatedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Share\Events\ShareCreatedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_detailed_grid_hzs';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(SabrePluginAddEvent::class, SabrePluginAddListener::class);
		$context->registerEventListener(NodeCreatedEvent::class, NodeCreatedListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, NodeWrittenListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, NodeDeletedListener::class);
		$context->registerEventListener(ShareCreatedEvent::class, ShareCreatedListener::class);
		$context->registerEventListener(BeforeNodeReadEvent::class, FileAccessListener::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
	}
}
