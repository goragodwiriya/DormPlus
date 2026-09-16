<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\NotificationController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var NotificationController $notificationController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/notifications', [$notificationController, 'index'], $auth);
$router->put('/api/notifications/read-all', [$notificationController, 'markAllRead'], $write);
$router->put('/api/notifications/{id}/read', [$notificationController, 'markRead'], $write);
$router->delete('/api/notifications/{id}', [$notificationController, 'destroy'], $write);
