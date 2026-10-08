<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateApiRateLimitsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('api_rate_limits', ['id' => false, 'primary_key' => ['ip_hash']])
            ->addColumn('ip_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('window_start', 'integer', ['null' => false])
            ->addColumn('request_count', 'integer', ['null' => false])
            ->addIndex('window_start')
            ->create();
    }
}
