<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\DateTimeService;
use App\Services\PasswordService;
use App\Validators\PasswordValidator;
use DateInterval;
use Laminas\Db\Adapter\Adapter;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpUnauthorizedException;

class PasswordController extends Controller
{
    private PasswordValidator $validator;

    /** @var array<string, string> */
    private array $config;

    public function __construct(
        Adapter $db,
        Form $form,
        private PasswordService $passwordService
    ) {
        parent::__construct($db);

        $this->validator = new PasswordValidator($form);
        $this->config = require APP_BASE_PATH . '/config/app.php';
    }

    public function change(Request $request, Response $response): Response
    {
        // body: current_password, new_password, new_password_repeat
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'change');

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

        $cooldownCutoff = DateTimeService::now()->sub(new DateInterval('PT24H'));
        $cooldownException = new HttpUnprocessableEntityException($request, [
            'new_password' => 'Password can only be changed once every 24 hours.',
        ]);

        if (
            $user['password_changed_at'] !== null
            && DateTimeService::make($user['password_changed_at']) > $cooldownCutoff
        ) {
            throw $cooldownException;
        }

        if (!$this->passwordService->change($user_id, $new_password, $cooldownCutoff)) {
            throw $cooldownException;
        }

        $isLocal = $this->config['env'] === 'local';

        $data = $isLocal
            ? ['password' => $new_password]
            : null;

        $status = $isLocal ? 200 : 204;

        return JsonResponse::write($response, $data, $status);
    }

    public function forgot(Request $request, Response $response): Response
    {
        // body: email
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'forgot');

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

    public function reset(Request $request, Response $response): Response
    {
        // body: token, new_password, new_password_repeat
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'reset');

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $this->validator->getErrors());
        }

        if (!$this->passwordService->reset($data['token'], $data['new_password'])) {
            throw new HttpUnprocessableEntityException($request, [
                'token' => 'The password reset token is invalid or expired.',
            ]);
        }

        return JsonResponse::write($response, status: 204);
    }
}
