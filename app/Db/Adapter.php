<?php

declare(strict_types=1);

namespace App\Db;

use Laminas\Db\Adapter\Adapter as BaseAdapter;
use Laminas\Db\Adapter\Driver\Pdo\Connection;
use Throwable;

class Adapter extends BaseAdapter
{
    public function transaction(callable $callback): mixed
    {
        /** @var Connection $connection */
        $connection = $this->getDriver()->getConnection();

        if ($connection->inTransaction()) {
            return $callback();
        }

        $connection->beginTransaction();

        try {
            $result = $callback();
            $connection->commit();
        } catch (Throwable $e) {
            // @phpstan-ignore if.alwaysFalse
            if ($connection->inTransaction()) {
                $connection->rollback();
            }
            throw $e;
        }

        return $result;
    }
}
