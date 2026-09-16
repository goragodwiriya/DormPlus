<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DateTimeImmutable;
use DormPlus\Api\Core\Pagination;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Core\ValidationException;
use DormPlus\Api\Services\PaymentService;

final class PaymentController extends Controller
{
    /**
     * @param PaymentService $payments
     */
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        $pagination = Pagination::fromRequest($request);
        $filters = $this->filters($request, [
            'q', 'month', 'room_id', 'tenant_id', 'status', 'type', 'sort'
        ]);

        if (($filters['month'] ?? '') !== '') {
            $filters['month'] = $this->month($filters['month']);
        }

        $result = $this->payments->list(
            $this->propertyId(),
            $filters,
            $pagination->perPage,
            $pagination->offset()
        );

        Response::success($result['items'], 200, $pagination->toArray($result['total']));
    }

    /**
     * @param Request $request
     */
    public function summary(Request $request): never
    {
        $month = $this->month(
            (string) ($request->query('month') ?: (new DateTimeImmutable())->format('Y-m'))
        );

        Response::success($this->payments->summary($this->propertyId(), $month));
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function show(Request $request, array $parameters): never
    {
        Response::success($this->payments->get($this->propertyId(), $this->id($parameters)));
    }

    /**
     * @param Request $request
     */
    public function store(Request $request): never
    {
        Response::success(
            $this->payments->create($this->propertyId(), $request->json()),
            201
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function update(Request $request, array $parameters): never
    {
        Response::success(
            $this->payments->update($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function cancel(Request $request, array $parameters): never
    {
        Response::success(
            $this->payments->cancel($this->propertyId(), $this->id($parameters))
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function destroy(Request $request, array $parameters): never
    {
        $this->payments->delete($this->propertyId(), $this->id($parameters));

        Response::success(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }

    /**
     * @param string $value
     */
    private function month(string $value): string
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            throw new ValidationException(['month' => 'รูปแบบเดือนต้องเป็น YYYY-MM']);
        }

        return $value;
    }
}
