<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['null' => false])
            ->addColumn('password', 'string', ['null' => false])
            ->addColumn('first_name', 'string', ['null' => false])
            ->addColumn('last_name', 'string', ['null' => false])
            ->addColumn('email_verified_at', 'timestamp', ['null' => true])
            ->addColumn('password_changed_at', 'timestamp', ['null' => true])
            ->addTimestamps()
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
