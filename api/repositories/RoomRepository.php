<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class RoomRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * รายการห้องพร้อมผู้เช่าปัจจุบัน (จากสัญญาที่ active)
     *
     * @param int $propertyId
     * @param array $filters q, status, floor, sort
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
        $where = ['r.property_id = :property_id'];
        $bindings = ['property_id' => $propertyId];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(r.room_number LIKE :q OR t.first_name LIKE :q OR t.last_name LIKE :q)';
            $bindings['q'] = '%'.$filters['q'].'%';
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'r.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['floor'] ?? '') !== '') {
            $where[] = 'r.floor = :floor';
            $bindings['floor'] = (int) $filters['floor'];
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'latest' => 'datetime(r.updated_at) DESC, r.id DESC',
            'rent_asc' => 'r.monthly_rent ASC, r.room_number ASC',
            'rent_desc' => 'r.monthly_rent DESC, r.room_number ASC',
            'status' => 'r.status ASC, r.room_number ASC',
            default => 'r.floor ASC, r.room_number ASC'
        };

        $joins = $this->currentContractJoin();
        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rooms r {$joins} WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT
                r.id, r.room_number, r.floor, r.type, r.area,
                r.monthly_rent, r.status, r.description, r.image,
                r.created_at, r.updated_at,
                c.id AS contract_id,
                c.end_date AS contract_end_date,
                t.id AS tenant_id,
                t.first_name,
                t.last_name
             FROM rooms r
             {$joins}
             WHERE {$whereSql}
             ORDER BY {$orderBy}
             LIMIT :limit OFFSET :offset"
        );

        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                ':'.$key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
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
                r.*,
                c.id AS contract_id,
                c.start_date AS contract_start_date,
                c.end_date AS contract_end_date,
                c.monthly_rent AS contract_rent,
                c.deposit AS contract_deposit,
                t.id AS tenant_id,
                t.first_name,
                t.last_name,
                t.phone AS tenant_phone,
                t.gender AS tenant_gender
             FROM rooms r
             {$this->currentContractJoin()}
             WHERE r.property_id = :property_id AND r.id = :id
             LIMIT 1"
        );

        $statement->execute(['property_id' => $propertyId, 'id' => $id]);
        $room = $statement->fetch();

        return $room ?: null;
    }

    /**
     * @param int $propertyId
     * @param string $roomNumber
     * @param int|null $ignoreId
     */
    public function numberExists(
        int $propertyId,
        string $roomNumber,
        ?int $ignoreId = null
    ): bool {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rooms
             WHERE property_id = :property_id
               AND room_number = :room_number
               AND (:ignore_id IS NULL OR id <> :ignore_id)'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'room_number' => $roomNumber,
            'ignore_id' => $ignoreId
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param int $propertyId
     * @param array $data
     */
    public function create(int $propertyId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO rooms (
                property_id, room_number, floor, type, area,
                monthly_rent, status, description, image
             ) VALUES (
                :property_id, :room_number, :floor, :type, :area,
                :monthly_rent, :status, :description, :image
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'room_number' => $data['room_number'],
            'floor' => $data['floor'],
            'type' => $data['type'],
            'area' => $data['area'],
            'monthly_rent' => $data['monthly_rent'],
            'status' => $data['status'],
            'description' => $data['description'],
            'image' => $data['image'] ?? 'assets/images/room-placeholder.svg'
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
            'UPDATE rooms SET
                room_number = :room_number,
                floor = :floor,
                type = :type,
                area = :area,
                monthly_rent = :monthly_rent,
                status = :status,
                description = :description,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'room_number' => $data['room_number'],
            'floor' => $data['floor'],
            'type' => $data['type'],
            'area' => $data['area'],
            'monthly_rent' => $data['monthly_rent'],
            'status' => $data['status'],
            'description' => $data['description']
        ]);
    }

    /**
     * @param int $id
     * @param string $status
     */
    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE rooms
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
        $statement = $this->pdo->prepare('DELETE FROM rooms WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param int $id
     */
    public function hasRelatedRecords(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM contracts WHERE room_id = :contract_room)
              + (SELECT COUNT(*) FROM payments WHERE room_id = :payment_room)
              + (SELECT COUNT(*) FROM maintenance_requests WHERE room_id = :maintenance_room)'
        );

        $statement->execute([
            'contract_room' => $id,
            'payment_room' => $id,
            'maintenance_room' => $id
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * ชั้นทั้งหมดที่มีห้อง สำหรับตัวกรอง
     *
     * @param int $propertyId
     */
    public function floors(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT floor FROM rooms
             WHERE property_id = :property_id
             ORDER BY floor ASC'
        );

        $statement->execute(['property_id' => $propertyId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * ห้องที่ให้เช่าได้ (ว่าง/จองแล้ว) สำหรับสร้างสัญญา
     *
     * @param int $propertyId
     * @param int|null $includeRoomId ห้องปัจจุบันของสัญญาที่กำลังแก้ไข
     */
    public function availableForContract(int $propertyId, ?int $includeRoomId = null): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, room_number, floor, type, monthly_rent, status
             FROM rooms
             WHERE property_id = :property_id
               AND (status IN (:available, :reserved) OR id = :include_id)
             ORDER BY floor ASC, room_number ASC'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'available' => 'available',
            'reserved' => 'reserved',
            'include_id' => $includeRoomId
        ]);

        return $statement->fetchAll();
    }

    /**
     * รายการห้องแบบย่อ (id, เลขห้อง, ค่าเช่า) สำหรับ select
     *
     * @param int $propertyId
     */
    public function options(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, room_number, floor, monthly_rent, status
             FROM rooms
             WHERE property_id = :property_id
             ORDER BY floor ASC, room_number ASC'
        );

        $statement->execute(['property_id' => $propertyId]);

        return $statement->fetchAll();
    }

    private function currentContractJoin(): string
    {
        return "LEFT JOIN contracts c
                    ON c.room_id = r.id
                   AND c.status = 'active'
                   AND c.id = (
                        SELECT MAX(c2.id) FROM contracts c2
                        WHERE c2.room_id = r.id AND c2.status = 'active'
                   )
                LEFT JOIN tenants t ON t.id = c.tenant_id";
    }
}
