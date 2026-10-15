<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Services\UserService;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserController extends Controller
{
    #[Inject]
    private UserService $users;

    public function show(Request $request, Response $response): Response
    {
        return JsonResponse::write(
            $response,
            $this->users->findOne($request->getAttribute('user_id'))
        );
    }

    public function update(Request $request, Response $response): Response
    {
        $this->users->update(
            $request->getAttribute('user_id'),
            $request->getParsedBody() ?? []
        );

        return JsonResponse::write($response, status: 204);
    }

    public function delete(Request $request, Response $response): Response
    {
        $this->users->delete($request->getAttribute('user_id'));

        return JsonResponse::write($response, status: 204);
    }
}
