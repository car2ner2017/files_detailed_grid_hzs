<?php

declare(strict_types=1);

namespace OCA\FilesDetailedGridHzs\Listener;

use OCA\FilesDetailedGridHzs\Service\FileMetadataService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\IManager as IGroupManager;
use OCP\Share\Events\ShareCreatedEvent;

/**
 * @template-implements IEventListener<ShareCreatedEvent>
 */
class ShareCreatedListener implements IEventListener {
	public function __construct(
		private FileMetadataService $service,
		private IGroupManager $groupManager,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof ShareCreatedEvent)) {
			return;
		}

		$share = $event->getShare();
		$fileId = $share->getNodeId();
		$owner = $share->getShareOwner();
		$shareType = $share->getShareType();
		$sharedWith = $share->getSharedWith();

		$targetType = match ($shareType) {
			\OCP\Share::SHARE_TYPE_USER => 'user',
			\OCP\Share::SHARE_TYPE_GROUP => 'group',
			\OCP\Share::SHARE_TYPE_LINK => 'link',
			\OCP\Share::SHARE_TYPE_EMAIL => 'email',
			\OCP\Share::SHARE_TYPE_CIRCLE => 'circle',
			default => 'other',
		};

		$action = $shareType === \OCP\Share::SHARE_TYPE_GROUP ? 'share_group' : 'share_user';

		$details = [
			'shareType' => $shareType,
			'targetType' => $targetType,
			'sharedWith' => $sharedWith,
		];

		// If shared with a group, record group name and snapshot of all group members
		if ($shareType === \OCP\Share::SHARE_TYPE_GROUP && $sharedWith !== null && $sharedWith !== '') {
			$group = $this->groupManager->get($sharedWith);
			$members = [];
			if ($group !== null) {
				$users = $group->getUsers();
				foreach ($users as $user) {
					$members[] = [
						'uid' => $user->getUID(),
						'displayName' => $user->getDisplayName(),
					];
				}
			}
			$details['groupName'] = $sharedWith;
			$details['groupMembersCount'] = count($members);
			$details['groupMembers'] = $members;
		}

		$this->service->logAccess(
			$fileId,
			$action,
			$owner,
			$targetType,
			$sharedWith,
			json_encode($details, JSON_UNESCAPED_UNICODE)
		);
	}
}
