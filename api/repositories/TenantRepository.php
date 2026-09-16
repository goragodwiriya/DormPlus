<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class TenantRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param array $filters q, status, room_id
     * @param int $limit
     * @param int $offset
     * @return array{items: array, total: int}
     */
    public function paginate(
        int $propertyId,
        array $filters,
        int $limit,
        int $offset
    ): array {
        $where = ['t.property_id = :property_id'];
        $bindings = ['property_id' => $propertyId];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(t.first_name LIKE :q OR t.last_name LIKE :q
                         OR t.phone LIKE :q OR t.email LIKE :q OR r.room_number LIKE :q)';
            $bindings['q'] = '%'.$filters['q'].'%';
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 't.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['gender'] ?? '') !== '') {
            $where[] = 't.gender = :gender';
            $bindings['gender'] = $filters['gender'];
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'latest' => 'datetime(t.created_at) DESC, t.id DESC',
            'room' => 'r.room_number ASC, t.first_name ASC',
            default => 't.first_name ASC, t.last_name ASC'
        };

        $joins = $this->currentContractJoin();
        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tenants t {$joins} WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT
                t.id, t.first_name, t.last_name, t.gender, t.phone, t.email,
                t.status, t.created_at, t.updated_at,
                c.id AS contract_id,
                c.end_date AS contract_end_date,
                r.id AS room_id,
                r.room_number,
                r.floor
             FROM tenants t
             {$joins}
             WHERE {$whereSql}
             ORDER BY {$orderBy}
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
     * @param int $id
     * @return mixed
     */
    public function find(int $propertyId, int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                t.*,
                c.id AS contract_id,
                c.start_date AS contract_start_date,
                c.end_date AS contract_end_date,
                c.monthly_rent AS contract_rent,
                r.id AS room_id,
                r.room_number,
                r.floor
             FROM tenants t
             {$this->currentContractJoin()}
             WHERE t.property_id = :property_id AND t.id = :id
             LIMIT 1"
        );

        $statement->execute(['property_id' => $propertyId, 'id' => $id]);
        $tenant = $statement->fetch();

        return $tenant ?: null;
    }

    /**
     * @param int $propertyId
     * @param array $data
     */
    public function create(int $propertyId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tenants (
                property_id, first_name, last_name, gender, phone, email,
                national_id, address, emergency_contact, emergency_phone, status
             ) VALUES (
                :property_id, :first_name, :last_name, :gender, :phone, :email,
                :national_id, :address, :emergency_contact, :emergency_phone, :status
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'national_id' => $data['national_id'],
            'address' => $data['address'],
            'emergency_contact' => $data['emergency_contact'],
            'emergency_phone' => $data['emergency_phone'],
            'status' => $data['status']
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tenants SET
                first_name = :first_name,
                last_name = :last_name,
                gender = :gender,
                phone = :phone,
                email = :email,
                national_id = :national_id,
                address = :address,
                emergency_contact = :emergency_contact,
                emergency_phone = :emergency_phone,
                status = :status,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'national_id' => $data['national_id'],
            'address' => $data['address'],
            'emergency_contact' => $data['emergency_contact'],
            'emergency_phone' => $data['emergency_phone'],
            'status' => $data['status']
        ]);
    }

    /**
     * @param int $id
     * @param string $status
     */
    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tenants
             SET status = :status, updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * @param int $id
     */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM tenants WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param int $id
     */
    public function hasRelatedRecords(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM contracts WHERE tenant_id = :contract_tenant)
              + (SELECT COUNT(*) FROM payments WHERE tenant_id = :payment_tenant)'
        );

        $statement->execute([
            'contract_tenant' => $id,
            'payment_tenant' => $id
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * รายชื่อผู้เช่าแบบย่อสำหรับ select
     *
     * @param int $propertyId
     */
    public function options(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                t.id, t.first_name, t.last_name, t.status,
                r.id AS room_id, r.room_number,
                c.id AS contract_id, c.monthly_rent AS contract_rent
             FROM tenants t
             {$this->currentContractJoin()}
             WHERE t.property_id = :property_id
             ORDER BY t.first_name ASC, t.last_name ASC"
        );

        $statement->execute(['property_id' => $propertyId]);

        return $statement->fetchAll();
    }

    private function currentContractJoin(): string
    {
        return "LEFT JOIN contracts c
                    ON c.tenant_id = t.id
                   AND c.status = 'active'
                   AND c.id = (
                        SELECT MAX(c2.id) FROM contracts c2
                        WHERE c2.tenant_id = t.id AND c2.status = 'active'
                   )
                LEFT JOIN rooms r ON r.id = c.room_id";
    }
}
