<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Http\JsonResponse;
use App\Services\EmailVerificationService;
use App\Validators\EmailVerificationValidator;
use Laminas\Db\Adapter\Adapter;
use Leaf\Form;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class EmailVerificationController extends Controller
{
    private EmailVerificationValidator $validator;

    /** @var array<string, string> */
    private array $config;

    public function __construct(
        Adapter $db,
        Form $form,
        private EmailVerificationService $emailVerification
    ) {
        parent::__construct($db);

        $this->validator = new EmailVerificationValidator($form);
        $this->config = require APP_BASE_PATH . '/config/app.php';
    }

    public function send(Request $request, Response $response): Response
    {
        // body: email
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'send');

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

        $this->emailVerification->send((int) $user['id'], $user['email'], $token);

        $data = $this->config['env'] === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
    }

    public function verify(Request $request, Response $response): Response
    {
        // body: token
        $data = $this->validator->validate($request->getParsedBody() ?? [], 'verify');

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
}
