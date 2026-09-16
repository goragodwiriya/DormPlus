<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Pagination;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\MaintenanceService;

final class MaintenanceController extends Controller
{
    /**
     * @param MaintenanceService $maintenance
     */
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        $pagination = Pagination::fromRequest($request);
        $result = $this->maintenance->list(
            $this->propertyId(),
            $this->filters($request, ['q', 'status', 'priority', 'room_id', 'open', 'from', 'to', 'sort']),
            $pagination->perPage,
            $pagination->offset()
        );

        Response::success(
            ['items' => $result['items'], 'status_counts' => $result['status_counts']],
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
        Response::success($this->maintenance->get($this->propertyId(), $this->id($parameters)));
    }

    /**
     * @param Request $request
     */
    public function store(Request $request): never
    {
        Response::success(
            $this->maintenance->create($this->propertyId(), $request->json()),
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
            $this->maintenance->update($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function patch(Request $request, array $parameters): never
    {
        Response::success(
            $this->maintenance->patch($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function destroy(Request $request, array $parameters): never
    {
        $this->maintenance->delete($this->propertyId(), $this->id($parameters));

        Response::success(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }
}
