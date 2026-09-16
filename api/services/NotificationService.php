<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Repositories\NotificationRepository;

final class NotificationService
{
    /**
     * @param NotificationRepository $notifications
     */
    public function __construct(
        private readonly NotificationRepository $notifications
    ) {
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     */
    public function list(
        int $propertyId,
        int $userId,
        array $filters,
        int $limit,
        int $offset
    ): array {
        $result = $this->notifications->paginate(
            $propertyId,
            $userId,
            $filters,
            $limit,
            $offset
        );

        $result['items'] = array_map(static fn(array $item): array => [
             ...$item,
            'id' => (int) $item['id'],
            'is_read' => (int) $item['is_read'],
            'related_id' => $item['related_id'] !== null ? (int) $item['related_id'] : null
        ], $result['items']);

        $result['unread_count'] = $this->notifications->unreadCount($propertyId, $userId);

        return $result;
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $id
     */
    public function markRead(int $propertyId, int $userId, int $id): array
    {
        $notification = $this->notifications->find($propertyId, $userId, $id);

        if ($notification === null) {
            throw new NotFoundException('ไม่พบการแจ้งเตือน');
        }

        $this->notifications->markRead($id);

        return [
            'id' => $id,
            'unread_count' => $this->notifications->unreadCount($propertyId, $userId)
        ];
    }

    /**
     * @param int $propertyId
     * @param int $userId
     */
    public function markAllRead(int $propertyId, int $userId): array
    {
        $updated = $this->notifications->markAllRead($propertyId, $userId);

        return ['updated' => $updated, 'unread_count' => 0];
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $id
     */
    public function delete(int $propertyId, int $userId, int $id): void
    {
        if ($this->notifications->find($propertyId, $userId, $id) === null) {
            throw new NotFoundException('ไม่พบการแจ้งเตือน');
        }

        $this->notifications->delete($id);
    }

    /**
     * สร้างการแจ้งเตือนจากเหตุการณ์ในระบบ (เห็นได้ทุกผู้ใช้ในหอพัก)
     *
     * @param int $propertyId
     * @param string $type maintenance|payment|contract|room
     * @param string $title
     * @param string|null $description
     * @param string|null $relatedType
     * @param int|null $relatedId
     */
    public function notify(
        int $propertyId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): int {
        return $this->notifications->create($propertyId, [
            'user_id' => null,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'related_type' => $relatedType,
            'related_id' => $relatedId
        ]);
    }
}
