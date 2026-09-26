<?php

namespace App\Http\Controllers;

use Assert\Assertion;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class UserController
{
    private Sql $sql;

    private Select $baseSelect;

    public function __construct(Adapter $db)
    {
        $this->sql = new Sql($db);

        $this->baseSelect = $this->sql
            ->select('users')
            ->columns(['id', 'email', 'first_name', 'last_name']);
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();

        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $perPage = isset($queryParams['per_page']) ? max(1, min(20, (int) $queryParams['per_page'])) : 20;

        $countSelect = (clone $this->baseSelect)
            ->columns(['total' => new Expression('COUNT(*)')]); // overrides base columns
        $countResult = $this->sql
            ->prepareStatementForSqlObject($countSelect)
            ->execute()
            ->current();

        $totalCount = (int) ($countResult['total'] ?? 0);
        $totalPages = (int) ceil($totalCount / $perPage);

        $page = min($page, $totalPages > 0 ? $totalPages : 1);
        $offset = ($page - 1) * $perPage;

        // Добавляем X-Pagination заголовки
        $response = $response
            ->withHeader('Access-Control-Expose-Headers', 'X-Pagination-Total-Count, X-Pagination-Total-Pages, X-Pagination-Current-Page, X-Pagination-Per-Page')
            ->withHeader('X-Pagination-Total-Count', (string) $totalCount)
            ->withHeader('X-Pagination-Total-Pages', (string) $totalPages)
            ->withHeader('X-Pagination-Current-Page', (string) $page)
            ->withHeader('X-Pagination-Per-Page', (string) $perPage);

        // Клонируем базовое свойство класса
        $select = (clone $this->baseSelect)
            ->limit($perPage)
            ->offset($offset)
            ->order('id DESC');

        $results = iterator_to_array($this->sql->prepareStatementForSqlObject($select)->execute());

        $response->getBody()->write(json_encode($results));

        return $response;
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        Assertion::integerish($args['id']);

        $select = (clone $this->baseSelect)
            ->where(['id' => $args['id']]);

        $results = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute();

        if ($results->count() === 0) {
            throw new HttpNotFoundException($request);
        }

        $response->getBody()->write(json_encode($results->current()));

        return $response;
    }
}
