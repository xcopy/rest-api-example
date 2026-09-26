<?php

namespace App\Exception;

use Slim\Exception\HttpSpecializedException;

class HttpUnprocessableEntityException extends HttpSpecializedException
{
    protected $code = 422;

    protected string $title = '422 Unprocessable Entity';
}
