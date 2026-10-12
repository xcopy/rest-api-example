<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class DateTimeService
{
    public const FORMAT = 'Y-m-d H:i:s';

    public static function make(string $datetime, string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone($timezone));
    }

    public static function now(bool $format = false): DateTimeImmutable|string
    {
        $now = self::make('now');

        return $format ? $now->format(self::FORMAT) : $now;
    }
}
