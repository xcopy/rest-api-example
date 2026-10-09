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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = require dirname(__DIR__, 2) . '/config/cors.php';

        $origin = $request->getHeaderLine('Origin');
        $allowed = $origin !== '' && in_array($origin, $config['allowed_origins']);
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
                ->withHeader('Access-Control-Allow-Methods', $config['allowed_methods'])
                ->withHeader('Access-Control-Allow-Headers', $config['allowed_headers'])
                ->withHeader('Access-Control-Max-Age', $config['max_age']);
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Expose-Headers', $config['exposed_headers']);
    }
}
