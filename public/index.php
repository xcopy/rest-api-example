<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Error\Renderers\JsonErrorRenderer;
use App\Handlers\ErrorHandler;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use DI\ContainerBuilder;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Middlewares\TrailingSlash;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;

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

$auth = require __DIR__ . '/../routes/auth.php';
$users = require __DIR__ . '/../routes/users.php';
$auth($app);
$users($app);

// Order matters: LIFO (Last-In, First-Out)
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(ContentLengthMiddleware::class);
$app->add(TrailingSlash::class);
$app->add(RateLimitMiddleware::class);

$errorHandler = new ErrorHandler($app->getCallableResolver(), $app->getResponseFactory());
$errorHandler->forceContentType('application/json');
$errorHandler->registerErrorRenderer('application/json', JsonErrorRenderer::class);

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

$app->add(CorsMiddleware::class);
$app->run();
