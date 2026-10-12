<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class ConfigService
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;

        foreach (explode('.', $key) as $segment) {
            if ($segment === '' || !is_array($value) || !array_key_exists($segment, $value)) {
                if (func_num_args() > 1) {
                    return $default;
                }

                throw new InvalidArgumentException(sprintf('Configuration key "%s" was not found.', $key));
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
