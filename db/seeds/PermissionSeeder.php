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
        $this->execute('delete from permission_role');
        $this->execute('delete from permissions');

        $this->table('permissions')
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
