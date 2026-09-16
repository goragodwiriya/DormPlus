<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Pagination;
use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\NotificationService;

final class NotificationController extends Controller
{
    /**
     * @param NotificationService $notifications
     */
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * @param Request $request
     */
    public function index(Request $request): never
    {
        $pagination = Pagination::fromRequest($request);
        $result = $this->notifications->list(
            $this->propertyId(),
            $this->userId(),
            $this->filters($request, ['unread', 'type']),
            $pagination->perPage,
            $pagination->offset()
        );

        Response::success(
            ['items' => $result['items'], 'unread_count' => $result['unread_count']],
            200,
            $pagination->toArray($result['total'])
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function markRead(Request $request, array $parameters): never
    {
        Response::success(
            $this->notifications->markRead($this->propertyId(), $this->userId(), $this->id($parameters))
        );
    }

    /**
     * @param Request $request
     */
    public function markAllRead(Request $request): never
    {
        Response::success(
            $this->notifications->markAllRead($this->propertyId(), $this->userId())
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function destroy(Request $request, array $parameters): never
    {
        $this->notifications->delete($this->propertyId(), $this->userId(), $this->id($parameters));

        Response::success(['message' => 'ลบการแจ้งเตือนแล้ว']);
    }
}
