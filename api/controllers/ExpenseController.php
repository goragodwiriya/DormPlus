<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Pagination;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Core\ValidationException;
use DormPlus\Api\Services\ExpenseService;

final class ExpenseController extends Controller
{
    /**
     * @param ExpenseService $expenses
     */
    public function __construct(private readonly ExpenseService $expenses)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        $pagination = Pagination::fromRequest($request);
        $filters = $this->filters($request, ['q', 'month', 'category']);

        if (($filters['month'] ?? '') !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $filters['month'])) {
            throw new ValidationException(['month' => 'รูปแบบเดือนต้องเป็น YYYY-MM']);
        }

        $result = $this->expenses->list(
            $this->propertyId(),
            $filters,
            $pagination->perPage,
            $pagination->offset()
        );

        Response::success(
            ['items' => $result['items'], 'month_total' => $result['month_total'] ?? null],
            200,
            $pagination->toArray($result['total'])
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function show(Request $request, array $parameters): never
    {
        Response::success($this->expenses->get($this->propertyId(), $this->id($parameters)));
    }

    /**
     * @param Request $request
     */
    public function store(Request $request): never
    {
        Response::success($this->expenses->create($this->propertyId(), $request->json()), 201);
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function update(Request $request, array $parameters): never
    {
        Response::success(
            $this->expenses->update($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function destroy(Request $request, array $parameters): never
    {
        $this->expenses->delete($this->propertyId(), $this->id($parameters));

        Response::success(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }
}
