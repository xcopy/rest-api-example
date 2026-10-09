<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Handlers\ErrorHandler;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpTooManyRequestsException;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private ClientInterface $redis) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $packedAddress = is_string($remoteAddress) ? inet_pton($remoteAddress) : false;

        if ($packedAddress === false) {
            throw new \RuntimeException('A valid client IP address is required for rate limiting.');
        }

        $config = require dirname(__DIR__, 2) . '/config/rate-limit.php';

        $now = time();
        $windowStart = intdiv($now, $config['window']) * $config['window'];
        $resetAfter = ($windowStart + $config['window']) - $now;
        $ipHash = hash('sha256', $packedAddress);
        $key = sprintf('rate-limit:%s:%d', $ipHash, $windowStart);

        $requestCount = $this->redis->eval(
            <<<'LUA'
local current = tonumber(redis.call('GET', KEYS[1]) or '0')
local limit = tonumber(ARGV[1])

if current >= limit then
    return -1
end

current = redis.call('INCR', KEYS[1])

if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[2])
end

return current
LUA,
            1,
            $key,
            (string) $config['limit'],
            (string) $resetAfter
        );

        if (!is_int($requestCount)) {
            throw new \RuntimeException('Unable to update the API rate limit counter in Redis.');
        }

        $blocked = $requestCount === -1;
        $requestCount = $blocked ? $config['limit'] : $requestCount;
        $remaining = max(0, $config['limit'] - $requestCount);

        $headers = [
            'RateLimit-Limit' => (string) $config['limit'],
            'RateLimit-Remaining' => (string) ($blocked ? 0 : $remaining),
            'RateLimit-Reset' => (string) $resetAfter,
        ];

        if ($blocked) {
            $headers['Retry-After'] = (string) $resetAfter;

            throw new HttpTooManyRequestsException(
                ErrorHandler::withErrorHeaders($request, $headers)
            );
        }

        $response = $handler->handle($request);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
