<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class PermissionSeeder extends AbstractSeed
{
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
