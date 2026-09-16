<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\ExpenseController;
use DormPlus\Api\Controllers\PaymentController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var PaymentController $paymentController */
/** @var ExpenseController $expenseController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/payments', [$paymentController, 'index'], $auth);
$router->get('/api/payments/summary', [$paymentController, 'summary'], $auth);
$router->get('/api/payments/{id}', [$paymentController, 'show'], $auth);
$router->post('/api/payments', [$paymentController, 'store'], $write);
$router->put('/api/payments/{id}', [$paymentController, 'update'], $write);
$router->put('/api/payments/{id}/cancel', [$paymentController, 'cancel'], $write);
$router->delete('/api/payments/{id}', [$paymentController, 'destroy'], $write);

$router->get('/api/expenses', [$expenseController, 'index'], $auth);
$router->get('/api/expenses/{id}', [$expenseController, 'show'], $auth);
$router->post('/api/expenses', [$expenseController, 'store'], $write);
$router->put('/api/expenses/{id}', [$expenseController, 'update'], $write);
$router->delete('/api/expenses/{id}', [$expenseController, 'destroy'], $write);
