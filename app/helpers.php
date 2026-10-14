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

if (!function_exists('hashPassword')) {
    function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('checkPassword')) {
    function checkPassword(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            throw new InvalidArgumentException('Password or/and hash cannot be empty.');
        }

        return password_verify($password, $hash);
    }
}
