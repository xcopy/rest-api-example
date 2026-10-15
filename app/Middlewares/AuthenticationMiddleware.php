<?php

declare(strict_types=1);

namespace App\Middlewares;

use DI\Attribute\Inject;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;

class AuthenticationMiddleware implements MiddlewareInterface
{
    #[Inject]
    private Sql $sql;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
            throw new HttpUnauthorizedException($request);
        }

        $token_hash = hashToken($matches[1]);

        $select = $this->sql
            ->select('user_tokens')
            ->columns([])
            ->join('users', 'user_tokens.user_id = users.id', ['id', 'email'])
            ->where([
                'user_tokens.token_hash' => $token_hash,
                'user_tokens.expires_at > ?' => now(true),
            ])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($user === false) {
            throw new HttpUnauthorizedException($request);
        }

        return $handler->handle(
            $request
                ->withAttribute('user_id', $user['id'])
                ->withAttribute('email', $user['email'])
                ->withAttribute('token_hash', $token_hash)
        );
    }
}
