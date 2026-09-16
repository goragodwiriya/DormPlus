<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class PaymentRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param array $filters q, month (YYYY-MM), room_id, tenant_id, status, type, sort
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
        $where = ['p.property_id = :property_id'];
        $bindings = ['property_id' => $propertyId];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(r.room_number LIKE :q OR t.first_name LIKE :q
                         OR t.last_name LIKE :q OR p.reference LIKE :q)';
            $bindings['q'] = '%'.$filters['q'].'%';
        }

        if (($filters['month'] ?? '') !== '') {
            [$year, $month] = array_map('intval', explode('-', $filters['month']));
            $where[] = 'p.period_year = :period_year AND p.period_month = :period_month';
            $bindings['period_year'] = $year;
            $bindings['period_month'] = $month;
        }

        if (($filters['room_id'] ?? '') !== '') {
            $where[] = 'p.room_id = :room_id';
            $bindings['room_id'] = (int) $filters['room_id'];
        }

        if (($filters['tenant_id'] ?? '') !== '') {
            $where[] = 'p.tenant_id = :tenant_id';
            $bindings['tenant_id'] = (int) $filters['tenant_id'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'p.status = :status';
            $bindings['status'] = $filters['status'];
        }

        if (($filters['type'] ?? '') !== '') {
            $where[] = 'p.payment_type = :type';
            $bindings['type'] = $filters['type'];
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'amount' => 'p.amount DESC, p.id DESC',
            'room' => 'r.room_number ASC, p.id DESC',
            default => 'datetime(COALESCE(p.payment_date, p.created_at)) DESC, p.id DESC'
        };

        $whereSql = implode(' AND ', $where);

        $countStatement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM payments p
             INNER JOIN rooms r ON r.id = p.room_id
             INNER JOIN tenants t ON t.id = p.tenant_id
             WHERE {$whereSql}"
        );
        $countStatement->execute($bindings);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            "SELECT
                p.*,
                r.room_number, r.floor,
                t.first_name, t.last_name, t.gender
             FROM payments p
             INNER JOIN rooms r ON r.id = p.room_id
             INNER JOIN tenants t ON t.id = p.tenant_id
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
                p.*,
                r.room_number, r.floor,
                t.first_name, t.last_name, t.gender, t.phone AS tenant_phone
             FROM payments p
             INNER JOIN rooms r ON r.id = p.room_id
             INNER JOIN tenants t ON t.id = p.tenant_id
             WHERE p.property_id = :property_id AND p.id = :id
             LIMIT 1'
        );

        $statement->execute(['property_id' => $propertyId, 'id' => $id]);
        $payment = $statement->fetch();

        return $payment ?: null;
    }

    /**
     * ประวัติการชำระของห้อง/ผู้เช่า
     *
     * @param string $column room_id|tenant_id
     * @param int $id
     * @param int $limit
     */
    public function historyFor(string $column, int $id, int $limit = 12): array
    {
        $column = $column === 'room_id' ? 'room_id' : 'tenant_id';

        $statement = $this->pdo->prepare(
            "SELECT
                p.id, p.amount, p.payment_type, p.payment_date,
                p.period_month, p.period_year, p.reference, p.status,
                r.room_number, t.first_name, t.last_name
             FROM payments p
             INNER JOIN rooms r ON r.id = p.room_id
             INNER JOIN tenants t ON t.id = p.tenant_id
             WHERE p.{$column} = :id
             ORDER BY p.period_year DESC, p.period_month DESC, p.id DESC
             LIMIT :limit"
        );

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
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
            'INSERT INTO payments (
                property_id, tenant_id, room_id, contract_id, amount,
                payment_type, payment_date, period_month, period_year,
                reference, notes, status
             ) VALUES (
                :property_id, :tenant_id, :room_id, :contract_id, :amount,
                :payment_type, :payment_date, :period_month, :period_year,
                :reference, :notes, :status
             )'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'tenant_id' => $data['tenant_id'],
            'room_id' => $data['room_id'],
            'contract_id' => $data['contract_id'],
            'amount' => $data['amount'],
            'payment_type' => $data['payment_type'],
            'payment_date' => $data['payment_date'],
            'period_month' => $data['period_month'],
            'period_year' => $data['period_year'],
            'reference' => $data['reference'],
            'notes' => $data['notes'],
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
            'UPDATE payments SET
                tenant_id = :tenant_id,
                room_id = :room_id,
                contract_id = :contract_id,
                amount = :amount,
                payment_type = :payment_type,
                payment_date = :payment_date,
                period_month = :period_month,
                period_year = :period_year,
                reference = :reference,
                notes = :notes,
                status = :status,
                updated_at = datetime(\'now\', \'localtime\')
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'tenant_id' => $data['tenant_id'],
            'room_id' => $data['room_id'],
            'contract_id' => $data['contract_id'],
            'amount' => $data['amount'],
            'payment_type' => $data['payment_type'],
            'payment_date' => $data['payment_date'],
            'period_month' => $data['period_month'],
            'period_year' => $data['period_year'],
            'reference' => $data['reference'],
            'notes' => $data['notes'],
            'status' => $data['status']
        ]);
    }

    /**
     * @param int $id
     */
    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM payments WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * สรุปยอดของงวดที่เลือก: ชำระแล้ว / ค้างชำระ / จำนวนรายการ
     *
     * @param int $propertyId
     * @param int $year
     * @param int $month
     */
    public function periodSummary(int $propertyId, int $year, int $month): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_total,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
             FROM payments
             WHERE property_id = :property_id
               AND period_year = :year
               AND period_month = :month"
        );

        $statement->execute([
            'property_id' => $propertyId,
            'year' => $year,
            'month' => $month
        ]);

        return $statement->fetch();
    }
}
