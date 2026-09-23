<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getFileid()
 * @method void setFileid(int $fileid)
 * @method string|null getUploadedBy()
 * @method void setUploadedBy(?string $uploadedBy)
 * @method string|null getLastModifiedBy()
 * @method void setLastModifiedBy(?string $lastModifiedBy)
 * @method int|null getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 * @method int|null getUpdatedAt()
 * @method void setUpdatedAt(?int $updatedAt)
 * @method bool getIsDeleted()
 * @method void setIsDeleted(bool $isDeleted)
 * @method int|null getDeletedAt()
 * @method void setDeletedAt(?int $deletedAt)
 */
class FileMetadata extends Entity implements JsonSerializable {
	protected ?int $fileid = null;
	protected ?string $uploadedBy = null;
	protected ?string $lastModifiedBy = null;
	protected ?int $createdAt = null;
	protected ?int $updatedAt = null;
	protected bool $isDeleted = false;
	protected ?int $deletedAt = null;

	public function __construct() {
		$this->addType('fileid', Types::INTEGER);
		$this->addType('uploaded_by', Types::STRING);
		$this->addType('last_modified_by', Types::STRING);
		$this->addType('created_at', Types::INTEGER);
		$this->addType('updated_at', Types::INTEGER);
		$this->addType('is_deleted', Types::BOOLEAN);
		$this->addType('deleted_at', Types::INTEGER);
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'fileid' => $this->fileid,
			'uploadedBy' => $this->uploadedBy,
			'lastModifiedBy' => $this->lastModifiedBy,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'isDeleted' => $this->isDeleted,
			'deletedAt' => $this->deletedAt,
		];
	}
}

