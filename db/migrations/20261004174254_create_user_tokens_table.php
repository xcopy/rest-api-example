<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_tokens')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addTimestamps()
            ->addIndex('token_hash', ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
