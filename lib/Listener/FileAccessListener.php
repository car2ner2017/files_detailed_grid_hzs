<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Listener;

use OCA\FilesDetailedGridHzs\Service\FileMetadataService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<BeforeNodeReadEvent>
 */
class FileAccessListener implements IEventListener {
	public function __construct(
		private FileMetadataService $service,
		private IUserSession $userSession,
		private IRequest $request,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeNodeReadEvent)) {
			return;
		}

		$node = $event->getNode();
		if (!($node instanceof Node)) {
			return;
		}

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

		$uri = $this->request->getRequestUri();
		$userAgent = (string)$this->request->getHeader('User-Agent');

		// Exclude background polling and autosave endpoints from triggering open_file
		if (str_contains($uri, '/apps/text/session/') && (
			str_contains($uri, '/sync')
			|| str_contains($uri, '/save')
			|| str_contains($uri, '/push')
			|| str_contains($uri, '/pull')
			|| str_contains($uri, '/close')
		)) {
			return;
		}

		if (str_contains($uri, '/apps/onlyoffice/track')
			|| (str_contains($uri, '/wopi/files/') && str_contains($uri, '/contents') && $this->request->getMethod() !== 'GET')
		) {
			return;
		}

		// Distinguish between open_file (viewer, editor, onlyoffice/collabora/text) and download
		$isViewerOrEditor = str_contains($uri, '/apps/viewer')
			|| str_contains($uri, '/apps/onlyoffice')
			|| str_contains($uri, '/apps/richdocuments')
			|| str_contains($uri, '/apps/text')
			|| str_contains($uri, '/wopi/')
			|| str_contains($uri, '/directEditing');

		$isFolder = $node instanceof Folder;
		$action = $isFolder
			? 'download_folder'
			: ($isViewerOrEditor ? 'open_file' : 'download_file');

		$details = [
			'path' => $node->getPath(),
			'uri' => $uri,
			'userAgent' => $userAgent,
			'isViewerOrEditor' => $isViewerOrEditor,
		];

		$this->service->logAccess(
			$node->getId(),
			$action,
			$userId,
			$isFolder ? 'folder' : 'file',
			(string)$node->getId(),
			json_encode($details, JSON_UNESCAPED_UNICODE)
		);
	}
}
