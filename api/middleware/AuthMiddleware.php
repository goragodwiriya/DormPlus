<?php

declare (strict_types = 1);

namespace DormPlus\Api\Middleware;

use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;

final class AuthMiddleware
{
    /**
     * @param Request $request
     */
    public function __invoke(Request $request): void
    {
        if (!isset($_SESSION['user_id'], $_SESSION['property_id'])) {
            Response::error(
                'UNAUTHORIZED',
                'กรุณาเข้าสู่ระบบ',
                401
            );
        }
    }
}