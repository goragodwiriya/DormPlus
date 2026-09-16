<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\ContractController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var ContractController $contractController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/contracts', [$contractController, 'index'], $auth);
$router->get('/api/contracts/expiring', [$contractController, 'expiring'], $auth);
$router->get('/api/contracts/{id}', [$contractController, 'show'], $auth);
$router->post('/api/contracts', [$contractController, 'store'], $write);
$router->put('/api/contracts/{id}', [$contractController, 'update'], $write);
$router->put('/api/contracts/{id}/end', [$contractController, 'end'], $write);
$router->post('/api/contracts/{id}/renew', [$contractController, 'renew'], $write);
$router->delete('/api/contracts/{id}', [$contractController, 'destroy'], $write);
