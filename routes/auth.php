<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Middleware\AuthenticationMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/logout', [AuthController::class, 'logout'])->add(AuthenticationMiddleware::class);
        $group->post('/logout-all', [AuthController::class, 'logoutAll'])->add(AuthenticationMiddleware::class);
    });
};
