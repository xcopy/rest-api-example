<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Middlewares\AuthenticationMiddleware;
use App\Middlewares\LowerFilterMiddleware;
use App\Middlewares\RbacMiddleware;
use Laminas\Db\Adapter\Adapter;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

$lowerFilter = new LowerFilterMiddleware('email');

return function (App $app) use ($lowerFilter) {
    // auth
    $app->group('/auth', function (RouteCollectorProxy $group) use ($lowerFilter) {
        $controllerClass = AuthController::class;

        $group->post('/login', [$controllerClass, 'login'])
            ->add($lowerFilter);
        $group->post('/verify-email', [$controllerClass, 'verifyEmail']);
        $group->post('/send-verification', [$controllerClass, 'sendVerification'])
            ->add($lowerFilter);

        $auth = AuthenticationMiddleware::class;

        $group
            ->post('/logout', [$controllerClass, 'logout'])
            ->add($auth);
        $group
            ->post('/logout-all', [$controllerClass, 'logoutAll'])
            ->add($auth);
        $group
            ->post('/change-password', [$controllerClass, 'changePassword'])
            ->add($auth);
    });

    // users
    $app
        ->group('/users', function (RouteCollectorProxy $group) use ($lowerFilter) {
            $controllerClass = UserController::class;
            $id = '/{id:[0-9]+}';

            $authorize = fn(string $permission) => new RbacMiddleware(
                $group->getContainer()->get(Adapter::class),
                $permission
            );

            $group->get('', [$controllerClass, 'index'])->add($authorize('list users'));
            $group->get($id, [$controllerClass, 'show'])->add($authorize('show user'));
            $group->post('', [$controllerClass, 'create'])
                ->add($lowerFilter)
                ->add($authorize('create user'));
            $group->map(['POST', 'PATCH'], $id, [$controllerClass, 'update'])
                ->add($lowerFilter)
                ->add($authorize('update user'));
            $group->put($id, [$controllerClass, 'upsert'])
                ->add($lowerFilter)
                ->add($authorize('update user'));
            $group->delete($id, [$controllerClass, 'delete'])->add($authorize('delete user'));
        })
        ->add(AuthenticationMiddleware::class);
};
