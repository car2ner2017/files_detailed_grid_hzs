<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Listener;

use OCA\FilesDetailedGridHzs\Service\FileMetadataService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<NodeCreatedEvent>
 */
class NodeCreatedListener implements IEventListener {
	public function __construct(
		private FileMetadataService $service,
		private IUserSession $userSession,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof NodeCreatedEvent)) {
			return;
		}

		$node = $event->getNode();
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			try {
				$userId = \OC::$server->getUserSession()->getUser()?->getUID();
			} catch (\Throwable) {
			}
		}
		if ($userId === null) {
			try {
				$userId = $node->getOwner()?->getUID();
			} catch (\Throwable) {
			}
		}

		if ($userId !== null) {
			$this->service->recordCreated($node->getId(), $userId);
		}
	}
}
