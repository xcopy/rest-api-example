<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Validators\AuthValidator;
use App\Validators\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

class AuthController extends ValidatingController
{
    protected function getValidator(): Validator
    {
        return new AuthValidator($this->form);
    }

    public function login(Request $request, Response $response): Response
    {
        // body: email, password
        $data = $this->validateRequest($request);

        $select = $this->sql
            ->select('users')
            ->where(['email' => $data['email']])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        $successfull = $user && password_verify($data['password'], $user['password']);

        if (!$successfull) {
            throw new HttpUnauthorizedException($request, 'Invalid email or password.');
        }

        if ($user['email_verified_at'] === null) {
            throw new HttpForbiddenException($request, 'Email address is not verified.');
        }

        $token = generateToken();
        $expiresAt = now()->modify($this->config->get('auth.access_token_ttl'));

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
