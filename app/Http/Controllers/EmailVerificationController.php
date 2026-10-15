<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Services\EmailVerificationService;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class EmailVerificationController extends Controller
{
    #[Inject]
    private EmailVerificationService $emailVerification;

    public function send(Request $request, Response $response): Response
    {
        $email = $request->getAttribute('email', '');

        $this->emailVerification->send($email, $token);

        return JsonResponse::write($response, status: 202);
    }

    public function verify(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $this->emailVerification->verify($data['token'] ?? '');

        return JsonResponse::write($response, status: 204);
    }
}
