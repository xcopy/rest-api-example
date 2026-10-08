<?php

declare(strict_types=1);

namespace App\Exceptions;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpSpecializedException;

class HttpUnprocessableEntityException extends HttpSpecializedException
{
    protected $code = 422;

    protected $message = 'Unprocessable entity';

    protected string $title = '422 Unprocessable Entity';

    private array $errors;

    public function __construct(ServerRequestInterface $request, array $errors)
    {
        parent::__construct($request);

        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
