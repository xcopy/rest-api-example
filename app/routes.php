<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UserController;
use App\Middlewares\AuthenticationMiddleware;
use App\Middlewares\LowerFilterMiddleware;
use App\Middlewares\RbacMiddleware;
use Laminas\Db\Adapter\Adapter;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

$authMiddleware = AuthenticationMiddleware::class;
$lowerFilterMiddleware = new LowerFilterMiddleware('email');

return function (App $app) use ($authMiddleware, $lowerFilterMiddleware) {
    // auth
    $app->group('/auth', function (RouteCollectorProxy $group) use ($authMiddleware, $lowerFilterMiddleware) {
        $controllerClass = AuthController::class;

        $group
            ->post('/login', [$controllerClass, 'login'])
            ->add($lowerFilterMiddleware);
        $group
            ->post('/logout', [$controllerClass, 'logout'])
            ->add($authMiddleware);
        $group
            ->post('/logout-all', [$controllerClass, 'logoutAll'])
            ->add($authMiddleware);

        $group
            ->post('/change-password', [$controllerClass, 'changePassword'])
            ->add($authMiddleware);
        $group
            ->post('/forgot-password', [$controllerClass, 'forgotPassword'])
            ->add($lowerFilterMiddleware);
        $group->post('/reset-password', [$controllerClass, 'resetPassword']);
    });

    // email-verification
    $app->group('/email-verification', function (RouteCollectorProxy $group) use ($lowerFilterMiddleware) {
        $controllerClass = EmailVerificationController::class;

        $group
            ->post('/send', [$controllerClass, 'send'])
            ->add($lowerFilterMiddleware);
        $group->post('/verify', [$controllerClass, 'verify']);
    });

    // password
    $app->group('/password', function (RouteCollectorProxy $group) use ($authMiddleware, $lowerFilterMiddleware) {
        $controllerClass = PasswordController::class;

        $group
            ->post('/change', [$controllerClass, 'change'])
            ->add($authMiddleware);
        $group
            ->post('/forgot', [$controllerClass, 'forgot'])
            ->add($lowerFilterMiddleware);
        $group->post('/reset', [$controllerClass, 'reset']);
    });

    // users
    $app
        ->group('/users', function (RouteCollectorProxy $group) use ($lowerFilterMiddleware) {
            $controllerClass = UserController::class;
            $id = '/{id:[0-9]+}';

            $authMiddleware = fn(string $permission) => new RbacMiddleware(
                $group->getContainer()->get(Adapter::class),
                $permission
            );

            $group->get('', [$controllerClass, 'index'])
                ->add($authMiddleware('list users'));
            $group->get($id, [$controllerClass, 'show'])
                ->add($authMiddleware('show user'));
            $group->post('', [$controllerClass, 'create'])
                ->add($lowerFilterMiddleware)
                ->add($authMiddleware('create user'));
            $group->map(['POST', 'PATCH'], $id, [$controllerClass, 'update'])
                ->add($lowerFilterMiddleware)
                ->add($authMiddleware('update user'));
            $group->put($id, [$controllerClass, 'upsert'])
                ->add($lowerFilterMiddleware)
                ->add($authMiddleware('update user'));
            $group->delete($id, [$controllerClass, 'delete'])
                ->add($authMiddleware('delete user'));
        })
        ->add(AuthenticationMiddleware::class);
};
