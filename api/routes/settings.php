<?php

declare (strict_types = 1);

use DormPlus\Api\Controllers\SettingsController;
use DormPlus\Api\Core\Router;
use DormPlus\Api\Middleware\AuthMiddleware;
use DormPlus\Api\Middleware\CsrfMiddleware;

/** @var Router $router */
/** @var SettingsController $settingsController */

$auth = [new AuthMiddleware()];
$write = [new AuthMiddleware(), new CsrfMiddleware()];

$router->get('/api/settings', [$settingsController, 'index'], $auth);
$router->put('/api/settings/property', [$settingsController, 'updateProperty'], $write);
$router->put('/api/settings/rental', [$settingsController, 'updateRental'], $write);
$router->put('/api/settings/system', [$settingsController, 'updateSystem'], $write);
