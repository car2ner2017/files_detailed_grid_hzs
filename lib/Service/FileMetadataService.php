<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Service;

use OCA\FilesDetailedGridHzs\Db\AccessLogMapper;
use OCA\FilesDetailedGridHzs\Db\FileMetadataMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Node;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class FileMetadataService {
	/** @var array<string, array{displayName: string, email: string}> Cache of userId => user data */
	private array $userCache = [];

	/** @var array<int, array{uploadedBy: ?array{uid: string, displayName: string, email: string}, modifiedBy: ?array{uid: string, displayName: string, email: string}}> */
	private array $resolvedCache = [];

	/** @var array<string, bool> In-memory request debounce cache */
	private array $requestDebounceCache = [];

	private ICache $cache;

	public function __construct(
		private FileMetadataMapper $mapper,
		private AccessLogMapper $accessLogMapper,
		private IDBConnection $db,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed('files_detailed_grid_hzs');
	}

	/**
	 * Preload metadata for a list of file IDs to avoid N+1 queries during PROPFIND.
	 *
	 * @param int[] $fileIds
	 */
	public function preload(array $fileIds, ?string $fallbackOwner = null): void {
		$toFetch = array_diff($fileIds, array_keys($this->resolvedCache));
		if (empty($toFetch)) {
			return;
		}

		$dbEntities = $this->mapper->findByFileIds($toFetch);

		foreach ($toFetch as $fileId) {
			$entity = $dbEntities[$fileId] ?? null;

			$uploadedBy = $entity?->getUploadedBy();
			$modifiedBy = $entity?->getLastModifiedBy();

			if ($uploadedBy === null) {
				$uploadedBy = $this->resolveFallbackUploader($fileId, $fallbackOwner);
			}

			if ($modifiedBy === null) {
				$modifiedBy = $this->resolveFallbackModifier($fileId, $uploadedBy);
			}

			$this->resolvedCache[$fileId] = [
				'uploadedBy' => $this->formatUserInfo($uploadedBy),
				'modifiedBy' => $this->formatUserInfo($modifiedBy),
			];
		}
	}

	/**
	 * Get resolved metadata for a single node.
	 *
	 * @return array{uploadedBy: ?array{uid: string, displayName: string, email: string}, modifiedBy: ?array{uid: string, displayName: string, email: string}}
	 */
	public function getMetadataForNode(Node $node): array {
		$fileId = $node->getId();
		if (isset($this->resolvedCache[$fileId])) {
			return $this->resolvedCache[$fileId];
		}

		$fallbackOwner = null;
		try {
			$owner = $node->getOwner();
			if ($owner !== null) {
				$fallbackOwner = $owner->getUID();
			}
		} catch (\Throwable) {
		}

		$this->preload([$fileId], $fallbackOwner);
		return $this->resolvedCache[$fileId];
	}

	public function recordCreated(int $fileid, string $userId): void {
		try {
			$this->mapper->recordCreated($fileid, $userId, time());
			$this->logAccess($fileid, 'create', $userId, 'file', (string)$fileid, json_encode(['source' => 'webdav'], JSON_UNESCAPED_UNICODE));
			unset($this->resolvedCache[$fileid]);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to record file creation: ' . $e->getMessage(), ['app' => 'files_detailed_grid_hzs']);
		}
	}

	public function recordModified(int $fileid, string $userId): void {
		try {
			$this->mapper->recordModified($fileid, $userId, time());
			$this->logAccess($fileid, 'modify', $userId, 'file', (string)$fileid, json_encode(['source' => 'webdav'], JSON_UNESCAPED_UNICODE));
			unset($this->resolvedCache[$fileid]);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to record file modification: ' . $e->getMessage(), ['app' => 'files_detailed_grid_hzs']);
		}
	}

	public function recordDeleted(int $fileid, ?string $userId = null): void {
		try {
			// Soft delete: never delete historical records from the database
			$this->mapper->markDeleted($fileid, time());
			$this->logAccess($fileid, 'delete', $userId, 'file', (string)$fileid, json_encode(['source' => 'webdav'], JSON_UNESCAPED_UNICODE));
			unset($this->resolvedCache[$fileid]);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to mark file deleted: ' . $e->getMessage(), ['app' => 'files_detailed_grid_hzs']);
		}
	}

	public function logAccess(
		int $fileid,
		string $action,
		?string $userId,
		?string $targetType = null,
		?string $targetId = null,
		?string $details = null
	): void {
		// Debounce window in seconds for repeated access events
		$debounceWindows = [
			'open_file' => 60,
			'download_file' => 30,
			'download_folder' => 30,
			'browse_folder' => 30,
		];

		$window = $debounceWindows[$action] ?? null;
		if ($window !== null) {
			$memKey = "{$action}_{$fileid}_" . ($userId ?? 'anon');
			if (isset($this->requestDebounceCache[$memKey])) {
				return;
			}
			$this->requestDebounceCache[$memKey] = true;

			$cacheKey = "debounce_{$memKey}";
			$cached = $this->cache->get($cacheKey);
			if ($cached !== null && $cached !== false) {
				return;
			}

			// Check database for recent duplicate within debounce window
			if ($this->accessLogMapper->isRecentDuplicate($fileid, $action, $userId, $window)) {
				$this->cache->set($cacheKey, '1', $window);
				return;
			}

			$this->cache->set($cacheKey, '1', $window);
		}

		try {
			$this->accessLogMapper->log($fileid, $action, $userId, $targetType, $targetId, $details);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to log file access: ' . $e->getMessage(), ['app' => 'files_detailed_grid_hzs']);
		}
	}

	private function resolveFallbackUploader(int $fileId, ?string $fallbackOwner): ?string {
		// 1. Try oc_activity for file creation
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('user')
				->from('activity')
				->where($qb->expr()->eq('object_type', $qb->createNamedParameter('files')))
				->andWhere($qb->expr()->eq('object_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->in('type', $qb->createNamedParameter([
					'file_created', 'created_by', 'created_self', 'created_public',
				], IQueryBuilder::PARAM_STR_ARRAY)))
				->orderBy('timestamp', 'ASC')
				->setMaxResults(1);

			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();

			if ($row && !empty($row['user'])) {
				return (string)$row['user'];
			}
		} catch (\Throwable) {
		}

		// 2. Fallback to owner if available
		return $fallbackOwner;
	}

	private function resolveFallbackModifier(int $fileId, ?string $fallbackUploader): ?string {
		// 1. Try oc_files_versions for the latest version author
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('metadata')
				->from('files_versions')
				->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
				->orderBy('timestamp', 'DESC')
				->setMaxResults(1);

			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();

			if ($row && !empty($row['metadata'])) {
				$meta = json_decode((string)$row['metadata'], true);
				if (is_array($meta) && !empty($meta['author'])) {
					return (string)$meta['author'];
				}
			}
		} catch (\Throwable) {
		}

		// 2. Try oc_activity for file changed
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('user')
				->from('activity')
				->where($qb->expr()->eq('object_type', $qb->createNamedParameter('files')))
				->andWhere($qb->expr()->eq('object_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->in('type', $qb->createNamedParameter([
					'file_changed', 'changed_by', 'changed_self',
				], IQueryBuilder::PARAM_STR_ARRAY)))
				->orderBy('timestamp', 'DESC')
				->setMaxResults(1);

			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();

			if ($row && !empty($row['user'])) {
				return (string)$row['user'];
			}
		} catch (\Throwable) {
		}

		// 3. Fallback to uploader
		return $fallbackUploader;
	}

	/**
	 * @return array{uid: string, displayName: string, email: string}|null
	 */
	private function formatUserInfo(?string $userId): ?array {
		if ($userId === null || $userId === '') {
			return null;
		}

		if (!isset($this->userCache[$userId])) {
			$user = $this->userManager->get($userId);
			$this->userCache[$userId] = [
				'displayName' => $user !== null ? $user->getDisplayName() : $userId,
				'email' => $user !== null ? (string)$user->getEMailAddress() : '',
			];
		}

		return [
			'uid' => $userId,
			'displayName' => $this->userCache[$userId]['displayName'],
			'email' => $this->userCache[$userId]['email'],
		];
	}
}
