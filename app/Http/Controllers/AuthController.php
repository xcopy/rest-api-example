<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\EmailVerificationService;
use App\Services\PasswordService;
use App\Services\TokenService;
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

    /** @var array<string, string> */
    private array $config;

    public function __construct(
        Adapter $db,
        Form $form,
        private EmailVerificationService $emailVerification,
        private PasswordService $passwordService
    ) {
        $this->sql = new Sql($db);
        $this->validator = new AuthValidator($form);
        $this->config = require APP_BASE_PATH . '/config/app.php';
    }

    public function login(Request $request, Response $response): Response
    {
        // body: email, password
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'login');

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
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiresAt = $now->modify($config['access_token_ttl']);

        $insert = $this->sql
            ->insert('user_tokens')
            ->values([
                'user_id' => $user['id'],
                'token_hash' => TokenService::hash($token),
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

    public function sendVerification(Request $request, Response $response): Response
    {
        // body: email
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

        $data = $this->config['env'] === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
    }

    public function verifyEmail(Request $request, Response $response): Response
    {
        // body: token
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

    public function forgotPassword(Request $request, Response $response): Response
    {
        // body: email
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'forgot-password');

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        $select = $this->sql
            ->select('users')
            ->columns(['id', 'email'])
            ->where(['email' => $data['email']])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        $token = null;

        if ($user !== false) {
            $this->passwordService->sendReset((int) $user['id'], $user['email'], $token);
        }

        $data = $this->config['env'] === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        // body: token, new_password, new_password_repeat
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'reset-password');

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        if (!$this->passwordService->resetPassword($data['token'], $data['new_password'])) {
            throw new HttpUnprocessableEntityException($request, [
                'token' => 'The password reset token is invalid or expired.',
            ]);
        }

        return JsonResponse::write($response, status: 204);
    }

    public function changePassword(Request $request, Response $response): Response
    {
        // body: current_password, new_password, new_password_repeat
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'change-password');

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        $user_id = (int) $request->getAttribute('user_id');

        $select = $this->sql
            ->select('users')
            ->columns(['password', 'password_changed_at'])
            ->where(['id' => $user_id])
            ->limit(1);

        $user = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        if ($user === false) {
            throw new HttpUnauthorizedException($request);
        }

        if (!password_verify($data['current_password'], $user['password'])) {
            throw new HttpUnprocessableEntityException($request, [
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $new_password = $data['new_password'];

        if (password_verify($new_password, $user['password'])) {
            throw new HttpUnprocessableEntityException($request, [
                'new_password' => 'New password must be different from the current password.',
            ]);
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cooldownCutoff = $now->sub(new \DateInterval('PT24H'));
        $cooldownException = new HttpUnprocessableEntityException($request, [
            'new_password' => 'Password can only be changed once every 24 hours.',
        ]);

        if (
            $user['password_changed_at'] !== null
            && new \DateTimeImmutable($user['password_changed_at'], new \DateTimeZone('UTC')) > $cooldownCutoff
        ) {
            throw $cooldownException;
        }

        if (!$this->passwordService->changePassword($user_id, $new_password, $cooldownCutoff)) {
            throw $cooldownException;
        }

        $isLocal = $this->config['env'] === 'local';

        $data = $isLocal
            ? ['password' => $new_password]
            : null;

        $status = $isLocal ? 200 : 204;

        return JsonResponse::write($response, $data, $status);
    }
}
