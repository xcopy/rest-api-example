<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UserController;
use App\Http\JsonResponse;
use App\Middlewares\AuthenticationMiddleware;
use App\Middlewares\LowerFilterMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

$authMiddleware = AuthenticationMiddleware::class;
$lowerFilterMiddleware = new LowerFilterMiddleware('email');

return function (App $app) use ($authMiddleware, $lowerFilterMiddleware) {
    // health
    $app->get('/health', function (Request $request, Response $response) {
        return JsonResponse::write($response, ['status' => 'ok']);
    });

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
    });

    // email-verification
    $app->group('/email-verification', function (RouteCollectorProxy $group) use ($authMiddleware) {
        $controllerClass = EmailVerificationController::class;

        $group
            ->post('/send', [$controllerClass, 'send'])
            ->add($authMiddleware);
        $group->post('/verify', [$controllerClass, 'verify'])
            ->setName('email-verification.verify');
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
        $group->post('/reset', [$controllerClass, 'reset'])
            ->setName('password.reset');
    });

    // users
    $app
        ->group('/users', function (RouteCollectorProxy $group) {
            $controllerClass = UserController::class;
            $id = '/{id:[0-9]+}';

            $group->get($id, [$controllerClass, 'show']);
            $group->map(['POST', 'PATCH'], $id, [$controllerClass, 'update']);
            $group->delete($id, [$controllerClass, 'delete']);
        })
        ->add(AuthenticationMiddleware::class);
};
