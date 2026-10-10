<?php

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Policies\UserPolicy;
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

    private UserPolicy $policy;

    public function __construct(Adapter $db, Form $form)
    {
        $this->sql = new Sql($db);
        $this->policy = new UserPolicy($db);

        $this->baseSelect = $this->sql
            ->select('users')
            ->columns(['id', 'email', 'first_name', 'last_name']);

        $this->validator = new UserValidator($form);
    }

    public function index(Request $request, Response $response): Response
    {
        $this->policy->assertCanList($request);

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

    /** @param array<string, string> $args */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;

        $user = $this->findUser($request, $id);

        $this->policy->assertCanView($request, (int) $id);

        return JsonResponse::write($response, $user);
    }

    public function create(Request $request, Response $response): Response
    {
        $this->policy->assertCanCreate($request);

        $data = $this->validator->validate($request->getParsedBody() ?? []);

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

        $insert = $this->sql
            ->insert('users')
            ->values($data);

        $result = $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        $user_id = (int) $result->getGeneratedValue();

        $this->assignDefaultRole($user_id);

        $select = (clone $this->baseSelect)
            ->where(['id' => $user_id]);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return JsonResponse::write($response, $user, 201);
    }

    /** @param array<string, string> $args */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;

        $this->findUser($request, $id);

        $this->policy->assertCanUpdate($request, (int) $id);

        $data = $this->validator->validate(
            $request->getParsedBody() ?? [],
            'update',
            ['user_id' => $id]
        );

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
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

    /** @param array<string, string> $args */
    public function upsert(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;
        $exists = true;

        try {
            $this->findUser($request, $id);
        } catch (HttpNotFoundException) {
            $exists = false;
        }

        if ($exists) {
            $this->policy->assertCanUpdate($request, (int) $id);
        } else {
            $this->policy->assertCanCreate($request);
        }

        $data = $this->validator->validate(
            $request->getParsedBody() ?? [],
            context: $exists ? ['user_id' => $id] : []
        );

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
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

        if (!$exists) {
            $this->assignDefaultRole((int) $id);
        }

        return JsonResponse::write(
            $response,
            $exists ? null : $this->findUser($request, $id),
            $exists ? 204 : 201
        );
    }

    /** @param array<string, string> $args */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'] ?? null;

        $this->findUser($request, $id);

        $this->policy->assertCanDelete($request, (int) $id);

        $delete = $this->sql
            ->delete('users')
            ->where(compact('id'));

        $this->sql
            ->prepareStatementForSqlObject($delete)
            ->execute();

        return JsonResponse::write($response, status: 204);
    }

    private function assignDefaultRole(int $user_id): void
    {
        $select = $this->sql
            ->select('roles')
            ->columns(['id'])
            ->where(['name' => 'user'])
            ->limit(1);

        $role = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($role === false) {
            throw new \RuntimeException('The default user role is not configured.');
        }

        $insert = $this->sql
            ->insert('role_user')
            ->values([
                'role_id' => $role['id'],
                'user_id' => $user_id,
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();
    }

    /** @return array<string, mixed> */
    private function findUser(Request $request, int|string|null $id): array
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
