<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\AuthController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var AuthController $authController */

$authMiddleware = new AuthMiddleware();
$csrfMiddleware = new CsrfMiddleware();

$router->post(
    '/api/auth/login',
    [$authController, 'login']
);

$router->get(
    '/api/auth/me',
    [$authController, 'currentUser'],
    [$authMiddleware]
);

$router->post(
    '/api/auth/logout',
    [$authController, 'logout'],
    [$authMiddleware, $csrfMiddleware]
);

$router->put(
    '/api/auth/password',
    [$authController, 'changePassword'],
    [$authMiddleware, $csrfMiddleware]
);

$router->put(
    '/api/auth/profile',
    [$authController, 'updateProfile'],
    [$authMiddleware, $csrfMiddleware]
);
