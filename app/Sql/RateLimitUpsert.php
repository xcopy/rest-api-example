<?php

declare(strict_types=1);

namespace App\Sql;

use Laminas\Db\Sql\Insert;

class RateLimitUpsert extends Insert
{
    public function __construct(int $limit)
    {
        parent::__construct('api_rate_limits');

        $this->specifications[self::SPECIFICATION_INSERT]
            = 'INSERT INTO %1$s (%2$s) VALUES (%3$s)
            ON CONFLICT ("ip_hash") DO UPDATE SET
                "request_count" = CASE
                    WHEN "window_start" = excluded."window_start" THEN "request_count" + 1
                    ELSE 1
                END,
                "window_start" = excluded."window_start"
            WHERE "window_start" != excluded."window_start" OR "request_count" < ' . $limit;
    }
}
