<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getFileid()
 * @method void setFileid(int $fileid)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method string|null getUserId()
 * @method void setUserId(?string $userId)
 * @method string|null getTargetType()
 * @method void setTargetType(?string $targetType)
 * @method string|null getTargetId()
 * @method void setTargetId(?string $targetId)
 * @method string|null getDetails()
 * @method void setDetails(?string $details)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 */
class AccessLog extends Entity implements JsonSerializable {
	protected ?int $fileid = null;
	protected ?string $action = null;
	protected ?string $userId = null;
	protected ?string $targetType = null;
	protected ?string $targetId = null;
	protected ?string $details = null;
	protected ?int $timestamp = null;

	public function __construct() {
		$this->addType('fileid', Types::INTEGER);
		$this->addType('action', Types::STRING);
		$this->addType('user_id', Types::STRING);
		$this->addType('target_type', Types::STRING);
		$this->addType('target_id', Types::STRING);
		$this->addType('details', Types::TEXT);
		$this->addType('timestamp', Types::INTEGER);
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'fileid' => $this->fileid,
			'action' => $this->action,
			'userId' => $this->userId,
			'targetType' => $this->targetType,
			'targetId' => $this->targetId,
			'details' => $this->details,
			'timestamp' => $this->timestamp,
		];
	}
}

