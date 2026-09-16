<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DateTimeImmutable;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\ReportService;

final class ReportController extends Controller
{
    /**
     * @param ReportService $reports
     */
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * @param Request $request
     */
    public function income(Request $request): never
    {
        Response::success($this->reports->income($this->propertyId(), $this->year($request)));
    }

    /**
     * @param Request $request
     */
    public function expenses(Request $request): never
    {
        $month = filter_var($request->query('month'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 12]
        ]);

        Response::success($this->reports->expenses(
            $this->propertyId(),
            $this->year($request),
            $month === false ? null : $month
        ));
    }

    /**
     * @param Request $request
     */
    public function profit(Request $request): never
    {
        Response::success($this->reports->profit($this->propertyId(), $this->year($request)));
    }

    /**
     * @param Request $request
     */
    public function occupancy(Request $request): never
    {
        Response::success($this->reports->occupancy($this->propertyId()));
    }

    /**
     * @param Request $request
     */
    public function payments(Request $request): never
    {
        Response::success($this->reports->payments(
            $this->propertyId(),
            $this->dateQuery($request, 'from'),
            $this->dateQuery($request, 'to'),
            mb_substr(trim((string) $request->query('status', '')), 0, 20)
        ));
    }

    /**
     * @param Request $request
     */
    public function tenants(Request $request): never
    {
        Response::success($this->reports->tenants(
            $this->propertyId(),
            mb_substr(trim((string) $request->query('status', '')), 0, 20)
        ));
    }

    /**
     * @param Request $request
     */
    public function maintenance(Request $request): never
    {
        Response::success($this->reports->maintenance(
            $this->propertyId(),
            $this->dateQuery($request, 'from'),
            $this->dateQuery($request, 'to')
        ));
    }

    /**
     * @param Request $request
     */
    public function contracts(Request $request): never
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT, [
            'options' => ['default' => 30, 'min_range' => 1, 'max_range' => 365]
        ]);

        Response::success($this->reports->contracts($this->propertyId(), (int) $days));
    }

    /**
     * @param Request $request
     */
    private function year(Request $request): int
    {
        $current = (int) (new DateTimeImmutable())->format('Y');
        $year = filter_var($request->query('year', $current), FILTER_VALIDATE_INT, [
            'options' => ['default' => $current, 'min_range' => 2000, 'max_range' => 2200]
        ]);

        return (int) $year;
    }

    /**
     * @param Request $request
     * @param string $key
     */
    private function dateQuery(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return $value === '' ? null : mb_substr($value, 0, 10);
    }
}
