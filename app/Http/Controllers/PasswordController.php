<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Services\PasswordService;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PasswordController extends Controller
{
    #[Inject]
    private PasswordService $password;

    public function change(Request $request, Response $response): Response
    {
        $userId = (int) $request->getAttribute('user_id');

        $this->password->change($userId, $request->getParsedBody() ?? []);

        return JsonResponse::write($response, status: 204);
    }

    public function forgot(Request $request, Response $response): Response
    {
        $this->password->sendReset($request->getParsedBody() ?? [], $token);

        $data = config('app.env') === 'local' ? compact('token') : null;

        return JsonResponse::write($response, $data, 202);
    }

    public function reset(Request $request, Response $response): Response
    {
        $this->password->reset($request->getParsedBody() ?? []);

        return JsonResponse::write($response, status: 204);
    }
}
