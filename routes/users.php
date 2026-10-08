<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use App\Middlewares\AuthenticationMiddleware;
use App\Middlewares\RbacMiddleware;
use Laminas\Db\Adapter\Adapter;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app
        ->group('/users', function (RouteCollectorProxy $group) {
            $controllerClass = UserController::class;
            $id = '/{id:[0-9]+}';

            $authorize = fn(string $permission) => new RbacMiddleware(
                $group->getContainer()->get(Adapter::class),
                $permission
            );

            $group->get('', [$controllerClass, 'index'])->add($authorize('list users'));
            $group->get($id, [$controllerClass, 'show'])->add($authorize('show user'));
            $group->post('', [$controllerClass, 'create'])->add($authorize('create user'));
            $group->map(['POST', 'PATCH'], $id, [$controllerClass, 'update'])->add($authorize('update user'));
            $group->put($id, [$controllerClass, 'upsert'])->add($authorize('update user'));
            $group->delete($id, [$controllerClass, 'delete'])->add($authorize('delete user'));
        })
        ->add(AuthenticationMiddleware::class);
};
