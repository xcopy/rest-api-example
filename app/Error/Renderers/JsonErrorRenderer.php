<?php

declare(strict_types=1);

namespace App\Error\Renderers;

use App\Exceptions\HttpUnprocessableEntityException;
use Throwable;

class JsonErrorRenderer extends \Slim\Error\Renderers\JsonErrorRenderer
{
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $output = json_decode(parent::__invoke($exception, $displayErrorDetails), true);

        if ($exception instanceof HttpUnprocessableEntityException) {
            $output['errors'] = $exception->getErrors();
        }

        return (string) json_encode(
            $output,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
