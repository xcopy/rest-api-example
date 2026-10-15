<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Services\UserService;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends Controller
{
    #[Inject]
    private UserService $users;

    /** @param array<string, string> $args */
    public function show(Request $request, Response $response, array $args): Response
    {
        return JsonResponse::write(
            $response,
            $this->users->findOne((int) $args['id'])
        );
    }

    /** @param array<string, string> $args */
    public function update(Request $request, Response $response, array $args): Response
    {
        $this->users->update((int) $args['id'], $request->getParsedBody() ?? []);

        return JsonResponse::write($response, status: 204);
    }

    /** @param array<string, string> $args */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->users->delete((int) $args['id']);

        return JsonResponse::write($response, status: 204);
    }
}
