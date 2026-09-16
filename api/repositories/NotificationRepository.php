<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class NotificationRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param array $filters unread (1), type
     * @param int $limit
     * @param int $offset
     * @return array{items: array, total: int}
     */
    public function paginate(
        int $propertyId,
        int $userId,
        array $filters,
        int $limit,
        int $offset
    ): array {
        $where = ['property_id = :property_id', '(user_id = :user_id OR user_id IS NULL)'];
        $bindings = ['property_id' => $propertyId, 'user_id' => $userId];

        if (($filters['unread'] ?? '') !== '') {
            $where[] = 'is_read = 0';
        }

        if (($filters['type'] ?? '') !== '') {
            $where[] = 'type = :type';
            $bindings['type'] = $filters['type'];
        }

        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT id, type, title, description, related_type, related_id,
                    is_read, created_at, read_at
             FROM notifications
             WHERE {$whereSql}
             ORDER BY datetime(created_at) DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(':'.$key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $id
     * @return mixed
     */
    public function find(int $propertyId, int $userId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM notifications
             WHERE property_id = :property_id
               AND id = :id
               AND (user_id = :user_id OR user_id IS NULL)
             LIMIT 1'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'id' => $id,
            'user_id' => $userId
        ]);

        $notification = $statement->fetch();

        return $notification ?: null;
    }

    /**
     * @param int $propertyId
     * @param array $data type, title, description, related_type, related_id, user_id
     */
    public function create(int $propertyId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (
                property_id, user_id, type, title, description,
                related_type, related_id
             ) VALUES (
                :property_id, :user_id, :type, :title, :description,
                :related_type, :related_id
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'user_id' => $data['user_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $id
     */
    public function markRead(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = datetime(\'now\', \'localtime\')
             WHERE id = :id AND is_read = 0'
        );

        $statement->execute(['id' => $id]);
    }

    /**
     * @param int $propertyId
     * @param int $userId
     */
    public function markAllRead(int $propertyId, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = datetime(\'now\', \'localtime\')
             WHERE property_id = :property_id
               AND (user_id = :user_id OR user_id IS NULL)
               AND is_read = 0'
        );

        $statement->execute(['property_id' => $propertyId, 'user_id' => $userId]);

        return $statement->rowCount();
    }

    /**
     * @param int $id
     */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM notifications WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param int $propertyId
     * @param int $userId
     */
    public function unreadCount(int $propertyId, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE property_id = :property_id
               AND (user_id = :user_id OR user_id IS NULL)
               AND is_read = 0'
        );

        $statement->execute(['property_id' => $propertyId, 'user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
