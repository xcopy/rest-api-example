<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use App\Middleware\AuthenticationMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/users', function (RouteCollectorProxy $group) {
        $id = '/{id:[0-9]+}';

        $group->options('', function (Request $request, Response $response) {
            return $response
                // ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST');
        });
        $group->options($id, function (Request $request, Response $response) {
            return $response
                // ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'PATCH, POST, PUT, DELETE');
        });

        $group->get('', [UserController::class, 'index']);
        $group->get($id, [UserController::class, 'show']);
        $group->post('', [UserController::class, 'create']);
        $group->map(['POST', 'PATCH'], $id, [UserController::class, 'update']);
        $group->put($id, [UserController::class, 'upsert']);
        $group->delete($id, [UserController::class, 'delete']);
    })->add(AuthenticationMiddleware::class);
};
