<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEmailVerificationTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('email_verification_tokens')
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addTimestamps()
            ->addIndex('token_hash', ['unique' => true])
            ->addIndex('expires_at')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
