<?php

declare (strict_types = 1);

namespace DormPlus\Api\Middleware;

use DormPlus\Api\Core\Request;
use DormPlus\Api\Core\Response;

final class CsrfMiddleware
{
    /**
     * @param Request $request
     */
    public function __invoke(Request $request): void
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $requestToken = $request->header('X-CSRF-Token') ?? '';

        if (
            $sessionToken === ''
            || $requestToken === ''
            || !hash_equals($sessionToken, $requestToken)
        ) {
            Response::error(
                'CSRF_TOKEN_MISMATCH',
                'เซสชันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
                419
            );
        }
    }
}