<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\EmailVerificationService;
use App\Validators\AuthValidator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;

class AuthController
{
    private Sql $sql;

    private AuthValidator $validator;

    public function __construct(Adapter $db, Form $form, private EmailVerificationService $emailVerification)
    {
        $this->sql = new Sql($db);
        $this->validator = new AuthValidator($form);
    }

    public function login(Request $request, Response $response): Response
    {
        $credentials = $this->validator->validate($request->getParsedBody() ?? [], 'login');

        if ($credentials === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        $select = $this->sql
            ->select('users')
            ->where(['email' => $credentials['email']])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        $successfull = $user && password_verify($credentials['password'], $user['password']);

        if (!$successfull) {
            throw new HttpUnauthorizedException($request, 'Invalid email or password.');
        }

        if ($user['email_verified_at'] === null) {
            throw new HttpForbiddenException($request, 'Email address is not verified.');
        }

        $config = require APP_BASE_PATH . '/config/auth.php';

        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify($config['access_token_ttl']);

        $insert = $this->sql
            ->insert('user_tokens')
            ->values([
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

        $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        return JsonResponse::write($response, [
            'access_token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ]);
    }

    public function verifyEmail(Request $request, Response $response): Response
    {
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'verify-email');

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        if (!$this->emailVerification->verify($data['token'])) {
            throw new HttpUnprocessableEntityException($request, [
                'token' => 'The verification token is invalid or expired.',
            ]);
        }

        return JsonResponse::write($response, status: 204);
    }

    public function sendVerification(Request $request, Response $response): Response
    {
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'send-verification');

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        $select = $this->sql
            ->select('users')
            ->columns(['id', 'email', 'email_verified_at'])
            ->where(['email' => $data['email']])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($user === false) {
            throw new HttpNotFoundException($request);
        }

        if ($user['email_verified_at'] !== null) {
            throw new HttpUnprocessableEntityException($request, [
                'email' => 'Email address is already verified.',
            ]);
        }

        $this->emailVerification->sendVerification((int) $user['id'], $user['email'], $token);

        $config = require APP_BASE_PATH . '/config/app.php';

        $data = $config['env'] === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
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
