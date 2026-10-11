<?php

declare(strict_types=1);

use App\Error\Renderers\JsonErrorRenderer;
use App\Handlers\ErrorHandler;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use Middlewares\TrailingSlash;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Slim\App;
use Slim\Middleware\ContentLengthMiddleware;

return function (App $app) {
    // Order matters: LIFO (Last-In, First-Out)
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(ContentLengthMiddleware::class);
    $app->add(TrailingSlash::class);
    $app->add(RateLimitMiddleware::class);

    $config = require APP_BASE_PATH . '/config/app.php';
    $isProduction = $config['env'] === 'production';

    $stream = new RotatingFileHandler(
        APP_BASE_PATH . '/logs/app.log',
        maxFiles: 14, // two weeks
        level: $isProduction ? Level::Warning : Level::Debug
    );
    $stream->setFormatter(new LineFormatter(
        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        'Y-m-d H:i:s',
        allowInlineLineBreaks: true,
        ignoreEmptyContextAndExtra: true
    ));
    $logger = (new Logger('app'))->pushHandler($stream);

    $errorHandler = new ErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $logger
    );
    $errorHandler->forceContentType('application/json');
    $errorHandler->registerErrorRenderer('application/json', JsonErrorRenderer::class);

    $errorMiddleware = $app->addErrorMiddleware(
        displayErrorDetails: !$isProduction,
        logErrors: true,
        logErrorDetails: true
    );
    $errorMiddleware->setDefaultErrorHandler($errorHandler);

    $app->add(CorsMiddleware::class);
};
