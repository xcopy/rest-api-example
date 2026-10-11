<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class RoleSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->execute('delete from permission_role');
        $this->execute('delete from role_user');
        $this->execute('delete from roles');

        $this->table('roles')
            ->insert([
                ['name' => 'admin'],
                ['name' => 'user'],
            ])
            ->saveData();
    }
}
