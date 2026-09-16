<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Pagination;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\ContractService;

final class ContractController extends Controller
{
    /**
     * @param ContractService $contracts
     */
    public function __construct(private readonly ContractService $contracts)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        $pagination = Pagination::fromRequest($request);
        $result = $this->contracts->list(
            $this->propertyId(),
            $this->filters($request, [
                'q', 'status', 'room_id', 'tenant_id', 'expiring_within', 'from', 'to', 'sort'
            ]),
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
     */
    public function expiring(Request $request): never
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT, [
            'options' => ['default' => 30, 'min_range' => 1, 'max_range' => 365]
        ]);

        Response::success($this->contracts->expiring($this->propertyId(), (int) $days));
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function show(Request $request, array $parameters): never
    {
        Response::success($this->contracts->get($this->propertyId(), $this->id($parameters)));
    }

    /**
     * @param Request $request
     */
    public function store(Request $request): never
    {
        Response::success(
            $this->contracts->create($this->propertyId(), $request->json()),
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
            $this->contracts->update($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function end(Request $request, array $parameters): never
    {
        Response::success(
            $this->contracts->end($this->propertyId(), $this->id($parameters), $request->json())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function renew(Request $request, array $parameters): never
    {
        Response::success(
            $this->contracts->renew($this->propertyId(), $this->id($parameters), $request->json()),
            201
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function destroy(Request $request, array $parameters): never
    {
        $this->contracts->delete($this->propertyId(), $this->id($parameters));

        Response::success(['message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    }
}
