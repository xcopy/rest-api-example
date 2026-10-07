<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class RoleUserSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        $table = $this->table('role_user');

        $table->truncate();

        $table
            ->insert([
                ['role_id' => 1, 'user_id' => 1],
            ])
            ->saveData();
    }
}
