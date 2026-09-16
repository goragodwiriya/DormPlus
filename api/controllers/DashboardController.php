<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\DashboardService;

final class DashboardController
{
    /**
     * @param DashboardService $dashboard
     */
    public function __construct(
        private readonly DashboardService $dashboard
    ) {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        Response::success(
            $this->dashboard->dashboard(
                $this->propertyId(),
                $this->userId()
            )
        );
    }

    /**
     * @param Request $request
     */
    public function statistics(Request $request): never
    {
        Response::success(
            $this->dashboard->statistics($this->propertyId())
        );
    }

    /**
     * @param Request $request
     */
    public function financialSummary(Request $request): never
    {
        Response::success(
            $this->dashboard->financialSummary($this->propertyId())
        );
    }

    /**
     * @param Request $request
     */
    public function financialChart(Request $request): never
    {
        Response::success(
            $this->dashboard->financialChart($this->propertyId())
        );
    }

    /**
     * @param Request $request
     */
    public function roomStatus(Request $request): never
    {
        Response::success(
            $this->dashboard->roomStatus($this->propertyId())
        );
    }

    /**
     * @param Request $request
     */
    public function notifications(Request $request): never
    {
        $limit = filter_var(
            $request->query('limit', 6),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => 6,
                    'min_range' => 1,
                    'max_range' => 20
                ]
            ]
        );

        Response::success(
            $this->dashboard->notifications(
                $this->propertyId(),
                $this->userId(),
                $limit
            )
        );
    }

    /**
     * @param Request $request
     */
    public function latestRooms(Request $request): never
    {
        $limit = $this->limit($request, 5);

        Response::success(
            $this->dashboard->latestRooms(
                $this->propertyId(),
                $limit
            )
        );
    }

    /**
     * @param Request $request
     */
    public function latestPayments(Request $request): never
    {
        $limit = $this->limit($request, 5);

        Response::success(
            $this->dashboard->latestPayments(
                $this->propertyId(),
                $limit
            )
        );
    }

    private function propertyId(): int
    {
        return (int) $_SESSION['property_id'];
    }

    private function userId(): int
    {
        return (int) $_SESSION['user_id'];
    }

    /**
     * @param Request $request
     * @param int $default
     */
    private function limit(Request $request, int $default): int
    {
        $limit = filter_var(
            $request->query('limit', $default),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => $default,
                    'min_range' => 1,
                    'max_range' => 20
                ]
            ]
        );

        return (int) $limit;
    }
}