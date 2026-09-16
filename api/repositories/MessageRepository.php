<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class MessageRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * รายการบทสนทนาของผู้ใช้ พร้อมข้อความล่าสุดและจำนวนที่ยังไม่อ่าน
     *
     * @param int $propertyId
     * @param int $userId
     * @param string $search
     */
    public function threadsForUser(int $propertyId, int $userId, string $search = ''): array
    {
        $where = ['mt.property_id = :property_id', 'tp.user_id = :user_id'];
        $bindings = ['property_id' => $propertyId, 'user_id' => $userId];

        if ($search !== '') {
            $where[] = 'mt.subject LIKE :q';
            $bindings['q'] = '%'.$search.'%';
        }

        $whereSql = implode(' AND ', $where);

        $statement = $this->pdo->prepare(
            "SELECT
                mt.id, mt.subject, mt.created_by, mt.created_at, mt.updated_at,
                tp.last_read_at,
                (SELECT m.body FROM messages m
                  WHERE m.thread_id = mt.id
                  ORDER BY datetime(m.created_at) DESC, m.id DESC LIMIT 1) AS last_message,
                (SELECT m.created_at FROM messages m
                  WHERE m.thread_id = mt.id
                  ORDER BY datetime(m.created_at) DESC, m.id DESC LIMIT 1) AS last_message_at,
                (SELECT u.first_name FROM messages m
                  INNER JOIN users u ON u.id = m.sender_id
                  WHERE m.thread_id = mt.id
                  ORDER BY datetime(m.created_at) DESC, m.id DESC LIMIT 1) AS last_sender_name,
                (SELECT COUNT(*) FROM messages m
                  WHERE m.thread_id = mt.id
                    AND m.sender_id <> :reader_id
                    AND (tp.last_read_at IS NULL
                         OR datetime(m.created_at) > datetime(tp.last_read_at))) AS unread_count,
                (SELECT GROUP_CONCAT(u.first_name || ' ' || u.last_name, ', ')
                  FROM thread_participants p2
                  INNER JOIN users u ON u.id = p2.user_id
                  WHERE p2.thread_id = mt.id AND p2.user_id <> :other_id) AS participants
             FROM message_threads mt
             INNER JOIN thread_participants tp ON tp.thread_id = mt.id
             WHERE {$whereSql}
             ORDER BY datetime(COALESCE(last_message_at, mt.created_at)) DESC"
        );

        $bindings['reader_id'] = $userId;
        $bindings['other_id'] = $userId;
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $threadId
     * @return mixed
     */
    public function findThread(int $propertyId, int $userId, int $threadId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT mt.id, mt.subject, mt.created_by, mt.created_at, tp.last_read_at
             FROM message_threads mt
             INNER JOIN thread_participants tp
                ON tp.thread_id = mt.id AND tp.user_id = :user_id
             WHERE mt.property_id = :property_id AND mt.id = :thread_id
             LIMIT 1'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'user_id' => $userId,
            'thread_id' => $threadId
        ]);

        $thread = $statement->fetch();

        return $thread ?: null;
    }

    /**
     * @param int $threadId
     */
    public function participants(int $threadId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.role, u.avatar
             FROM thread_participants tp
             INNER JOIN users u ON u.id = tp.user_id
             WHERE tp.thread_id = :thread_id
             ORDER BY u.first_name ASC'
        );

        $statement->execute(['thread_id' => $threadId]);

        return $statement->fetchAll();
    }

    /**
     * @param int $threadId
     */
    public function messages(int $threadId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.id, m.thread_id, m.sender_id, m.body, m.created_at,
                    u.first_name, u.last_name, u.avatar
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_id
             WHERE m.thread_id = :thread_id
             ORDER BY datetime(m.created_at) ASC, m.id ASC'
        );

        $statement->execute(['thread_id' => $threadId]);

        return $statement->fetchAll();
    }

    /**
     * @param int $propertyId
     * @param string $subject
     * @param int $createdBy
     */
    public function createThread(int $propertyId, string $subject, int $createdBy): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO message_threads (property_id, subject, created_by)
             VALUES (:property_id, :subject, :created_by)'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'subject' => $subject,
            'created_by' => $createdBy
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $threadId
     * @param int $userId
     */
    public function addParticipant(int $threadId, int $userId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO thread_participants (thread_id, user_id)
             VALUES (:thread_id, :user_id)'
        );

        $statement->execute(['thread_id' => $threadId, 'user_id' => $userId]);
    }

    /**
     * @param int $threadId
     * @param int $senderId
     * @param string $body
     */
    public function addMessage(int $threadId, int $senderId, string $body): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO messages (thread_id, sender_id, body)
             VALUES (:thread_id, :sender_id, :body)'
        );

        $statement->execute([
            'thread_id' => $threadId,
            'sender_id' => $senderId,
            'body' => $body
        ]);

        $touch = $this->pdo->prepare(
            'UPDATE message_threads SET updated_at = datetime(\'now\', \'localtime\') WHERE id = :id'
        );
        $touch->execute(['id' => $threadId]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $threadId
     * @param int $userId
     */
    public function markRead(int $threadId, int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE thread_participants
             SET last_read_at = datetime(\'now\', \'localtime\')
             WHERE thread_id = :thread_id AND user_id = :user_id'
        );

        $statement->execute(['thread_id' => $threadId, 'user_id' => $userId]);
    }

    /**
     * จำนวนข้อความที่ยังไม่อ่านรวมทุกบทสนทนา
     *
     * @param int $propertyId
     * @param int $userId
     */
    public function unreadCount(int $propertyId, int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM messages m
             INNER JOIN message_threads mt ON mt.id = m.thread_id
             INNER JOIN thread_participants tp
                ON tp.thread_id = mt.id AND tp.user_id = :user_id
             WHERE mt.property_id = :property_id
               AND m.sender_id <> :sender_id
               AND (tp.last_read_at IS NULL
                    OR datetime(m.created_at) > datetime(tp.last_read_at))'
        );

        $statement->execute([
            'user_id' => $userId,
            'property_id' => $propertyId,
            'sender_id' => $userId
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * ผู้ใช้ในหอพักเดียวกันสำหรับเลือกผู้รับ
     *
     * @param int $propertyId
     */
    public function users(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, first_name, last_name, role, avatar
             FROM users
             WHERE property_id = :property_id AND is_active = 1
             ORDER BY first_name ASC'
        );

        $statement->execute(['property_id' => $propertyId]);

        return $statement->fetchAll();
    }
}
