<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DateTimeImmutable;
use DormPlus\Api\Core\ValidationException;
use DormPlus\Api\Repositories\ContractRepository;
use DormPlus\Api\Repositories\ReportRepository;

final class ReportService
{
    /**
     * @param ReportRepository $reports
     * @param ContractRepository $contracts
     */
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly ContractRepository $contracts
    ) {
    }

    /**
     * รายรับรายเดือนของปีที่เลือก (12 เดือน) แยกตามประเภท
     *
     * @param int $propertyId
     * @param int $year
     */
    public function income(int $propertyId, int $year): array
    {
        $rows = $this->indexByMonth($this->reports->incomeByMonth($propertyId, $year), 'period_month');
        $months = [];
        $totals = ['rent' => 0.0, 'electricity' => 0.0, 'water' => 0.0, 'other' => 0.0, 'total' => 0.0];

        for ($month = 1; $month <= 12; $month++) {
            $row = $rows[$month] ?? [];
            $entry = [
                'period' => sprintf('%04d-%02d', $year, $month),
                'month' => $month,
                'rent' => (float) ($row['rent'] ?? 0),
                'electricity' => (float) ($row['electricity'] ?? 0),
                'water' => (float) ($row['water'] ?? 0),
                'other' => (float) ($row['other'] ?? 0),
                'total' => (float) ($row['total'] ?? 0),
                'payment_count' => (int) ($row['payment_count'] ?? 0)
            ];

            foreach (['rent', 'electricity', 'water', 'other', 'total'] as $key) {
                $totals[$key] += $entry[$key];
            }

            $months[] = $entry;
        }

        return [
            'year' => $year,
            'years' => $this->reports->availableYears($propertyId),
            'months' => $months,
            'totals' => $totals
        ];
    }

    /**
     * รายจ่ายรายเดือน + แยกหมวดหมู่
     *
     * @param int $propertyId
     * @param int $year
     * @param int|null $month
     */
    public function expenses(int $propertyId, int $year, ?int $month = null): array
    {
        $rows = $this->indexByMonth($this->reports->expensesByMonth($propertyId, $year), 'month');
        $months = [];
        $total = 0.0;

        for ($index = 1; $index <= 12; $index++) {
            $amount = (float) ($rows[$index]['total'] ?? 0);
            $total += $amount;

            $months[] = [
                'period' => sprintf('%04d-%02d', $year, $index),
                'month' => $index,
                'total' => $amount,
                'expense_count' => (int) ($rows[$index]['expense_count'] ?? 0)
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'years' => $this->reports->availableYears($propertyId),
            'months' => $months,
            'categories' => array_map(static fn(array $row): array => [
                'category' => $row['category'],
                'total' => (float) $row['total'],
                'expense_count' => (int) $row['expense_count']
            ], $this->reports->expensesByCategory($propertyId, $year, $month)),
            'total' => $total
        ];
    }

    /**
     * รายรับ-รายจ่าย-กำไร รายเดือน (สำหรับกราฟและตารางสรุป)
     *
     * @param int $propertyId
     * @param int $year
     */
    public function profit(int $propertyId, int $year): array
    {
        $income = $this->income($propertyId, $year);
        $expenses = $this->expenses($propertyId, $year);
        $months = [];

        foreach ($income['months'] as $index => $row) {
            $expense = $expenses['months'][$index]['total'];

            $months[] = [
                'period' => $row['period'],
                'month' => $row['month'],
                'income' => $row['total'],
                'expenses' => $expense,
                'net_profit' => $row['total'] - $expense
            ];
        }

        return [
            'year' => $year,
            'years' => $income['years'],
            'months' => $months,
            'totals' => $this->reports->yearTotals($propertyId, $year)
        ];
    }

    /**
     * @param int $propertyId
     */
    public function occupancy(int $propertyId): array
    {
        $floors = array_map(static fn(array $row): array => [
            'floor' => (int) $row['floor'],
            'total' => (int) $row['total'],
            'occupied' => (int) $row['occupied'],
            'available' => (int) $row['available'],
            'maintenance' => (int) $row['maintenance'],
            'reserved' => (int) $row['reserved'],
            'occupancy_rate' => (int) $row['total'] > 0
                ? round(((int) $row['occupied'] / (int) $row['total']) * 100, 1)
                : 0.0,
            'occupied_rent' => (float) $row['occupied_rent'],
            'potential_rent' => (float) $row['potential_rent']
        ], $this->reports->occupancyByFloor($propertyId));

        $summary = [
            'total' => 0, 'occupied' => 0, 'available' => 0,
            'maintenance' => 0, 'reserved' => 0,
            'occupied_rent' => 0.0, 'potential_rent' => 0.0
        ];

        foreach ($floors as $floor) {
            foreach (array_keys($summary) as $key) {
                $summary[$key] += $floor[$key];
            }
        }

        $summary['occupancy_rate'] = $summary['total'] > 0
            ? round(($summary['occupied'] / $summary['total']) * 100, 1)
            : 0.0;

        return ['floors' => $floors, 'summary' => $summary];
    }

    /**
     * @param int $propertyId
     * @param string|null $from
     * @param string|null $to
     * @param string $status
     */
    public function payments(int $propertyId, ?string $from, ?string $to, string $status = ''): array
    {
        [$from, $to] = $this->dateRange($from, $to);
        $rows = $this->reports->payments($propertyId, $from, $to, $status);

        $summary = ['paid' => 0.0, 'pending' => 0.0, 'cancelled' => 0.0, 'count' => count($rows)];

        foreach ($rows as $row) {
            $summary[$row['status']] += (float) $row['amount'];
        }

        return [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'items' => array_map(static fn(array $row): array => [
                 ...$row,
                'id' => (int) $row['id'],
                'amount' => (float) $row['amount'],
                'tenant_name' => trim($row['first_name'].' '.$row['last_name'])
            ], $rows),
            'summary' => $summary
        ];
    }

    /**
     * @param int $propertyId
     * @param string $status
     */
    public function tenants(int $propertyId, string $status = ''): array
    {
        $rows = $this->reports->tenants($propertyId, $status);

        return [
            'status' => $status,
            'items' => array_map(static fn(array $row): array => [
                 ...$row,
                'id' => (int) $row['id'],
                'tenant_name' => trim($row['first_name'].' '.$row['last_name']),
                'monthly_rent' => $row['monthly_rent'] !== null ? (float) $row['monthly_rent'] : null,
                'total_paid' => (float) $row['total_paid'],
                'total_pending' => (float) $row['total_pending']
            ], $rows),
            'summary' => [
                'count' => count($rows),
                'with_room' => count(array_filter($rows, static fn(array $row): bool => $row['room_number'] !== null))
            ]
        ];
    }

    /**
     * @param int $propertyId
     * @param string|null $from
     * @param string|null $to
     */
    public function maintenance(int $propertyId, ?string $from, ?string $to): array
    {
        [$from, $to] = $this->dateRange($from, $to);
        $rows = $this->reports->maintenance($propertyId, $from, $to);

        $summary = [
            'count' => count($rows),
            'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0,
            'total_cost' => 0.0
        ];

        foreach ($rows as $row) {
            $summary[$row['status']]++;
            $summary['total_cost'] += (float) $row['cost'];
        }

        return [
            'from' => $from,
            'to' => $to,
            'items' => array_map(static fn(array $row): array => [
                 ...$row,
                'id' => (int) $row['id'],
                'cost' => (float) $row['cost']
            ], $rows),
            'summary' => $summary
        ];
    }

    /**
     * สัญญาที่จะหมดอายุภายใน N วัน (รวมที่เลยกำหนดแล้วแต่ยัง active)
     *
     * @param int $propertyId
     * @param int $days
     */
    public function contracts(int $propertyId, int $days): array
    {
        $rows = $this->contracts->expiringWithin($propertyId, $days);

        return [
            'days' => $days,
            'items' => array_map(static fn(array $row): array => [
                 ...$row,
                'id' => (int) $row['id'],
                'monthly_rent' => (float) $row['monthly_rent'],
                'days_remaining' => (int) $row['days_remaining'],
                'tenant_name' => trim($row['first_name'].' '.$row['last_name'])
            ], $rows),
            'summary' => [
                'count' => count($rows),
                'overdue' => count(array_filter($rows, static fn(array $row): bool => (int) $row['days_remaining'] < 0))
            ]
        ];
    }

    /**
     * @param string|null $from
     * @param string|null $to
     * @return array{0: string, 1: string}
     */
    private function dateRange(?string $from, ?string $to): array
    {
        $now = new DateTimeImmutable();
        $from = $from ?: $now->modify('first day of this month')->format('Y-m-d');
        $to = $to ?: $now->modify('last day of this month')->format('Y-m-d');

        foreach (['from' => $from, 'to' => $to] as $key => $value) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            if ($date === false || $date->format('Y-m-d') !== $value) {
                throw new ValidationException([$key => 'รูปแบบวันที่ไม่ถูกต้อง (YYYY-MM-DD)']);
            }
        }

        if ($from > $to) {
            throw new ValidationException(['to' => 'วันสิ้นสุดต้องไม่ก่อนวันเริ่มต้น']);
        }

        return [$from, $to];
    }

    /**
     * @param array $rows
     * @param string $key
     */
    private function indexByMonth(array $rows, string $key): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row[$key]] = $row;
        }

        return $indexed;
    }
}
