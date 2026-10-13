<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\EmailVerificationService;
use App\Validators\EmailVerificationValidator;
use App\Validators\Validator;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class EmailVerificationController extends ValidatingController
{
    #[Inject]
    protected EmailVerificationService $emailVerification;

    protected function getValidator(): Validator
    {
        return new EmailVerificationValidator($this->form);
    }

    public function send(Request $request, Response $response): Response
    {
        // body: email
        $data = $this->validateRequest($request, 'send');

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

        $this->emailVerification->send((int) $user['id'], $user['email'], $token);

        $data = $this->config->get('app.env') === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
    }

    public function verify(Request $request, Response $response): Response
    {
        // body: token
        $data = $this->validateRequest($request, 'verify');

        if (!$this->emailVerification->verify($data['token'])) {
            throw new HttpUnprocessableEntityException($request, [
                'token' => 'The verification token is invalid or expired.',
            ]);
        }

        return JsonResponse::write($response, status: 204);
    }
}
