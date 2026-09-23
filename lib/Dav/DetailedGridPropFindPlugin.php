<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Dav;

use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\Node as SabreNode;
use OCA\FilesDetailedGridHzs\Service\FileMetadataService;
use OCP\Files\Folder;
use OCP\IUserSession;
use Sabre\DAV\ICollection;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class DetailedGridPropFindPlugin extends ServerPlugin {
	public const PROPERTY_UPLOADED_BY = '{http://nextcloud.org/ns}files-grid-uploaded-by';
	public const PROPERTY_MODIFIED_BY = '{http://nextcloud.org/ns}files-grid-modified-by';

	private ?Server $server = null;

	public function __construct(
		private FileMetadataService $metadataService,
		private IUserSession $userSession,
	) {
	}

	#[\Override]
	public function initialize(Server $server): void {
		$this->server = $server;
		$server->on('preloadCollection', $this->preloadCollection(...));
		$server->on('propFind', $this->propFind(...));
	}

	public function preloadCollection(PropFind $propFind, ICollection $collection): void {
		if (!$collection instanceof Directory) {
			return;
		}

		$requested = $propFind->getRequestedProperties();
		if (!in_array(self::PROPERTY_UPLOADED_BY, $requested, true)
			&& !in_array(self::PROPERTY_MODIFIED_BY, $requested, true)
		) {
			return;
		}

		$folder = $collection->getNode();
		if (!$folder instanceof Folder) {
			return;
		}

		// Resolve user with multiple fallbacks
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			try {
				$userId = \OC::$server->getUserSession()->getUser()?->getUID();
			} catch (\Throwable) {
			}
		}
		if ($userId === null && $this->server !== null) {
			try {
				$authPlugin = $this->server->getPlugin('auth');
				if ($authPlugin !== null) {
					$principal = $authPlugin->getCurrentPrincipal();
					if (is_string($principal) && str_starts_with($principal, 'principals/users/')) {
						$userId = substr($principal, strlen('principals/users/'));
					}
				}
			} catch (\Throwable) {
			}
		}
		if ($userId === null) {
			try {
				$userId = $folder->getOwner()?->getUID();
			} catch (\Throwable) {
			}
		}

		if ($userId !== null) {
			$details = [
				'path' => $folder->getPath(),
			];
			$this->metadataService->logAccess(
				$folder->getId(),
				'browse_folder',
				$userId,
				'folder',
				(string)$folder->getId(),
				json_encode($details, JSON_UNESCAPED_UNICODE)
			);
		}

		$ownerUid = null;
		try {
			$owner = $folder->getOwner();
			if ($owner !== null) {
				$ownerUid = $owner->getUID();
			}
		} catch (\Throwable) {
		}

		try {
			$nodes = $folder->getDirectoryListing();
			$fileIds = [];
			foreach ($nodes as $child) {
				$fileIds[] = $child->getId();
			}
			$this->metadataService->preload($fileIds, $ownerUid);
		} catch (\Throwable) {
		}
	}

	public function propFind(PropFind $propFind, INode $node): void {
		$requested = $propFind->getRequestedProperties();
		$wantsUploadedBy = in_array(self::PROPERTY_UPLOADED_BY, $requested, true);
		$wantsModifiedBy = in_array(self::PROPERTY_MODIFIED_BY, $requested, true);

		if (!$wantsUploadedBy && !$wantsModifiedBy) {
			return;
		}

		if (!$node instanceof SabreNode) {
			return;
		}

		try {
			$internalNode = $node->getNode();
			$meta = $this->metadataService->getMetadataForNode($internalNode);

			if ($wantsUploadedBy) {
				$propFind->handle(self::PROPERTY_UPLOADED_BY, function () use ($meta) {
					return $meta['uploadedBy'] !== null ? json_encode($meta['uploadedBy'], JSON_UNESCAPED_UNICODE) : '';
				});
			}

			if ($wantsModifiedBy) {
				$propFind->handle(self::PROPERTY_MODIFIED_BY, function () use ($meta) {
					return $meta['modifiedBy'] !== null ? json_encode($meta['modifiedBy'], JSON_UNESCAPED_UNICODE) : '';
				});
			}
		} catch (\Throwable) {
		}
	}
}
