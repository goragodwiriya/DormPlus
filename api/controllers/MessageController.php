<?php

declare (strict_types = 1);

namespace DormPlus\Api\Controllers;

use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;
use DormPlus\Api\Services\MessageService;

final class MessageController extends Controller
{
    /**
     * @param MessageService $messages
     */
    public function __construct(private readonly MessageService $messages)
    {
    }

    /**
     * @param Request $request
     */
    public function threads(Request $request): never
    {
        Response::success($this->messages->threads(
            $this->propertyId(),
            $this->userId(),
            mb_substr(trim((string) $request->query('q', '')), 0, 120)
        ));
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function thread(Request $request, array $parameters): never
    {
        Response::success(
            $this->messages->thread($this->propertyId(), $this->userId(), $this->id($parameters))
        );
    }

    /**
     * @param Request $request
     */
    public function createThread(Request $request): never
    {
        Response::success(
            $this->messages->createThread($this->propertyId(), $this->userId(), $request->json()),
            201
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function reply(Request $request, array $parameters): never
    {
        Response::success(
            $this->messages->reply(
                $this->propertyId(),
                $this->userId(),
                $this->id($parameters),
                $request->json()
            ),
            201
        );
    }

    /**
     * @param Request $request
     * @param array $parameters
     */
    public function markRead(Request $request, array $parameters): never
    {
        Response::success(
            $this->messages->markRead($this->propertyId(), $this->userId(), $this->id($parameters))
        );
    }

    /**
     * @param Request $request
     */
    public function unreadCount(Request $request): never
    {
        Response::success([
            'unread_count' => $this->messages->unreadCount($this->propertyId(), $this->userId())
        ]);
    }

    /**
     * @param Request $request
     */
    public function recipients(Request $request): never
    {
        Response::success($this->messages->recipients($this->propertyId(), $this->userId()));
    }
}
