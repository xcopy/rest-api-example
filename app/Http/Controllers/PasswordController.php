<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\PasswordService;
use App\Validators\PasswordValidator;
use App\Validators\Validator;
use DateInterval;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpUnauthorizedException;

class PasswordController extends ValidatingController
{
    #[Inject]
    protected PasswordService $passwordService;

    protected function getValidator(): Validator
    {
        return new PasswordValidator($this->form);
    }

    public function change(Request $request, Response $response): Response
    {
        // body: current_password, new_password, new_password_repeat
        $data = $this->validateRequest($request, 'change');

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

        $cooldownCutoff = now()->sub(new DateInterval('PT24H'));
        $cooldownException = new HttpUnprocessableEntityException($request, [
            'new_password' => 'Password can only be changed once every 24 hours.',
        ]);

        $password_changed_at = $user['password_changed_at'];

        if (
            $password_changed_at !== null
            && parseDateTime($password_changed_at) > $cooldownCutoff
        ) {
            throw $cooldownException;
        }

        if (!$this->passwordService->change($user_id, $new_password, $cooldownCutoff)) {
            throw $cooldownException;
        }

        $isLocal = $this->config->get('app.env') === 'local';

        $data = $isLocal ? ['password' => $new_password] : null;

        $status = $isLocal ? 200 : 204;

        return JsonResponse::write($response, $data, $status);
    }

    public function forgot(Request $request, Response $response): Response
    {
        // body: email
        $data = $this->validateRequest($request, 'forgot');

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

        $data = $this->config->get('app.env') === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
    }

    public function reset(Request $request, Response $response): Response
    {
        // body: token, new_password, new_password_repeat
        $data = $this->validateRequest($request, 'reset');

        if (!$this->passwordService->reset($data['token'], $data['new_password'])) {
            throw new HttpUnprocessableEntityException($request, [
                'token' => 'The password reset token is invalid or expired.',
            ]);
        }

        return JsonResponse::write($response, status: 204);
    }
}
