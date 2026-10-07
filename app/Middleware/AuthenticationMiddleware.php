<?php

namespace App\Middleware;

use App\Http\JsonResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthenticationMiddleware implements MiddlewareInterface
{
    private Sql $sql;

    public function __construct(Adapter $db)
    {
        $this->sql = new Sql($db);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
            return $this->unauthorized();
        }

        $token_hash = hash('sha256', $matches[1]);

        $select = $this->sql
            ->select(['t' => 'user_tokens'])
            ->columns([])
            ->join(['u' => 'users'], 't.user_id = u.id', ['id'])
            ->where([
                't.token_hash' => $token_hash,
                't.expires_at > ?' => gmdate('Y-m-d H:i:s'),
            ])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($user === false) {
            return $this->unauthorized();
        }

        return $handler->handle(
            $request
                ->withAttribute('user_id', $user['id'])
                ->withAttribute('token_hash', $token_hash)
        );
    }

    private function unauthorized(): ResponseInterface
    {
        $response = (new Response())->withHeader('WWW-Authenticate', 'Bearer');

        return JsonResponse::write($response, ['message' => 'Unauthenticated.'], 401);
    }
}
