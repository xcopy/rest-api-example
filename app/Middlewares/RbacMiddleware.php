<?php

declare(strict_types=1);

namespace App\Middlewares;

use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

final class RbacMiddleware implements MiddlewareInterface
{
    private Sql $sql;

    public function __construct(Sql $sql, private string $permission)
    {
        $this->sql = $sql;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$userId = $request->getAttribute('user_id')) {
            throw new HttpUnauthorizedException($request);
        }

        $select = $this->sql
            ->select('permissions')
            ->columns(['id'])
            ->join('permission_role', 'permission_role.permission_id = permissions.id', [])
            ->join('role_user', 'role_user.role_id = permission_role.role_id', [])
            ->where([
                'role_user.user_id' => $userId,
                'permissions.name' => $this->permission,
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
