<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class MaintenanceRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param array $filters q, status, priority, room_id, open (1 = ยังไม่ปิดงาน)
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
        $where = ['m.property_id = :property_id'];
        $bindings = ['property_id' => $propertyId];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(m.title LIKE :q OR m.description LIKE :q
                         OR r.room_number LIKE :q OR m.assigned_to LIKE :q)';
            $bindings['q'] = '%'.$filters['q'].'%';
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'm.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['priority'] ?? '') !== '') {
            $where[] = 'm.priority = :priority';
            $bindings['priority'] = $filters['priority'];
        }

        if (($filters['room_id'] ?? '') !== '') {
            $where[] = 'm.room_id = :room_id';
            $bindings['room_id'] = (int) $filters['room_id'];
        }

        if (($filters['open'] ?? '') !== '') {
            $where[] = "m.status IN ('pending', 'in_progress')";
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = 'date(m.reported_at) >= date(:from)';
            $bindings['from'] = $filters['from'];
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = 'date(m.reported_at) <= date(:to)';
            $bindings['to'] = $filters['to'];
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'priority' => "CASE m.priority
                            WHEN 'urgent' THEN 0 WHEN 'high' THEN 1
                            WHEN 'normal' THEN 2 ELSE 3 END ASC,
                           datetime(m.reported_at) DESC",
            'oldest' => 'datetime(m.reported_at) ASC, m.id ASC',
            default => 'datetime(m.reported_at) DESC, m.id DESC'
        };

        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM maintenance_requests m
             INNER JOIN rooms r ON r.id = m.room_id
             WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT
                m.*,
                r.room_number, r.floor,
                t.first_name, t.last_name
             FROM maintenance_requests m
             INNER JOIN rooms r ON r.id = m.room_id
             LEFT JOIN tenants t ON t.id = m.tenant_id
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
            'SELECT
                m.*,
                r.room_number, r.floor,
                t.first_name, t.last_name, t.phone AS tenant_phone
             FROM maintenance_requests m
             INNER JOIN rooms r ON r.id = m.room_id
             LEFT JOIN tenants t ON t.id = m.tenant_id
             WHERE m.property_id = :property_id AND m.id = :id
             LIMIT 1'
        );

        $statement->execute(['property_id' => $propertyId, 'id' => $id]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    /**
     * @param int $roomId
     * @param int $limit
     */
    public function forRoom(int $roomId, int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, priority, status, reported_at, completed_at, cost
             FROM maintenance_requests
             WHERE room_id = :room_id
             ORDER BY datetime(reported_at) DESC
             LIMIT :limit'
        );

        $statement->bindValue(':room_id', $roomId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @param int $propertyId
     * @param array $data
     */
    public function create(int $propertyId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO maintenance_requests (
                property_id, room_id, tenant_id, title, description,
                priority, status, assigned_to, reported_at, cost, notes
             ) VALUES (
                :property_id, :room_id, :tenant_id, :title, :description,
                :priority, :status, :assigned_to, :reported_at, :cost, :notes
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'room_id' => $data['room_id'],
            'tenant_id' => $data['tenant_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'],
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'],
            'reported_at' => $data['reported_at'],
            'cost' => $data['cost'],
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
            'UPDATE maintenance_requests SET
                room_id = :room_id,
                tenant_id = :tenant_id,
                title = :title,
                description = :description,
                priority = :priority,
                status = :status,
                assigned_to = :assigned_to,
                started_at = :started_at,
                completed_at = :completed_at,
                cost = :cost,
                notes = :notes,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'room_id' => $data['room_id'],
            'tenant_id' => $data['tenant_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'priority' => $data['priority'],
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'],
            'started_at' => $data['started_at'],
            'completed_at' => $data['completed_at'],
            'cost' => $data['cost'],
            'notes' => $data['notes']
        ]);
    }

    /**
     * @param int $id
     */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM maintenance_requests WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    /**
     * จำนวนงานแยกตามสถานะ
     *
     * @param int $propertyId
     */
    public function statusCounts(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS total
             FROM maintenance_requests
             WHERE property_id = :property_id
             GROUP BY status'
        );

        $statement->execute(['property_id' => $propertyId]);

        $counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];

        foreach ($statement->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}
