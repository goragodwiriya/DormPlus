<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\RoomController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var RoomController $roomController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/rooms', [$roomController, 'index'], $auth);
$router->get('/api/rooms/options', [$roomController, 'options'], $auth);
$router->get('/api/rooms/{id}', [$roomController, 'show'], $auth);
$router->post('/api/rooms', [$roomController, 'store'], $write);
$router->put('/api/rooms/{id}', [$roomController, 'update'], $write);
$router->put('/api/rooms/{id}/status', [$roomController, 'updateStatus'], $write);
$router->delete('/api/rooms/{id}', [$roomController, 'destroy'], $write);
