<?php

namespace App\Http\Controllers;

use App\Exception\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Validators\UserValidator;
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
    private Sql $sql;

    private Select $baseSelect;

    private UserValidator $validator;

    public function __construct(Adapter $db, Form $form)
    {
        $this->sql = new Sql($db);

        $this->baseSelect = $this->sql
            ->select('users')
            ->columns(['id', 'email', 'first_name', 'last_name']);

        $this->validator = new UserValidator($form);
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
        $data = $this->validator->validate($request->getParsedBody());

        if ($data === false) {
            // var_dump($this->validator->getErrors()); exit;
            throw new HttpUnprocessableEntityException($request);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $insert = $this->sql
            ->insert('users')
            ->values($data);

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
