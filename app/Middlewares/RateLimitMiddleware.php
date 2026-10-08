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

        $config = require BASE_PATH . '/config/rate-limit.php';

        $now = time();
        $windowStart = intdiv($now, $config['window']) * $config['window'];
        $resetAfter = ($windowStart + $config['window']) - $now;
        $ipHash = hash('sha256', $packedAddress);

        $deleteExpired = $this->sql
            ->delete('api_rate_limits')
            ->where(['window_start < ?' => $windowStart]);

        $this->sql
            ->prepareStatementForSqlObject($deleteExpired)
            ->execute();

        $upsert = (new RateLimitUpsert($config['limit']))
            ->values([
                'ip_hash' => $ipHash,
                'window_start' => $windowStart,
                'request_count' => 1,
            ]);

        $result = $this->sql
            ->prepareStatementForSqlObject($upsert)
            ->execute();

        $blocked = $result->getAffectedRows() === 0;
        $requestCount = $config['limit'];

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
