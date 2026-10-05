<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
    });
};
