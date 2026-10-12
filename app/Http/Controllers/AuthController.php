<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\DateTimeService;
use App\Services\TokenService;
use App\Validators\AuthValidator;
use Laminas\Db\Adapter\Adapter;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

class AuthController extends Controller
{
    private AuthValidator $validator;

    public function __construct(Adapter $db, Form $form)
    {
        parent::__construct($db);

        $this->validator = new AuthValidator($form);
    }

    public function login(Request $request, Response $response): Response
    {
        // body: email, password
        $data = $this->validator->validate($request->getParsedBody() ?? []);

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

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

        $config = require APP_BASE_PATH . '/config/auth.php';

        $token = TokenService::generateToken();
        $expiresAt = DateTimeService::now()->modify($config['access_token_ttl']);

        $insert = $this->sql
            ->insert('user_tokens')
            ->values([
                'user_id' => $user['id'],
                'token_hash' => TokenService::hash($token),
                'expires_at' => $expiresAt->format(DateTimeService::FORMAT),
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
