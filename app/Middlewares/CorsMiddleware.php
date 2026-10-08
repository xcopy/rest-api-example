<?php

declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private const ALLOWED_ORIGINS = ['http://localhost:8000'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $allowed = $origin !== '' && in_array($origin, self::ALLOWED_ORIGINS, true);
        $isPreflight = $request->getMethod() === 'OPTIONS'
            && $request->hasHeader('Origin')
            && $request->hasHeader('Access-Control-Request-Method');

        // Preflight is answered directly; never reaches auth or the rate limiter
        $response = $isPreflight ? new Response(204) : $handler->handle($request);

        // Always, regardless of whether the origin is allowed
        $response = $response->withAddedHeader('Vary', 'Origin');

        if (!$allowed) {
            return $response;
        }

        if ($isPreflight) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                ->withHeader('Access-Control-Max-Age', '600');
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Expose-Headers', 'RateLimit-Limit, RateLimit-Remaining, RateLimit-Reset, Retry-After');
    }
}
