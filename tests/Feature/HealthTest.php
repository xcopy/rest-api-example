<?php

declare(strict_types=1);

it('returns 200 on health endpoint', function () {
    $response = $this->request('GET', '/health');

    expect($response->getStatusCode())->toBe(200);
    expect($this->json($response))->toMatchArray(['status' => 'ok']);
});

it('returns 404 for unknown route', function () {
    expect($this->request('GET', '/nope')->getStatusCode())->toBe(404);
});
