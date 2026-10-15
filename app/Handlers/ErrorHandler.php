<?php

declare(strict_types=1);

namespace App\Handlers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler as BaseErrorHandler;

final class ErrorHandler extends BaseErrorHandler
{
    private const ATTRIBUTE = 'exception_headers';

    /** @param array<string, string> $headers */
    public static function withErrorHeaders(
        ServerRequestInterface $request,
        array $headers,
    ): ServerRequestInterface {
        // Merge with any headers already set earlier in the chain
        $existing = $request->getAttribute(self::ATTRIBUTE, []);

        return $request->withAttribute(self::ATTRIBUTE, $headers + $existing);
    }

    protected function respond(): ResponseInterface
    {
        $response = parent::respond();

        if ($this->exception instanceof HttpException) {
            $headers = $this->exception
                ->getRequest()
                ->getAttribute(self::ATTRIBUTE, []);

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
