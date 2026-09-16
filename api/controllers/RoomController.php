<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Pagination;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\RoomService;

final class RoomController extends Controller
{
    /**
     * @param RoomService $rooms
     */
    public function __construct(private readonly RoomService $rooms)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        $pagination = Pagination::fromRequest($request);
        $result = $this->rooms->list(
            $this->propertyId(),
            $this->filters($request, ['q', 'status', 'floor', 'sort']),
            $pagination->perPage,
            $pagination->offset()
        );

        Response::success($result['items'], 200, $pagination->toArray($result['total']));
    }

    /**
     * @param Request $request
     */
    public function options(Request $request): never
    {
        Response::success($this->rooms->options($this->propertyId()));
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function show(Request $request, array $parameters): never
    {
        Response::success(
            $this->rooms->detail($this->propertyId(), $this->id($parameters))
        );
    }

    /**
     * @param Request $request
     */
    public function store(Request $request): never
    {
        Response::success(
            $this->rooms->create($this->propertyId(), $request->json()),
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
            $this->rooms->update($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function updateStatus(Request $request, array $parameters): never
    {
        Response::success(
            $this->rooms->changeStatus($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function destroy(Request $request, array $parameters): never
    {
        $this->rooms->delete($this->propertyId(), $this->id($parameters));

        Response::success(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }
}
