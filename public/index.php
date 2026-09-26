<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Middleware\JsonResponseMiddleware;
use App\Middleware\JsonRequestMiddleware;
use Assert\Assertion;
use DI\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Middlewares\TrailingSlash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Slim\Routing\RouteCollectorProxy;

$container = new Container();

$container->set('db', function () {
    $adapter =  new Adapter([
        'driver'   => 'Pdo_Sqlite',
        'database' => __DIR__ . '/../db/db.sqlite3',
    ]);

    $adapter->query('PRAGMA foreign_keys = ON;')->execute();

    return $adapter;
});

AppFactory::setContainer($container);

$app = AppFactory::create();
$app->add(new JsonRequestMiddleware());
$app->addRoutingMiddleware();
$app->add(new TrailingSlash(false));
$app->add(new ContentLengthMiddleware());
$app->add(new JsonResponseMiddleware());
$app->addErrorMiddleware(true, true, true);

$app->group('/users', function (RouteCollectorProxy $group) {
    $sql = new Sql($this->get('db'));

    $baseSelect = $sql->select('users')
        ->columns(['id', 'email', 'first_name', 'last_name']);

    $group->get('', function (Request $request, Response $response) use ($sql, $baseSelect) {
        $queryParams = $request->getQueryParams();

        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $perPage = isset($queryParams['per_page']) ? max(1, min(20, (int) $queryParams['per_page'])) : 20;

        $countSelect = (clone $baseSelect)
            ->columns(['total' => new Expression('COUNT(*)')]); // will override base columns
        $countStatement = $sql->prepareStatementForSqlObject($countSelect);
        $countResult = $countStatement->execute()->current();

        $totalCount = (int) ($countResult['total'] ?? 0);
        $totalPages = (int) ceil($totalCount / $perPage);

        $page = min($page, $totalPages > 0 ? $totalPages : 1);
        $offset = ($page - 1) * $perPage;

        $response = $response
            ->withHeader('Access-Control-Expose-Headers', 'X-Pagination-Total-Count, X-Pagination-Total-Pages, X-Pagination-Current-Page, X-Pagination-Per-Page')
            ->withHeader('X-Pagination-Total-Count', (string) $totalCount)
            ->withHeader('X-Pagination-Total-Pages', (string) $totalPages)
            ->withHeader('X-Pagination-Current-Page', (string) $page)
            ->withHeader('X-Pagination-Per-Page', (string) $perPage);

        $select = (clone $baseSelect)
            ->limit($perPage)
            ->offset($offset)
            ->order('id DESC');

        $statement = $sql->prepareStatementForSqlObject($select);

        $results = iterator_to_array($statement->execute());

        $response->getBody()->write(json_encode($results));

        return $response;
    });

    $group->get('/{id}', function (Request $request, Response $response, array $args) use ($sql, $baseSelect) {
        Assertion::integerish($args['id']);

        $select = (clone $baseSelect)
            ->where(['id' => $args['id']]);

        $statement = $sql->prepareStatementForSqlObject($select);

        $results = $statement->execute();

        if ($results->count() === 0) {
            throw new HttpNotFoundException($request);
        }

        $response->getBody()->write(json_encode($results->current()));

        return $response;
    });
});

$app->run();
