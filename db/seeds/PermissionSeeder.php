<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class PermissionSeeder extends AbstractSeed
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
        $table = $this->table('permissions');

        $table->truncate();

        $table
            ->insert([
                ['name' => 'list users'],
                ['name' => 'show user'],
                ['name' => 'create user'],
                ['name' => 'update user'],
                ['name' => 'delete user'],
            ])
            ->saveData();
    }
}
