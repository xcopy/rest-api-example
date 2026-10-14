<?php

declare(strict_types=1);

use App\Error\Renderers\JsonErrorRenderer;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ServiceException;
use App\Exceptions\UnprocessableEntityException;
use App\Handlers\ErrorHandler;
use App\Http\JsonResponse;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\TrimInputMiddleware;
use App\Services\ConfigService;
use Middlewares\TrailingSlash;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Slim\App;
use Slim\Middleware\ContentLengthMiddleware;

return function (App $app) {
    // Order matters: LIFO (Last-In, First-Out)
    $app->add(TrimInputMiddleware::class);
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(ContentLengthMiddleware::class);
    $app->add(TrailingSlash::class);
    $app->add(RateLimitMiddleware::class);

    $config = $app->getContainer()?->get(ConfigService::class);

    if (!$config instanceof ConfigService) {
        throw new RuntimeException('The configuration service is not available.');
    }

    $isProduction = $config->get('app.env') === 'production';

    $stream = new RotatingFileHandler(
        APP_BASE_PATH . '/logs/app.log',
        maxFiles: 14, // two weeks
        level: $isProduction ? Level::Warning : Level::Debug
    );
    $stream->setFormatter(new LineFormatter(
        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        DATE_FORMAT_LOG,
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
    // domain/service exceptions -> HTTP exceptions mapping
    $errorMiddleware->setErrorHandler(
        ServiceException::class,
        function ($request, ServiceException $e) use ($app) {
            $status = match (true) {
                $e instanceof BadRequestException => 400,
                $e instanceof NotFoundException => 404,
                $e instanceof ConflictException => 409,
                $e instanceof UnprocessableEntityException => 422,
                default => 400
            };

            $response = $app->getResponseFactory()->createResponse($status);
            $data = ['message' => $e->getMessage()];

            if ($e instanceof UnprocessableEntityException) {
                $data['errors'] = $e->getErrors();
            }

            return JsonResponse::write($response, $data, $status);
        },
        handleSubclasses: true
    );

    $app->add(CorsMiddleware::class);
};
