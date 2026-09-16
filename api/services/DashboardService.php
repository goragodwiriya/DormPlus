<?php

declare (strict_types = 1);

namespace DormPlus\Api\Services;

use DateTimeImmutable;
use DormPlus\Api\Repositories\DashboardRepository;

final class DashboardService
{
    /**
     * @param DashboardRepository $dashboard
     */
    public function __construct(
        private readonly DashboardRepository $dashboard
    ) {
    }

    /**
     * @param int $propertyId
     */
    public function statistics(int $propertyId): array
    {
        $currentMonth = (new DateTimeImmutable())->format('Y-m');
        $previousMonth = (new DateTimeImmutable('first day of last month'))
            ->format('Y-m');

        $statistics = $this->dashboard->statistics(
            $propertyId,
            $currentMonth
        );

        $financial = $this->dashboard->financialTotals(
            $propertyId,
            $currentMonth,
            $previousMonth
        );

        $currentIncome = (float) $financial['income']['current_total'];
        $previousIncome = (float) $financial['income']['previous_total'];

        return [
            'rooms' => $this->integerValues($statistics['rooms']),
            'tenants' => $this->integerValues($statistics['tenants']),
            'monthly_income' => [
                'total' => $currentIncome,
                'change_percentage' => $this->percentageChange(
                    $currentIncome,
                    $previousIncome
                )
            ],
            'maintenance' => $this->integerValues(
                $statistics['maintenance']
            )
        ];
    }

    /**
     * @param int $propertyId
     */
    public function financialSummary(int $propertyId): array
    {
        $now = new DateTimeImmutable();
        $currentMonth = $now->format('Y-m');
        $previousMonth = $now
            ->modify('first day of last month')
            ->format('Y-m');

        $totals = $this->dashboard->financialTotals(
            $propertyId,
            $currentMonth,
            $previousMonth
        );

        $income = (float) $totals['income']['current_total'];
        $previousIncome = (float) $totals['income']['previous_total'];
        $expenses = (float) $totals['expenses']['current_total'];
        $previousExpenses = (float) $totals['expenses']['previous_total'];
        $profit = $income - $expenses;
        $previousProfit = $previousIncome - $previousExpenses;

        return [
            'period' => $currentMonth,
            'income' => [
                'total' => $income,
                'change_percentage' => $this->percentageChange(
                    $income,
                    $previousIncome
                )
            ],
            'expenses' => [
                'total' => $expenses,
                'change_percentage' => $this->percentageChange(
                    $expenses,
                    $previousExpenses
                )
            ],
            'net_profit' => [
                'total' => $profit,
                'change_percentage' => $this->percentageChange(
                    $profit,
                    $previousProfit
                )
            ]
        ];
    }

    /**
     * @param int $propertyId
     */
    public function financialChart(int $propertyId): array
    {
        $current = new DateTimeImmutable('first day of this month');
        $first = $current->modify('-3 months');
        $end = $current->modify('+1 month');

        $rows = $this->dashboard->monthlyFinancialData(
            $propertyId,
            $first->format('Y-m'),
            $end->format('Y-m')
        );

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[$row['period']] = [
                'income' => (float) $row['income'],
                'expenses' => (float) $row['expenses']
            ];
        }

        $result = [];

        for ($index = 0; $index < 4; $index++) {
            $period = $first->modify("+{$index} months");
            $key = $period->format('Y-m');

            $result[] = [
                'period' => $key,
                'income' => $indexed[$key]['income'] ?? 0,
                'expenses' => $indexed[$key]['expenses'] ?? 0,
                'is_current' => $key === $current->format('Y-m')
            ];
        }

        return $result;
    }

    /**
     * @param int $propertyId
     */
    public function roomStatus(int $propertyId): array
    {
        $status = $this->integerValues(
            $this->dashboard->roomStatus($propertyId)
        );

        $total = max(1, $status['total']);

        return [
             ...$status,
            'occupied_percentage' => round(
                ($status['occupied'] / $total) * 100,
                1
            ),
            'available_percentage' => round(
                ($status['available'] / $total) * 100,
                1
            )
        ];
    }

    /**
     * @param int $propertyId
     * @param int $userId
     * @param int $limit
     */
    public function notifications(
        int $propertyId,
        int $userId,
        int $limit = 6
    ): array {
        return [
            'items' => $this->dashboard->notifications(
                $propertyId,
                $userId,
                min(max($limit, 1), 20)
            ),
            'unread_count' => $this->dashboard->unreadNotificationCount(
                $propertyId,
                $userId
            )
        ];
    }

    /**
     * @param int $propertyId
     * @param int $limit
     */
    public function latestRooms(int $propertyId, int $limit = 5): array
    {
        $rooms = $this->dashboard->latestRooms(
            $propertyId,
            min(max($limit, 1), 20)
        );

        return array_map(static function (array $room): array {
            return [
                 ...$room,
                'id' => (int) $room['id'],
                'floor' => (int) $room['floor'],
                'monthly_rent' => (float) $room['monthly_rent'],
                'tenant_id' => $room['tenant_id'] !== null
                    ? (int) $room['tenant_id']
                    : null
            ];
        }, $rooms);
    }

    /**
     * @param int $propertyId
     * @param int $limit
     */
    public function latestPayments(int $propertyId, int $limit = 5): array
    {
        $payments = $this->dashboard->latestPayments(
            $propertyId,
            min(max($limit, 1), 20)
        );

        return array_map(static function (array $payment): array {
            return [
                 ...$payment,
                'id' => (int) $payment['id'],
                'room_id' => (int) $payment['room_id'],
                'tenant_id' => (int) $payment['tenant_id'],
                'amount' => (float) $payment['amount']
            ];
        }, $payments);
    }

    /**
     * @param int $propertyId
     * @param int $userId
     */
    public function dashboard(int $propertyId, int $userId): array
    {
        return [
            'statistics' => $this->statistics($propertyId),
            'financial_summary' => $this->financialSummary($propertyId),
            'financial_chart' => $this->financialChart($propertyId),
            'room_status' => $this->roomStatus($propertyId),
            'notifications' => $this->notifications(
                $propertyId,
                $userId,
                4
            ),
            'latest_rooms' => $this->latestRooms($propertyId),
            'latest_payments' => $this->latestPayments($propertyId)
        ];
    }

    /**
     * @param float $current
     * @param float $previous
     * @return mixed
     */
    private function percentageChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /**
     * @param array $data
     */
    private function integerValues(array $data): array
    {
        return array_map(
            static fn(mixed $value): int => (int) $value,
            $data
        );
    }
}