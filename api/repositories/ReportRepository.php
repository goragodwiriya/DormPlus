<?php

declare (strict_types = 1);

namespace DormPlus\Api\Repositories;

use PDO;

/**
 * คิวรีสรุปสำหรับหน้ารายงาน — ทุกตัวเลขคำนวณจากฐานข้อมูลจริง
 */
final class ReportRepository
{
    /**
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * รายรับรายเดือนแยกตามประเภท (เฉพาะที่ชำระแล้ว)
     *
     * @param int $propertyId
     * @param int $year
     */
    public function incomeByMonth(int $propertyId, int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                period_month,
                COALESCE(SUM(CASE WHEN payment_type = 'rent' THEN amount ELSE 0 END), 0) AS rent,
                COALESCE(SUM(CASE WHEN payment_type = 'electricity' THEN amount ELSE 0 END), 0) AS electricity,
                COALESCE(SUM(CASE WHEN payment_type = 'water' THEN amount ELSE 0 END), 0) AS water,
                COALESCE(SUM(CASE WHEN payment_type = 'other' THEN amount ELSE 0 END), 0) AS other,
                COALESCE(SUM(amount), 0) AS total,
                COUNT(*) AS payment_count
             FROM payments
             WHERE property_id = :property_id
               AND status = 'paid'
               AND period_year = :year
             GROUP BY period_month
             ORDER BY period_month ASC"
        );

        $statement->execute(['property_id' => $propertyId, 'year' => $year]);

        return $statement->fetchAll();
    }

    /**
     * รายจ่ายรายเดือน
     *
     * @param int $propertyId
     * @param int $year
     */
    public function expensesByMonth(int $propertyId, int $year): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                CAST(substr(expense_date, 6, 2) AS INTEGER) AS month,
                COALESCE(SUM(amount), 0) AS total,
                COUNT(*) AS expense_count
             FROM expenses
             WHERE property_id = :property_id
               AND substr(expense_date, 1, 4) = :year
             GROUP BY month
             ORDER BY month ASC"
        );

        $statement->execute(['property_id' => $propertyId, 'year' => (string) $year]);

        return $statement->fetchAll();
    }

    /**
     * รายจ่ายแยกตามหมวดหมู่
     *
     * @param int $propertyId
     * @param int $year
     * @param int|null $month
     */
    public function expensesByCategory(int $propertyId, int $year, ?int $month = null): array
    {
        $statement = $this->pdo->prepare(
            "SELECT category, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS expense_count
             FROM expenses
             WHERE property_id = :property_id
               AND substr(expense_date, 1, 4) = :year
               AND (:month IS NULL OR CAST(substr(expense_date, 6, 2) AS INTEGER) = :month)
             GROUP BY category
             ORDER BY total DESC"
        );

        $statement->execute([
            'property_id' => $propertyId,
            'year' => (string) $year,
            'month' => $month
        ]);

        return $statement->fetchAll();
    }

    /**
     * อัตราการเข้าพักแยกตามชั้น
     *
     * @param int $propertyId
     */
    public function occupancyByFloor(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                floor,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) AS occupied,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved,
                COALESCE(SUM(CASE WHEN status = 'occupied' THEN monthly_rent ELSE 0 END), 0)
                    AS occupied_rent,
                COALESCE(SUM(monthly_rent), 0) AS potential_rent
             FROM rooms
             WHERE property_id = :property_id
             GROUP BY floor
             ORDER BY floor ASC"
        );

        $statement->execute(['property_id' => $propertyId]);

        return $statement->fetchAll();
    }

    /**
     * รายการชำระเงินในช่วงวันที่ (ตามวันที่ชำระ)
     *
     * @param int $propertyId
     * @param string $from
     * @param string $to
     * @param string $status
     */
    public function payments(int $propertyId, string $from, string $to, string $status = ''): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                p.id, p.amount, p.payment_type, p.payment_date,
                p.period_month, p.period_year, p.reference, p.status,
                r.room_number, t.first_name, t.last_name
             FROM payments p
             INNER JOIN rooms r ON r.id = p.room_id
             INNER JOIN tenants t ON t.id = p.tenant_id
             WHERE p.property_id = :property_id
               AND (
                    (p.payment_date IS NOT NULL
                     AND date(p.payment_date) BETWEEN date(:from) AND date(:to))
                    OR (p.payment_date IS NULL
                        AND date(p.created_at) BETWEEN date(:from_created) AND date(:to_created))
               )
               AND (:status = '' OR p.status = :status)
             ORDER BY datetime(COALESCE(p.payment_date, p.created_at)) DESC, p.id DESC"
        );

        $statement->execute([
            'property_id' => $propertyId,
            'from' => $from,
            'to' => $to,
            'from_created' => $from,
            'to_created' => $to,
            'status' => $status
        ]);

        return $statement->fetchAll();
    }

    /**
     * ผู้เช่าทั้งหมดพร้อมห้องและสัญญาปัจจุบัน
     *
     * @param int $propertyId
     * @param string $status
     */
    public function tenants(int $propertyId, string $status = ''): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                t.id, t.first_name, t.last_name, t.gender, t.phone, t.status,
                t.created_at,
                r.room_number, r.floor,
                c.start_date, c.end_date, c.monthly_rent,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                  WHERE p.tenant_id = t.id AND p.status = 'paid') AS total_paid,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                  WHERE p.tenant_id = t.id AND p.status = 'pending') AS total_pending
             FROM tenants t
             LEFT JOIN contracts c
                ON c.tenant_id = t.id AND c.status = 'active'
               AND c.id = (SELECT MAX(c2.id) FROM contracts c2
                           WHERE c2.tenant_id = t.id AND c2.status = 'active')
             LEFT JOIN rooms r ON r.id = c.room_id
             WHERE t.property_id = :property_id
               AND (:status = '' OR t.status = :status)
             ORDER BY r.room_number ASC, t.first_name ASC"
        );

        $statement->execute(['property_id' => $propertyId, 'status' => $status]);

        return $statement->fetchAll();
    }

    /**
     * งานซ่อมในช่วงวันที่ (ตามวันที่แจ้ง)
     *
     * @param int $propertyId
     * @param string $from
     * @param string $to
     */
    public function maintenance(int $propertyId, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                m.id, m.title, m.priority, m.status, m.assigned_to,
                m.reported_at, m.completed_at, m.cost,
                r.room_number, r.floor
             FROM maintenance_requests m
             INNER JOIN rooms r ON r.id = m.room_id
             WHERE m.property_id = :property_id
               AND date(m.reported_at) BETWEEN date(:from) AND date(:to)
             ORDER BY datetime(m.reported_at) DESC'
        );

        $statement->execute([
            'property_id' => $propertyId,
            'from' => $from,
            'to' => $to
        ]);

        return $statement->fetchAll();
    }

    /**
     * ยอดรวมของปีสำหรับ header รายงาน
     *
     * @param int $propertyId
     * @param int $year
     */
    public function yearTotals(int $propertyId, int $year): array
    {
        $incomeStatement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE property_id = :property_id AND status = 'paid' AND period_year = :year"
        );
        $incomeStatement->execute(['property_id' => $propertyId, 'year' => $year]);

        $expenseStatement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses
             WHERE property_id = :property_id AND substr(expense_date, 1, 4) = :year'
        );
        $expenseStatement->execute(['property_id' => $propertyId, 'year' => (string) $year]);

        $income = (float) $incomeStatement->fetchColumn();
        $expenses = (float) $expenseStatement->fetchColumn();

        return [
            'income' => $income,
            'expenses' => $expenses,
            'net_profit' => $income - $expenses
        ];
    }

    /**
     * ปีที่มีข้อมูลการเงิน สำหรับตัวเลือกปี
     *
     * @param int $propertyId
     */
    public function availableYears(int $propertyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT year FROM (
                SELECT period_year AS year FROM payments WHERE property_id = :payment_property
                UNION
                SELECT CAST(substr(expense_date, 1, 4) AS INTEGER) FROM expenses
                 WHERE property_id = :expense_property
             ) ORDER BY year DESC'
        );

        $statement->execute([
            'payment_property' => $propertyId,
            'expense_property' => $propertyId
        ]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
