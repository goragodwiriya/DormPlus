<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\TenantController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var TenantController $tenantController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/tenants', [$tenantController, 'index'], $auth);
$router->get('/api/tenants/options', [$tenantController, 'options'], $auth);
$router->get('/api/tenants/{id}', [$tenantController, 'show'], $auth);
$router->post('/api/tenants', [$tenantController, 'store'], $write);
$router->put('/api/tenants/{id}', [$tenantController, 'update'], $write);
$router->delete('/api/tenants/{id}', [$tenantController, 'destroy'], $write);
