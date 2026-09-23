<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<AccessLog>
 */
class AccessLogMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'hzs_files_access_log', AccessLog::class);
	}

	public function log(
		int $fileid,
		string $action,
		?string $userId,
		?string $targetType = null,
		?string $targetId = null,
		?string $details = null,
		?int $timestamp = null
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'fileid' => $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT),
				'action' => $qb->createNamedParameter($action),
				'user_id' => $qb->createNamedParameter($userId),
				'target_type' => $qb->createNamedParameter($targetType),
				'target_id' => $qb->createNamedParameter($targetId),
				'details' => $qb->createNamedParameter($details),
				'timestamp' => $qb->createNamedParameter($timestamp ?? time(), IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	/**
	 * Checks if an event with the same fileid, action, and user_id was logged recently within $windowSeconds.
	 */
	public function isRecentDuplicate(int $fileid, string $action, ?string $userId, int $windowSeconds): bool {
		$qb = $this->db->getQueryBuilder();
		$threshold = time() - $windowSeconds;

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)))
			->andWhere($qb->expr()->gte('timestamp', $qb->createNamedParameter($threshold, IQueryBuilder::PARAM_INT)));

		if ($userId !== null) {
			$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere($qb->expr()->isNull('user_id'));
		}

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}

	/**
	 * @return AccessLog[]
	 */
	public function findByFileId(int $fileid, int $limit = 50): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT)))
			->orderBy('timestamp', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}
}
