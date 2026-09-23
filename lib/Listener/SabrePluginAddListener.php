<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Listener;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\FilesDetailedGridHzs\Dav\DetailedGridPropFindPlugin;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Container\ContainerInterface;

/**
 * @template-implements IEventListener<SabrePluginAddEvent>
 */
class SabrePluginAddListener implements IEventListener {
	public function __construct(
		private ContainerInterface $container,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof SabrePluginAddEvent)) {
			return;
		}

		$server = $event->getServer();
		$plugin = $this->container->get(DetailedGridPropFindPlugin::class);
		$server->addPlugin($plugin);
	}
}

