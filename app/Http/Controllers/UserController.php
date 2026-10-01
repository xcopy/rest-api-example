<?php

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Validators\UserValidator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
        $user = $this->findUser($request, $args['id'] ?? null);

        return JsonResponse::write($response, $user);
    }

    public function create(Request $request, Response $response): Response
    {
        $data = $this->validator->validate($request->getParsedBody() ?? []);

        if ($data === false) {
            return JsonResponse::write($response, $this->validator->getErrors(), 422);
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

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;

        $this->findUser($request, $id);

        $data = $this->validator->validate(
            $request->getParsedBody() ?? [],
            'update',
            ['user_id' => $id]
        );

        if ($data === false) {
            return JsonResponse::write($response, $this->validator->getErrors(), 422);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $update = $this->sql
            ->update('users')
            ->where(compact('id'))
            ->set($data);

        $this->sql
            ->prepareStatementForSqlObject($update)
            ->execute();

        return JsonResponse::write($response, status: 204);
    }

    public function upsert(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;

        try {
            $exists = $this->findUser($request, $id) !== false;
        } catch (\Throwable) {
            $exists = false;
        }

        $data = $this->validator->validate(
            $request->getParsedBody() ?? [],
            context: $exists ? ['user_id' => $id] : []
        );

        if ($data === false) {
            return JsonResponse::write($response, $this->validator->getErrors(), 422);
        }

        if ($exists) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        } else {
            $data['id'] = $id;
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $sql = $exists
            ? $this->sql->update('users')->set($data)->where(compact('id'))
            : $this->sql->insert('users')->values($data);

        $this->sql
            ->prepareStatementForSqlObject($sql)
            ->execute();

        return JsonResponse::write(
            $response,
            $exists ? null : $this->findUser($request, $id),
            $exists ? 204 : 201
        );
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;

        $this->findUser($request, $id);

        $delete = $this->sql
            ->delete('users')
            ->where(compact('id'));

        $this->sql
            ->prepareStatementForSqlObject($delete)
            ->execute();

        return JsonResponse::write($response, status: 204);
    }

    private function findUser(Request $request, ?int $id): array
    {
        $select = (clone $this->baseSelect)
            ->where(compact('id'));

        $results = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute();

        if ($results->count() === 0) {
            throw new HttpNotFoundException($request);
        }

        return $results->current();
    }
}
