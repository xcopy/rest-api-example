<?php

namespace App\Http;

use Psr\Http\Message\ResponseInterface as Response;

class JsonResponse
{
    public static function write(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
