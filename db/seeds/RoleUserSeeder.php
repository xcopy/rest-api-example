<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class RoleUserSeeder extends AbstractSeed
{
    public function run(): void
    {
        $table = $this->table('role_user');

        $table->truncate();

        $roles = array_column($this->fetchAll('SELECT id, name FROM roles'), 'id', 'name');

        if (!isset($roles['admin'], $roles['user'])) {
            throw new RuntimeException('The admin and user roles must be seeded before user role assignments.');
        }

        $users = $this->fetchAll('SELECT id FROM users ORDER BY id');
        $data = [];

        foreach ($users as $index => $user) {
            $data[] = [
                'role_id' => $index === 0 ? $roles['admin'] : $roles['user'],
                'user_id' => $user['id'],
            ];
        }

        if ($data !== []) {
            $table->insert($data)->saveData();
        }
    }
}
