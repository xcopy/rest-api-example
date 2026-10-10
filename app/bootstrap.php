<?php

declare(strict_types=1);

defined('APP_BASE_PATH') or define('APP_BASE_PATH', dirname(__DIR__));

require APP_BASE_PATH . '/vendor/autoload.php';

Dotenv\Dotenv::createUnsafeImmutable(APP_BASE_PATH)->safeLoad();
