<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Handlers\ErrorHandler;
use App\Sql\RateLimitUpsert;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpTooManyRequestsException;

class RateLimitMiddleware implements MiddlewareInterface
{
    private const LIMIT = 60;

    private const WINDOW_SECONDS = 60;

    private Sql $sql;

    public function __construct(Adapter $db)
    {
        $this->sql = new Sql($db);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $packedAddress = is_string($remoteAddress) ? inet_pton($remoteAddress) : false;

        if ($packedAddress === false) {
            throw new \RuntimeException('A valid client IP address is required for rate limiting.');
        }

        $now = time();
        $windowStart = intdiv($now, self::WINDOW_SECONDS) * self::WINDOW_SECONDS;
        $resetAfter = ($windowStart + self::WINDOW_SECONDS) - $now;
        $ipHash = hash('sha256', $packedAddress);

        $deleteExpired = $this->sql
            ->delete('api_rate_limits')
            ->where(['window_start < ?' => $windowStart]);

        $this->sql
            ->prepareStatementForSqlObject($deleteExpired)
            ->execute();

        $upsert = (new RateLimitUpsert(self::LIMIT))
            ->values([
                'ip_hash' => $ipHash,
                'window_start' => $windowStart,
                'request_count' => 1,
            ]);

        $result = $this->sql
            ->prepareStatementForSqlObject($upsert)
            ->execute();

        $blocked = $result->getAffectedRows() === 0;
        $requestCount = self::LIMIT;

        if (!$blocked) {
            $select = $this->sql
                ->select('api_rate_limits')
                ->columns(['request_count'])
                ->where(['ip_hash' => $ipHash]);
            $row = $this->sql
                ->prepareStatementForSqlObject($select)
                ->execute()
                ->current();

            if ($row === false || $row === null) {
                throw new \RuntimeException('Unable to read the current API rate limit counter.');
            }

            $requestCount = (int) $row['request_count'];
        }

        $remaining = max(0, self::LIMIT - $requestCount);

        if ($blocked) {
            throw new HttpTooManyRequestsException(
                ErrorHandler::withErrorHeaders($request, [
                    'RateLimit-Limit' => (string) self::LIMIT,
                    'RateLimit-Remaining' => '0',
                    'RateLimit-Reset' => (string) $resetAfter,
                    'Retry-After' => (string) $resetAfter,
                ]),
            );
        }

        return $handler->handle($request)
            ->withHeader('RateLimit-Limit', (string) self::LIMIT)
            ->withHeader('RateLimit-Remaining', (string) $remaining)
            ->withHeader('RateLimit-Reset', (string) $resetAfter);
    }
}
