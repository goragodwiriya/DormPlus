<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\MaintenanceController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var MaintenanceController $maintenanceController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/maintenance', [$maintenanceController, 'index'], $auth);
$router->get('/api/maintenance/{id}', [$maintenanceController, 'show'], $auth);
$router->post('/api/maintenance', [$maintenanceController, 'store'], $write);
$router->put('/api/maintenance/{id}', [$maintenanceController, 'update'], $write);
$router->put('/api/maintenance/{id}/status', [$maintenanceController, 'patch'], $write);
$router->delete('/api/maintenance/{id}', [$maintenanceController, 'destroy'], $write);
