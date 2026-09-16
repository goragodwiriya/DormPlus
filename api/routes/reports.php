<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\ReportController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;

/** @var Router $router */
/** @var ReportController $reportController */

$auth = [new AuthMiddleware()];

$router->get('/api/reports/income', [$reportController, 'income'], $auth);
$router->get('/api/reports/expenses', [$reportController, 'expenses'], $auth);
$router->get('/api/reports/profit', [$reportController, 'profit'], $auth);
$router->get('/api/reports/occupancy', [$reportController, 'occupancy'], $auth);
$router->get('/api/reports/payments', [$reportController, 'payments'], $auth);
$router->get('/api/reports/tenants', [$reportController, 'tenants'], $auth);
$router->get('/api/reports/maintenance', [$reportController, 'maintenance'], $auth);
$router->get('/api/reports/contracts', [$reportController, 'contracts'], $auth);
