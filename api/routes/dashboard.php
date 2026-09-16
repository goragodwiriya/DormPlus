<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\DashboardController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;

/** @var Router $router */
/** @var DashboardController $dashboardController */

$authMiddleware = new AuthMiddleware();

$router->get(
    '/api/dashboard',
    [$dashboardController, 'index'],
    [$authMiddleware]
);

$router->get(
    '/api/dashboard/statistics',
    [$dashboardController, 'statistics'],
    [$authMiddleware]
);

$router->get(
    '/api/dashboard/financial-summary',
    [$dashboardController, 'financialSummary'],
    [$authMiddleware]
);

$router->get(
    '/api/dashboard/financial-chart',
    [$dashboardController, 'financialChart'],
    [$authMiddleware]
);

$router->get(
    '/api/dashboard/room-status',
    [$dashboardController, 'roomStatus'],
    [$authMiddleware]
);

$router->get(
    '/api/dashboard/notifications',
    [$dashboardController, 'notifications'],
    [$authMiddleware]
);
