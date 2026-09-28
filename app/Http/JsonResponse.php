<?php

namespace App\Http;

use Psr\Http\Message\ResponseInterface as Response;

class JsonResponse
{
    public static function write(Response $response, mixed $data = null, int $status = 200): Response
    {
        if ($data !== null) {
            $response->getBody()->write(json_encode($data));
        }

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
