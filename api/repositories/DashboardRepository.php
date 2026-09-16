<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

final class DashboardRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param int $propertyId
     * @param string $month
     */
    public function statistics(int $propertyId, string $month): array
    {
        $roomStatement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = :available THEN 1 ELSE 0 END), 0)
                    AS available,
                COALESCE(SUM(CASE WHEN status = :occupied THEN 1 ELSE 0 END), 0)
                    AS occupied,
                COALESCE(SUM(CASE WHEN status = :maintenance THEN 1 ELSE 0 END), 0)
                    AS maintenance,
                COALESCE(SUM(CASE WHEN status = :reserved THEN 1 ELSE 0 END), 0)
                    AS reserved
             FROM rooms
             WHERE property_id = :property_id'
        );

        $roomStatement->execute([
            'available' => 'available',
            'occupied' => 'occupied',
            'maintenance' => 'maintenance',
            'reserved' => 'reserved',
            'property_id' => $propertyId
        ]);

        $tenantStatement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN gender = :male THEN 1 ELSE 0 END), 0)
                    AS male,
                COALESCE(SUM(CASE WHEN gender = :female THEN 1 ELSE 0 END), 0)
                    AS female,
                COALESCE(SUM(CASE WHEN gender = :other THEN 1 ELSE 0 END), 0)
                    AS other
             FROM tenants
             WHERE property_id = :property_id
               AND status = :status'
        );

        $tenantStatement->execute([
            'male' => 'male',
            'female' => 'female',
            'other' => 'other',
            'property_id' => $propertyId,
            'status' => 'active'
        ]);

        $incomeStatement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM payments
             WHERE property_id = :property_id
               AND status = :status
               AND substr(payment_date, 1, 7) = :month'
        );

        $incomeStatement->execute([
            'property_id' => $propertyId,
            'status' => 'paid',
            'month' => $month
        ]);

        $maintenanceStatement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = :pending THEN 1 ELSE 0 END), 0)
                    AS pending,
                COALESCE(SUM(CASE WHEN status = :in_progress THEN 1 ELSE 0 END), 0)
                    AS in_progress
             FROM maintenance_requests
             WHERE property_id = :property_id
               AND status IN (:pending_filter, :progress_filter)'
        );

        $maintenanceStatement->execute([
            'pending' => 'pending',
            'in_progress' => 'in_progress',
            'property_id' => $propertyId,
            'pending_filter' => 'pending',
            'progress_filter' => 'in_progress'
        ]);

        return [
            'rooms' => $roomStatement->fetch(),
            'tenants' => $tenantStatement->fetch(),
            'monthly_income' => (float) $incomeStatement->fetchColumn(),
            'maintenance' => $maintenanceStatement->fetch()
        ];
    }

    /**
     * @param int $propertyId
     * @param string $currentMonth
     * @param string $previousMonth
     */
    public function financialTotals(
        int $propertyId,
        string $currentMonth,
        string $previousMonth
    ): array {
        $paymentStatement = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(
                    CASE WHEN substr(payment_date, 1, 7) = :current_month
                    THEN amount ELSE 0 END
                ), 0) AS current_total,
                COALESCE(SUM(
                    CASE WHEN substr(payment_date, 1, 7) = :previous_month
                    THEN amount ELSE 0 END
                ), 0) AS previous_total
             FROM payments
             WHERE property_id = :property_id
               AND status = :status'
        );

        $paymentStatement->execute([
            'current_month' => $currentMonth,
            'previous_month' => $previousMonth,
            'property_id' => $propertyId,
            'status' => 'paid'
        ]);

        $expenseStatement = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(
                    CASE WHEN substr(expense_date, 1, 7) = :current_month
                    THEN amount ELSE 0 END
                ), 0) AS current_total,
                COALESCE(SUM(
                    CASE WHEN substr(expense_date, 1, 7) = :previous_month
                    THEN amount ELSE 0 END
                ), 0) AS previous_total
             FROM expenses
             WHERE property_id = :property_id'
        );

        $expenseStatement->execute([
            'current_month' => $currentMonth,
            'previous_month' => $previousMonth,
            'property_id' => $propertyId
        ]);

        return [
            'income' => $paymentStatement->fetch(),
            'expenses' => $expenseStatement->fetch()
        ];
    }

    /**
     * @param int $propertyId
     * @param string $startMonth
     * @param string $endMonth
     * @return mixed
     */
    public function monthlyFinancialData(
        int $propertyId,
        string $startMonth,
        string $endMonth
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT
                period,
                SUM(income) AS income,
                SUM(expenses) AS expenses
             FROM (
                SELECT
                    substr(payment_date, 1, 7) AS period,
                    SUM(amount) AS income,
                    0 AS expenses
                FROM payments
                WHERE property_id = :payment_property_id
                  AND status = :payment_status
                  AND substr(payment_date, 1, 7) >= :payment_start
                  AND substr(payment_date, 1, 7) < :payment_end
                GROUP BY substr(payment_date, 1, 7)

                UNION ALL

                SELECT
                    substr(expense_date, 1, 7) AS period,
                    0 AS income,
                    SUM(amount) AS expenses
                FROM expenses
                WHERE property_id = :expense_property_id
                  AND substr(expense_date, 1, 7) >= :expense_start
                  AND substr(expense_date, 1, 7) < :expense_end
                GROUP BY substr(expense_date, 1, 7)
             )
             GROUP BY period
             ORDER BY period ASC'
        );

        $statement->execute([
            'payment_property_id' => $propertyId,
            'payment_status' => 'paid',
            'payment_start' => $startMonth,
            'payment_end' => $endMonth,
            'expense_property_id' => $propertyId,
            'expense_start' => $startMonth,
            'expense_end' => $endMonth
        ]);

        return $statement->fetchAll();
    }

    /**
     * @param int $propertyId
     * @return mixed
     */
    public function roomStatus(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = :occupied THEN 1 ELSE 0 END), 0)
                    AS occupied,
                COALESCE(SUM(CASE WHEN status = :available THEN 1 ELSE 0 END), 0)
                    AS available,
                COALESCE(SUM(CASE WHEN status = :maintenance THEN 1 ELSE 0 END), 0)
                    AS maintenance,
                COALESCE(SUM(CASE WHEN status = :reserved THEN 1 ELSE 0 END), 0)
                    AS reserved
             FROM rooms
             WHERE property_id = :property_id'
        );

        $statement->execute([
            'occupied' => 'occupied',
            'available' => 'available',
            'maintenance' => 'maintenance',
            'reserved' => 'reserved',
            'property_id' => $propertyId
        ]);

        return $statement->fetch();
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $limit
     * @return mixed
     */
    public function notifications(int $propertyId, int $userId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                id,
                type,
                title,
                description,
                related_type,
                related_id,
                is_read,
                created_at
             FROM notifications
             WHERE property_id = :property_id
               AND (user_id = :user_id OR user_id IS NULL)
             ORDER BY is_read ASC, datetime(created_at) DESC
             LIMIT :limit'
        );

        $statement->bindValue(':property_id', $propertyId, PDO::PARAM_INT);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @param int $propertyId
     * @param int $userId
     */
    public function unreadNotificationCount(
        int $propertyId,
        int $userId
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications
             WHERE property_id = :property_id
               AND (user_id = :user_id OR user_id IS NULL)
               AND is_read = 0'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'user_id' => $userId
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param int $propertyId
     * @param int $limit
     * @return mixed
     */
    public function latestRooms(int $propertyId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                r.id,
                r.room_number,
                r.floor,
                r.type,
                r.monthly_rent,
                r.status,
                r.image,
                r.updated_at,
                t.id AS tenant_id,
                t.first_name,
                t.last_name,
                c.end_date AS contract_end_date
             FROM rooms r
             LEFT JOIN contracts c
                ON c.room_id = r.id
               AND c.status = :contract_status
               AND c.id = (
                    SELECT MAX(c2.id)
                    FROM contracts c2
                    WHERE c2.room_id = r.id
                      AND c2.status = :sub_contract_status
               )
             LEFT JOIN tenants t ON t.id = c.tenant_id
             WHERE r.property_id = :property_id
             ORDER BY datetime(r.updated_at) DESC, r.id ASC
             LIMIT :limit'
        );

        $statement->bindValue(':contract_status', 'active');
        $statement->bindValue(':sub_contract_status', 'active');
        $statement->bindValue(':property_id', $propertyId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @param int $propertyId
     * @param int $limit
     * @return mixed
     */
    public function latestPayments(int $propertyId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                p.id,
                p.amount,
                p.payment_type,
                p.payment_date,
                p.status,
                p.reference,
                r.id AS room_id,
                r.room_number,
                t.id AS tenant_id,
                t.first_name,
                t.last_name,
                t.gender
             FROM payments p
             INNER JOIN rooms r ON r.id = p.room_id
             INNER JOIN tenants t ON t.id = p.tenant_id
             WHERE p.property_id = :property_id
             ORDER BY datetime(p.payment_date) DESC, p.id DESC
             LIMIT :limit'
        );

        $statement->bindValue(':property_id', $propertyId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}