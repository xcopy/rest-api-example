<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Services\AuthService;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpUnauthorizedException;

class AuthController extends Controller
{
    #[Inject]
    private AuthService $auth;

    public function login(Request $request, Response $response): Response
    {
        $user = $this->auth->login($request->getParsedBody() ?? []);

        if ($user === null) {
            throw new HttpUnauthorizedException($request, 'Invalid email or password.');
        }

        $token = generateToken();
        $expiresAt = now()->modify(config('auth.access_token_ttl'));

        $insert = $this->sql
            ->insert('user_tokens')
            ->values([
                'user_id' => $user['id'],
                'token_hash' => hashToken($token),
                'expires_at' => $expiresAt->format(DATE_FORMAT_DB),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        return JsonResponse::write($response, [
            'access_token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $sql = $this->sql
            ->delete('user_tokens')
            ->where(['token_hash' => $request->getAttribute('token_hash')]);

        $this->sql
            ->prepareStatementForSqlObject($sql)
            ->execute();

        return JsonResponse::write($response, status: 204);
    }

    public function logoutAll(Request $request, Response $response): Response
    {
        $sql = $this->sql
            ->delete('user_tokens')
            ->where(['user_id' => $request->getAttribute('user_id')]);

        $this->sql
            ->prepareStatementForSqlObject($sql)
            ->execute();

        return JsonResponse::write($response, status: 204);
    }
}
