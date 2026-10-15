<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\JsonResponse;
use App\Repositories\AccessTokenRepository;
use App\Services\AuthService;
use App\Services\EmailVerificationService;
use App\Services\UserService;
use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpUnauthorizedException;

final class AuthController extends Controller
{
    #[Inject]
    private AuthService $auth;

    #[Inject]
    private EmailVerificationService $emailVerification;

    #[Inject]
    private AccessTokenRepository $accessTokenRepository;

    #[Inject]
    private UserService $users;

    public function register(Request $request, Response $response): Response
    {
        $user = $this->users->create($request->getParsedBody() ?? []);

        $this->emailVerification->send($user['email']);

        return JsonResponse::write($response, $user, 201);
    }

    public function login(Request $request, Response $response): Response
    {
        $user = $this->auth->login($request->getParsedBody() ?? []);

        if ($user === null) {
            throw new HttpUnauthorizedException($request, 'Invalid email or password.');
        }

        $token = generateToken();
        $expiresAt = now()->modify(config('auth.access_token_ttl'));

        $this->accessTokenRepository
            ->insert([
                'user_id' => $user['id'],
                'token_hash' => hashToken($token),
                'expires_at' => $expiresAt->format(DATE_FORMAT_DB),
            ]);

        return JsonResponse::write($response, [
            'access_token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->accessTokenRepository
            ->delete(['token_hash' => $request->getAttribute('token_hash')]);

        return JsonResponse::write($response, status: 204);
    }

    public function logoutAll(Request $request, Response $response): Response
    {
        $sql = $this->accessTokenRepository
            ->delete(['user_id' => $request->getAttribute('user_id')]);

        return JsonResponse::write($response, status: 204);
    }
}
