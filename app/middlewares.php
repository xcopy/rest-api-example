<?php

declare(strict_types=1);

use App\Error\Renderers\JsonErrorRenderer;
use App\Handlers\ErrorHandler;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use Middlewares\TrailingSlash;
use Slim\App;
use Slim\Middleware\ContentLengthMiddleware;

return function (App $app) {
    // Order matters: LIFO (Last-In, First-Out)
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(ContentLengthMiddleware::class);
    $app->add(TrailingSlash::class);
    $app->add(RateLimitMiddleware::class);

    $errorHandler = new ErrorHandler($app->getCallableResolver(), $app->getResponseFactory());
    $errorHandler->forceContentType('application/json');
    $errorHandler->registerErrorRenderer('application/json', JsonErrorRenderer::class);

    $errorMiddleware = $app->addErrorMiddleware(true, true, true);
    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    $app->add(CorsMiddleware::class);
};
