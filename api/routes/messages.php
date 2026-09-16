<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\MessageController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var MessageController $messageController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/messages/threads', [$messageController, 'threads'], $auth);
$router->get('/api/messages/unread-count', [$messageController, 'unreadCount'], $auth);
$router->get('/api/messages/recipients', [$messageController, 'recipients'], $auth);
$router->get('/api/messages/threads/{id}', [$messageController, 'thread'], $auth);
$router->post('/api/messages/threads', [$messageController, 'createThread'], $write);
$router->post('/api/messages/threads/{id}', [$messageController, 'reply'], $write);
$router->put('/api/messages/threads/{id}/read', [$messageController, 'markRead'], $write);
