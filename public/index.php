<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

$container = new Container();

$container->set('db', function () {
    $adapter =  new Adapter([
        'driver'   => 'Pdo_Sqlite',
        'database' => __DIR__ . '/../storage/db.sqlite3',
    ]);

    $adapter->query('PRAGMA foreign_keys = ON;')->execute();

    return $adapter;
});

AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');

$app->add(function (Request $request, Handler $handler) {
    $response = $handler->handle($request);

    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/users', function (Request $request, Response $response) {
    $sql = new Sql($this->get('db'));

    $select = $sql
        ->select('users')
        ->columns(['id', 'email', 'first_name', 'last_name'])
        ->order('id DESC');

    $statement = $sql->prepareStatementForSqlObject($select);

    $results = iterator_to_array($statement->execute());

    $response->getBody()->write(json_encode($results));

    return $response;
});

$app->get('/users/{id}', function (Request $request, Response $response, array $args) {
    $sql = new Sql($this->get('db'));

    $select = $sql
        ->select('users')
        ->columns(['id', 'email', 'first_name', 'last_name'])
        ->where(['id' => $args['id']]);

    $statement = $sql->prepareStatementForSqlObject($select);

    $results = $statement->execute();

    if ($results->count() === 0) {
        throw new HttpNotFoundException($request);
    }

    $response->getBody()->write(json_encode($results->current()));

    return $response;
});

$app->run();
