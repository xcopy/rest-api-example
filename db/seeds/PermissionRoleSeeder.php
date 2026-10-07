<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class PermissionRoleSeeder extends AbstractSeed
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
        $table = $this->table('permission_role');

        $table->truncate();

        $roles = array_column($this->fetchAll('SELECT id, name FROM roles'), 'id', 'name');
        $permissions = array_column($this->fetchAll('SELECT id, name FROM permissions'), 'id', 'name');

        if (!isset($roles['admin'], $roles['user'])) {
            throw new RuntimeException('The admin and user roles must be seeded before role permissions.');
        }

        $data = [];

        foreach ($permissions as $permissionId) {
            $data[] = ['permission_id' => $permissionId, 'role_id' => $roles['admin']];
        }

        foreach (['show user', 'update user'] as $permissionName) {
            if (!isset($permissions[$permissionName])) {
                throw new RuntimeException("The $permissionName permission must be seeded before role permissions.");
            }

            $data[] = [
                'permission_id' => $permissions[$permissionName],
                'role_id' => $roles['user'],
            ];
        }

        $table->insert($data)->saveData();
    }
}
