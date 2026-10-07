<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLoginAttemptsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $this->table('login_attempts')
            ->addColumn('email', 'string', ['null' => false])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => false])
            ->addColumn('successful', 'boolean', ['default' => false])
            ->addColumn('attempted_at', 'datetime', ['null' => false])
            ->addTimestamps()
            ->addIndex(['email', 'attempted_at'])
            ->addIndex(['ip', 'attempted_at'])
            ->create();
    }
}
