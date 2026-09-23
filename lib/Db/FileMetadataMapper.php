<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FileMetadata>
 */
class FileMetadataMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'hzs_files_detailed_grid', FileMetadata::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $fileid): FileMetadata {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function findByFileId(int $fileid): ?FileMetadata {
		try {
			return $this->find($fileid);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @param int[] $fileids
	 * @return array<int, FileMetadata>
	 */
	public function findByFileIds(array $fileids): array {
		if (empty($fileids)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('fileid', $qb->createNamedParameter($fileids, IQueryBuilder::PARAM_INT_ARRAY)));

		$entities = $this->findEntities($qb);
		$result = [];
		foreach ($entities as $entity) {
			$result[$entity->getFileid()] = $entity;
		}

		return $result;
	}

	public function recordCreated(int $fileid, string $userId, int $timestamp): void {
		$existing = $this->findByFileId($fileid);
		if ($existing === null) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert($this->getTableName())
				->values([
					'fileid' => $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT),
					'uploaded_by' => $qb->createNamedParameter($userId),
					'created_at' => $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
					'last_modified_by' => $qb->createNamedParameter($userId),
					'updated_at' => $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
				]);
			$qb->executeStatement();
		} else {
			$qb = $this->db->getQueryBuilder();
			$qb->update($this->getTableName())
				->set('uploaded_by', $qb->createNamedParameter($userId))
				->set('created_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
		}
	}

	public function recordModified(int $fileid, string $userId, int $timestamp): void {
		$existing = $this->findByFileId($fileid);
		if ($existing === null) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert($this->getTableName())
				->values([
					'fileid' => $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT),
					'last_modified_by' => $qb->createNamedParameter($userId),
					'updated_at' => $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
				]);
			$qb->executeStatement();
		} else {
			$qb = $this->db->getQueryBuilder();
			$qb->update($this->getTableName())
				->set('last_modified_by', $qb->createNamedParameter($userId))
				->set('updated_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
		}
	}

	public function markDeleted(int $fileid, int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_deleted', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('deleted_at', $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('fileid', $qb->createNamedParameter($fileid, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByFileId(int $fileid): void {
		// Soft delete: never physically delete metadata records from the database
		$this->markDeleted($fileid, time());
	}
}

