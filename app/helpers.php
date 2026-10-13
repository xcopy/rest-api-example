<?php

declare(strict_types=1);

if (!function_exists('parseDateTime')) {
    function parseDateTime(string $datetime, string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone($timezone));
    }
}

if (!function_exists('now')) {
    function now(bool $format = false, string $timezone = 'UTC'): DateTimeImmutable|string
    {
        $now = parseDateTime('now', $timezone);

        return $format ? $now->format(DATE_FORMAT_DB) : $now;
    }
}

if (!function_exists('generateToken')) {
    function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('hashToken')) {
    function hashToken(string $value): string
    {
        return hash('sha256', $value);
    }
}
