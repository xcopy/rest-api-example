<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

abstract class TestCase extends BaseTestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');

        if ($env !== 'testing') {
            throw new RuntimeException('Tests must run with APP_ENV=testing');
        }

        // fresh app per test (a new container, no state leaking between tests)
        $this->app = require dirname(__DIR__) . '/app/app.php';
    }

    protected function request(
        string $method,
        string $uri,
        ?array $body = null,
        array $headers = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, $uri, [
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $stream = (new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR));
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream);
        }

        return $this->app->handle($request);
    }

    protected function json(ResponseInterface $response): array
    {
        $response->getBody()->rewind();

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
