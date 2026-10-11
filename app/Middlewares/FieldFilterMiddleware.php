<?php

declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

abstract class FieldFilterMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $fields;

    public function __construct(string ...$fields)
    {
        $this->fields = $fields;
    }

    abstract protected function filter(string $value): string;

    public function process(Request $request, Handler $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            foreach ($this->fields as $field) {
                if (isset($body[$field]) && is_string($body[$field])) {
                    $body[$field] = $this->filter($body[$field]);
                }
            }

            $request = $request->withParsedBody($body);
        }

        return $handler->handle($request);
    }
}
