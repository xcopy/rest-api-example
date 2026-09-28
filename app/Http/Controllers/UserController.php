<?php

namespace App\Http\Controllers;

use App\Exception\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use Assert\Assert;
use Assert\Assertion;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

class UserController
{
    private Form $form;

    private Sql $sql;

    private Select $baseSelect;

    public function __construct(Adapter $db, Form $form)
    {
        $this->form = $form;

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

        $results = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute();

        $data = iterator_to_array($results);

        return JsonResponse::write($response, $data);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            Assertion::integerish($args['id']);
        } catch (\Throwable $e) {
            throw new HttpBadRequestException($request, $e->getMessage());
        }

        $select = (clone $this->baseSelect)
            ->where(['id' => $args['id']]);

        $results = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute();

        if ($results->count() === 0) {
            throw new HttpNotFoundException($request);
        }

        $data = $results->current();

        return JsonResponse::write($response, $data);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->form->validate($request->getParsedBody() ?? [], [
            'email' => 'email|unique:users',
            'password' => 'min:8',
            'first_name' => 'text|min:3|max:100',
            'last_name' => 'text|min:3|max:100',
        ]);

        if ($data === false) {
            // var_dump($this->form->errors()); exit;
            throw new HttpUnprocessableEntityException($request);
        }

        $insert = $this->sql
            ->insert('users')
            ->values([
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);

        $result = $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        $select = (clone $this->baseSelect)
            ->where(['id' => $result->getGeneratedValue()]);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return JsonResponse::write($response, $user, 201);
    }
}
