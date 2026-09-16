<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class SettingsRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @return mixed
     */
    public function property(int $propertyId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, address, phone, email, description, created_at, updated_at
             FROM properties WHERE id = :id LIMIT 1'
        );

        $statement->execute(['id' => $propertyId]);
        $property = $statement->fetch();

        return $property ?: null;
    }

    /**
     * @param int $propertyId
     * @param array $data
     */
    public function updateProperty(int $propertyId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE properties SET
                name = :name,
                address = :address,
                phone = :phone,
                email = :email,
                description = :description,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $propertyId,
            'name' => $data['name'],
            'address' => $data['address'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'description' => $data['description']
        ]);
    }

    /**
     * การตั้งค่าทั้งหมดเป็น key => value
     *
     * @param int $propertyId
     */
    public function all(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_key, setting_value FROM settings
             WHERE property_id = :property_id'
        );

        $statement->execute(['property_id' => $propertyId]);

        $settings = [];

        foreach ($statement->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    /**
     * @param int $propertyId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(int $propertyId, string $key, mixed $default = null): mixed
    {
        $statement = $this->pdo->prepare(
            'SELECT setting_value FROM settings
             WHERE property_id = :property_id AND setting_key = :key
             LIMIT 1'
        );

        $statement->execute(['property_id' => $propertyId, 'key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : $value;
    }

    /**
     * @param int $propertyId
     * @param string $key
     * @param string|null $value
     */
    public function set(int $propertyId, string $key, ?string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (property_id, setting_key, setting_value)
             VALUES (:property_id, :key, :value)
             ON CONFLICT(property_id, setting_key)
             DO UPDATE SET setting_value = excluded.setting_value,
                           updated_at = datetime(\'now\', \'localtime\')'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'key' => $key,
            'value' => $value
        ]);
    }
}
