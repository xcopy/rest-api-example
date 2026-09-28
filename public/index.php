<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\UserController;
use App\Middleware\JsonRequestMiddleware;
use App\Middleware\JsonResponseMiddleware;
use DI\ContainerBuilder;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Middlewares\TrailingSlash;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Routing\RouteCollectorProxy;

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);
$containerBuilder->addDefinitions([
    Adapter::class => function () {
        $adapter = new Adapter([
            'driver'   => 'Pdo_Sqlite',
            'database' => __DIR__ . '/../db/db.sqlite3',
        ]);

        $adapter->query('PRAGMA foreign_keys = ON;')->execute();

        return $adapter;
    },
    'db' => \DI\get(Adapter::class),
    Form::class => function (ContainerInterface $container) {
        $form = new Form();

        $form->rule('unique', function ($value, $param, $field) use ($container) {
            $sql = new Sql($container->get('db'));

            $params = explode(',', $param);

            $table = $params[0] ?? null;
            $currentUserId = $params[1] ?? null;

            $select = $sql
                ->select($table)
                ->where([$field => $value])
                ->limit(1);

            if ($currentUserId !== null) {
                $select->where(['id != ?' => $currentUserId]);
            }

            $results = $sql->prepareStatementForSqlObject($select)->execute();

            return $results->count() === 0;
        });

        $form->message('unique', 'The value for the field {field} is already taken');

        return $form;
    },
]);

AppFactory::setContainer($containerBuilder->build());

$app = AppFactory::create();
$app->add(new JsonRequestMiddleware());
$app->addRoutingMiddleware();
$app->add(new TrailingSlash(false));
$app->add(new ContentLengthMiddleware());
$app->add(new JsonResponseMiddleware());
$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');

$app->group('/users', function (RouteCollectorProxy $group) {
    $group->options('', function (Request $request, Response $response) {
        return $response
            // ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST');
    });
    $group->options('/{id}', function (Request $request, Response $response) {
        return $response
            // ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'PATCH, POST, DELETE');
    });

    $group->get('', [UserController::class, 'index']);
    $group->get('/{id}', [UserController::class, 'show']);
    $group->post('', [UserController::class, 'create']);
    $group->map(['POST', 'PATCH'], '/{id}', [UserController::class, 'update']);
    $group->delete('/{id}', [UserController::class, 'delete']);
});

$app->run();
