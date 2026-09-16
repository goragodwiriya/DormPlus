<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class UserRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param string $username
     * @return mixed
     */
    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                id,
                property_id,
                username,
                password_hash,
                first_name,
                last_name,
                role,
                avatar,
                is_active
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function findPublicById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                u.id,
                u.property_id,
                u.username,
                u.first_name,
                u.last_name,
                u.role,
                u.avatar,
                p.name AS property_name
             FROM users u
             LEFT JOIN properties p ON p.id = u.property_id
             WHERE u.id = :id
               AND u.is_active = 1
             LIMIT 1'
        );

        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    /**
     * @param int $id
     * @return mixed
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, property_id, username, password_hash, first_name, last_name, role
             FROM users WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    /**
     * @param int $id
     * @param string $passwordHash
     */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET password_hash = :password_hash, updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute(['id' => $id, 'password_hash' => $passwordHash]);
    }

    /**
     * @param int $id
     * @param array $data first_name, last_name
     */
    public function updateProfile(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET first_name = :first_name, last_name = :last_name,
                 updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ]);
    }

    /**
     * @param int $id
     */
    public function updateLastLogin(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET last_login_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute(['id' => $id]);
    }
}