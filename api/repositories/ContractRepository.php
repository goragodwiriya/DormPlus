<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class ContractRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param array $filters q, status, expiring_within, room_id, tenant_id
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
        $where = ['c.property_id = :property_id'];
        $bindings = ['property_id' => $propertyId];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(r.room_number LIKE :q OR t.first_name LIKE :q OR t.last_name LIKE :q)';
            $bindings['q'] = '%'.$filters['q'].'%';
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'c.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['room_id'] ?? '') !== '') {
            $where[] = 'c.room_id = :room_id';
            $bindings['room_id'] = (int) $filters['room_id'];
        }

        if (($filters['tenant_id'] ?? '') !== '') {
            $where[] = 'c.tenant_id = :tenant_id';
            $bindings['tenant_id'] = (int) $filters['tenant_id'];
        }

        if (($filters['expiring_within'] ?? '') !== '') {
            $where[] = "c.status = 'active'
                        AND date(c.end_date) >= date('now', 'localtime')
                        AND date(c.end_date) <= date('now', 'localtime', :expiring_days)";
            $bindings['expiring_days'] = '+'.(int) $filters['expiring_within'].' days';
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = 'date(c.end_date) >= date(:from)';
            $bindings['from'] = $filters['from'];
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = 'date(c.end_date) <= date(:to)';
            $bindings['to'] = $filters['to'];
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'end_date' => 'date(c.end_date) ASC, c.id ASC',
            'room' => 'r.room_number ASC, c.id DESC',
            default => 'datetime(c.created_at) DESC, c.id DESC'
        };

        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM contracts c
             INNER JOIN rooms r ON r.id = c.room_id
             INNER JOIN tenants t ON t.id = c.tenant_id
             WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT
                c.*,
                r.room_number, r.floor,
                t.first_name, t.last_name, t.phone AS tenant_phone,
                CAST(julianday(c.end_date) - julianday('now', 'localtime') AS INTEGER)
                    AS days_remaining
             FROM contracts c
             INNER JOIN rooms r ON r.id = c.room_id
             INNER JOIN tenants t ON t.id = c.tenant_id
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
                c.*,
                r.room_number, r.floor, r.type AS room_type,
                t.first_name, t.last_name, t.phone AS tenant_phone,
                t.email AS tenant_email,
                CAST(julianday(c.end_date) - julianday('now', 'localtime') AS INTEGER)
                    AS days_remaining
             FROM contracts c
             INNER JOIN rooms r ON r.id = c.room_id
             INNER JOIN tenants t ON t.id = c.tenant_id
             WHERE c.property_id = :property_id AND c.id = :id
             LIMIT 1"
        );

        $statement->execute(['property_id' => $propertyId, 'id' => $id]);
        $contract = $statement->fetch();

        return $contract ?: null;
    }

    /**
     * สัญญาทั้งหมดของห้อง/ผู้เช่า (ประวัติ)
     *
     * @param string $column room_id|tenant_id
     * @param int $id
     */
    public function historyFor(string $column, int $id): array
    {
        $column = $column === 'room_id' ? 'room_id' : 'tenant_id';

        $statement = $this->pdo->prepare(
            "SELECT
                c.id, c.tenant_id, c.room_id, c.start_date, c.end_date,
                c.monthly_rent, c.deposit, c.status, c.created_at,
                r.room_number, t.first_name, t.last_name
             FROM contracts c
             INNER JOIN rooms r ON r.id = c.room_id
             INNER JOIN tenants t ON t.id = c.tenant_id
             WHERE c.{$column} = :id
             ORDER BY date(c.start_date) DESC, c.id DESC"
        );

        $statement->execute(['id' => $id]);

        return $statement->fetchAll();
    }

    /**
     * @param int $roomId
     * @param int|null $ignoreId
     */
    public function activeForRoom(int $roomId, ?int $ignoreId = null): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, tenant_id FROM contracts
             WHERE room_id = :room_id AND status = 'active'
               AND (:ignore_id IS NULL OR id <> :ignore_id)
             LIMIT 1"
        );

        $statement->execute(['room_id' => $roomId, 'ignore_id' => $ignoreId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @param int $tenantId
     * @param int|null $ignoreId
     */
    public function activeForTenant(int $tenantId, ?int $ignoreId = null): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, room_id FROM contracts
             WHERE tenant_id = :tenant_id AND status = 'active'
               AND (:ignore_id IS NULL OR id <> :ignore_id)
             LIMIT 1"
        );

        $statement->execute(['tenant_id' => $tenantId, 'ignore_id' => $ignoreId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    /**
     * @param int $propertyId
     * @param array $data
     */
    public function create(int $propertyId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO contracts (
                property_id, tenant_id, room_id, start_date, end_date,
                monthly_rent, deposit, electricity_rate, water_rate, status, notes
             ) VALUES (
                :property_id, :tenant_id, :room_id, :start_date, :end_date,
                :monthly_rent, :deposit, :electricity_rate, :water_rate, :status, :notes
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'tenant_id' => $data['tenant_id'],
            'room_id' => $data['room_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'monthly_rent' => $data['monthly_rent'],
            'deposit' => $data['deposit'],
            'electricity_rate' => $data['electricity_rate'],
            'water_rate' => $data['water_rate'],
            'status' => $data['status'],
            'notes' => $data['notes']
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
            'UPDATE contracts SET
                tenant_id = :tenant_id,
                room_id = :room_id,
                start_date = :start_date,
                end_date = :end_date,
                monthly_rent = :monthly_rent,
                deposit = :deposit,
                electricity_rate = :electricity_rate,
                water_rate = :water_rate,
                status = :status,
                notes = :notes,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'tenant_id' => $data['tenant_id'],
            'room_id' => $data['room_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'monthly_rent' => $data['monthly_rent'],
            'deposit' => $data['deposit'],
            'electricity_rate' => $data['electricity_rate'],
            'water_rate' => $data['water_rate'],
            'status' => $data['status'],
            'notes' => $data['notes']
        ]);
    }

    /**
     * @param int $id
     * @param string $status
     * @param string|null $endDate
     */
    public function updateStatus(int $id, string $status, ?string $endDate = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE contracts SET
                status = :status,
                end_date = COALESCE(:end_date, end_date),
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'status' => $status,
            'end_date' => $endDate
        ]);
    }

    /**
     * @param int $id
     */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM contracts WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * สัญญา active ที่จะหมดอายุภายใน N วัน
     *
     * @param int $propertyId
     * @param int $days
     */
    public function expiringWithin(int $propertyId, int $days): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                c.id, c.tenant_id, c.room_id, c.start_date, c.end_date,
                c.monthly_rent, c.status,
                r.room_number, r.floor,
                t.first_name, t.last_name, t.phone AS tenant_phone,
                CAST(julianday(c.end_date) - julianday('now', 'localtime') AS INTEGER)
                    AS days_remaining
             FROM contracts c
             INNER JOIN rooms r ON r.id = c.room_id
             INNER JOIN tenants t ON t.id = c.tenant_id
             WHERE c.property_id = :property_id
               AND c.status = 'active'
               AND date(c.end_date) <= date('now', 'localtime', :days)
             ORDER BY date(c.end_date) ASC"
        );

        $statement->execute([
            'property_id' => $propertyId,
            'days' => '+'.$days.' days'
        ]);

        return $statement->fetchAll();
    }

    /**
     * จำนวนสัญญาแยกตามสถานะ
     *
     * @param int $propertyId
     */
    public function statusCounts(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS total
             FROM contracts
             WHERE property_id = :property_id
             GROUP BY status'
        );

        $statement->execute(['property_id' => $propertyId]);

        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
