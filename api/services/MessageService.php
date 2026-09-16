<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DormPlus\Api\Core\NotFoundException;
use DormPlus\Api\Core\Validator;
use DormPlus\Api\Repositories\MessageRepository;
use PDO;
use Throwable;

final class MessageService
{
    /**
     * @param PDO $pdo
     * @param MessageRepository $messages
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly MessageRepository $messages
    ) {
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param string $search
     */
    public function threads(int $propertyId, int $userId, string $search = ''): array
    {
        $threads = $this->messages->threadsForUser($propertyId, $userId, $search);

        return [
            'items' => array_map(static fn(array $thread): array => [
                 ...$thread,
                'id' => (int) $thread['id'],
                'created_by' => (int) $thread['created_by'],
                'unread_count' => (int) $thread['unread_count']
            ], $threads),
            'unread_count' => $this->messages->unreadCount($propertyId, $userId)
        ];
    }

    /**
     * เปิดบทสนทนา (และทำเครื่องหมายว่าอ่านแล้ว)
     *
     * @param int $propertyId
     * @param int $userId
     * @param int $threadId
     */
    public function thread(int $propertyId, int $userId, int $threadId): array
    {
        $thread = $this->messages->findThread($propertyId, $userId, $threadId);

        if ($thread === null) {
            throw new NotFoundException('ไม่พบบทสนทนา');
        }

        $this->messages->markRead($threadId, $userId);

        return [
            'thread' => [ ...$thread, 'id' => (int) $thread['id']],
            'participants' => $this->messages->participants($threadId),
            'messages' => array_map(static fn(array $message): array => [
                 ...$message,
                'id' => (int) $message['id'],
                'sender_id' => (int) $message['sender_id'],
                'is_mine' => (int) $message['sender_id'] === $userId
            ], $this->messages->messages($threadId)),
            'unread_count' => $this->messages->unreadCount($propertyId, $userId)
        ];
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param array $input subject, participant_ids[], body
     */
    public function createThread(int $propertyId, int $userId, array $input): array
    {
        $validator = new Validator($input);
        $validator->required('subject', 'หัวข้อ')->max('subject', 160);
        $validator->required('body', 'ข้อความ')->max('body', 4000);
        $validator->required('participant_ids', 'ผู้รับ');

        if ($validator->fails()) {
            $validator->validate();
        }

        $participantIds = array_values(array_unique(array_map(
            'intval',
            (array) $validator->value('participant_ids')
        )));

        $participantIds = array_filter($participantIds, static fn(int $id): bool => $id > 0);
        $validUsers = array_column($this->messages->users($propertyId), 'id');
        $validUsers = array_map('intval', $validUsers);

        foreach ($participantIds as $participantId) {
            if (!in_array($participantId, $validUsers, true)) {
                $validator->addError('participant_ids', 'ผู้รับบางรายไม่อยู่ในหอพักนี้');
                break;
            }
        }

        if ($participantIds === [] || $participantIds === [$userId]) {
            $validator->addError('participant_ids', 'กรุณาเลือกผู้รับอย่างน้อย 1 คน');
        }

        $validator->validate();

        $this->pdo->beginTransaction();

        try {
            $threadId = $this->messages->createThread(
                $propertyId,
                (string) $validator->value('subject'),
                $userId
            );

            $this->messages->addParticipant($threadId, $userId);

            foreach ($participantIds as $participantId) {
                $this->messages->addParticipant($threadId, $participantId);
            }

            $this->messages->addMessage($threadId, $userId, (string) $validator->value('body'));
            $this->messages->markRead($threadId, $userId);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $this->thread($propertyId, $userId, $threadId);
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $threadId
     * @param array $input body
     */
    public function reply(int $propertyId, int $userId, int $threadId, array $input): array
    {
        if ($this->messages->findThread($propertyId, $userId, $threadId) === null) {
            throw new NotFoundException('ไม่พบบทสนทนา');
        }

        $validator = new Validator($input);
        $validator->required('body', 'ข้อความ')->max('body', 4000);
        $validator->validate();

        $this->messages->addMessage($threadId, $userId, (string) $validator->value('body'));
        $this->messages->markRead($threadId, $userId);

        return $this->thread($propertyId, $userId, $threadId);
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $threadId
     */
    public function markRead(int $propertyId, int $userId, int $threadId): array
    {
        if ($this->messages->findThread($propertyId, $userId, $threadId) === null) {
            throw new NotFoundException('ไม่พบบทสนทนา');
        }

        $this->messages->markRead($threadId, $userId);

        return ['unread_count' => $this->messages->unreadCount($propertyId, $userId)];
    }

    /**
     * @param int $propertyId
     * @param int $userId
     */
    public function unreadCount(int $propertyId, int $userId): int
    {
        return $this->messages->unreadCount($propertyId, $userId);
    }

    /**
     * ผู้ใช้ที่ส่งข้อความหาได้ (ไม่รวมตัวเอง)
     *
     * @param int $propertyId
     * @param int $userId
     */
    public function recipients(int $propertyId, int $userId): array
    {
        return array_values(array_filter(
            array_map(static fn(array $user): array => [
                 ...$user,
                'id' => (int) $user['id'],
                'name' => trim($user['first_name'].' '.$user['last_name'])
            ], $this->messages->users($propertyId)),
            static fn(array $user): bool => $user['id'] !== $userId
        ));
    }
}
