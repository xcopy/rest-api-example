<?php

declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

class TrimInputMiddleware implements MiddlewareInterface
{
    /** @var array<string, bool> */
    private array $except;

    /**
     * @param list<string> $except
     */
    public function __construct(array $except = [
        'password',
        'password_repeat',
        'current_password',
        'new_password',
        'new_password_repeat',
    ])
    {
        $this->except = array_fill_keys(array_map('strtolower', $except), true);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->trimQueryParams($request);
        $request = $this->trimParsedBody($request);

        return $handler->handle($request);
    }

    private function trimQueryParams(ServerRequestInterface $request): ServerRequestInterface
    {
        return $request->withQueryParams($this->trimArray($request->getQueryParams()));
    }

    private function trimParsedBody(ServerRequestInterface $request): ServerRequestInterface
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $request->withParsedBody($this->trimArray($parsedBody));
        }

        if (is_object($parsedBody)) {
            $trimmed = $this->trimObject($parsedBody);

            return $request->withParsedBody($trimmed);
        }

        return $request;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function trimArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->trimArray($value);
                continue;
            }

            if (is_object($value)) {
                $data[$key] = $this->trimObject($value);
                continue;
            }

            if ($this->shouldSkip((string) $key)) {
                continue;
            }

            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        return $data;
    }

    private function trimObject(object $value): object
    {
        if (!$value instanceof stdClass) {
            return $value;
        }

        $trimmed = [];

        foreach ((array) $value as $key => $propertyValue) {
            if ($this->shouldSkip((string) $key)) {
                $trimmed[$key] = $propertyValue;
                continue;
            }

            if (is_string($propertyValue)) {
                $trimmed[$key] = trim($propertyValue);
                continue;
            }

            if (is_array($propertyValue)) {
                $trimmed[$key] = $this->trimArray($propertyValue);
                continue;
            }

            if (is_object($propertyValue)) {
                $trimmed[$key] = $this->trimObject($propertyValue);
                continue;
            }

            $trimmed[$key] = $propertyValue;
        }

        return (object) $trimmed;
    }

    private function shouldSkip(string $key): bool
    {
        return isset($this->except[strtolower($key)]);
    }
}
