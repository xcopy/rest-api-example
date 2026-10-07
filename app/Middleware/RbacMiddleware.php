<?php

declare(strict_types=1);

namespace App\Middleware;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

class RbacMiddleware implements MiddlewareInterface
{
    private Sql $sql;

    public function __construct(Adapter $db, private string $permission)
    {
        $this->sql = new Sql($db);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$user_id = $request->getAttribute('user_id')) {
            throw new HttpUnauthorizedException($request);
        }

        $select = $this->sql
            ->select(['p' => 'permissions'])
            ->columns(['id'])
            ->join(['pr' => 'permission_role'], 'pr.permission_id = p.id', [])
            ->join(['ru' => 'role_user'], 'ru.role_id = pr.role_id', [])
            ->where([
                'ru.user_id' => $user_id,
                'p.name' => $this->permission,
            ])
            ->limit(1);

        $permission = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($permission === false) {
            throw new HttpForbiddenException($request);
        }

        return $handler->handle($request);
    }
}
