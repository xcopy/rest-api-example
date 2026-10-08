<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use App\Middlewares\AuthenticationMiddleware;
use App\Middlewares\RbacMiddleware;
use Laminas\Db\Adapter\Adapter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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

        $authorize = fn(string $permission) => new RbacMiddleware(
            $group->getContainer()->get(Adapter::class),
            $permission
        );

        $group->get('', [UserController::class, 'index'])->add($authorize('list users'));
        $group->get($id, [UserController::class, 'show'])->add($authorize('show user'));
        $group->post('', [UserController::class, 'create'])->add($authorize('create user'));
        $group->map(['POST', 'PATCH'], $id, [UserController::class, 'update'])->add($authorize('update user'));
        $group->put($id, [UserController::class, 'upsert'])->add($authorize('update user'));
        $group->delete($id, [UserController::class, 'delete'])->add($authorize('delete user'));
    })
    ->add(AuthenticationMiddleware::class);
};
