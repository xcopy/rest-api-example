<?php

declare(strict_types=1);

namespace App\Policies;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

class UserPolicy
{
    private Sql $sql;

    public function __construct(Adapter $db)
    {
        $this->sql = new Sql($db);
    }

    public function assertCanList(ServerRequestInterface $request): void
    {
        $this->assertAdmin($request);
    }

    public function assertCanCreate(ServerRequestInterface $request): void
    {
        $this->assertAdmin($request);
    }

    public function assertCanView(ServerRequestInterface $request, int $targetUserId): void
    {
        $this->assertOwnerOrAdmin($request, $targetUserId);
    }

    public function assertCanUpdate(ServerRequestInterface $request, int $targetUserId): void
    {
        $this->assertOwnerOrAdmin($request, $targetUserId);
    }

    public function assertCanDelete(ServerRequestInterface $request, int $targetUserId): void
    {
        $userId = $this->authenticatedUserId($request);

        if ($userId === $targetUserId || !$this->isAdmin($userId)) {
            throw new HttpForbiddenException($request);
        }
    }

    private function assertOwnerOrAdmin(ServerRequestInterface $request, int $targetUserId): void
    {
        $userId = $this->authenticatedUserId($request);

        if ($userId !== $targetUserId && !$this->isAdmin($userId)) {
            throw new HttpForbiddenException($request);
        }
    }

    private function assertAdmin(ServerRequestInterface $request): void
    {
        if (!$this->isAdmin($this->authenticatedUserId($request))) {
            throw new HttpForbiddenException($request);
        }
    }

    private function authenticatedUserId(ServerRequestInterface $request): int
    {
        $userId = $request->getAttribute('user_id');

        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            throw new HttpUnauthorizedException($request);
        }

        return (int) $userId;
    }

    private function isAdmin(int $userId): bool
    {
        $select = $this->sql
            ->select('roles')
            ->columns(['id'])
            ->join('role_user', 'role_user.role_id = roles.id', [])
            ->where([
                'role_user.user_id' => $userId,
                'roles.name' => 'admin',
            ])
            ->limit(1);

        return $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current() !== false;
    }
}
