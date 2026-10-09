<?php

declare(strict_types=1);

namespace App\Exceptions;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpSpecializedException;

class HttpUnprocessableEntityException extends HttpSpecializedException
{
    /** @var int */
    protected $code = 422;

    protected $message = 'Unprocessable entity';

    protected string $title = '422 Unprocessable Entity';

    /** @var array<string, string|list<string>> */
    private array $errors;

    /** @param array<string, string|list<string>> $errors */
    public function __construct(ServerRequestInterface $request, array $errors)
    {
        parent::__construct($request);

        $this->errors = $errors;
    }

    /** @return array<string, string|list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
