<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRolesAndPermissionsTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('roles')
            ->addColumn('name', 'string', ['null' => false])
            ->addIndex('name', ['unique' => true])
            ->create();

        $this->table('permissions')
            ->addColumn('name', 'string', ['null' => false])
            ->addIndex('name', ['unique' => true])
            ->create();

        $this->table('permission_role', ['id' => false,'primary_key' => ['permission_id', 'role_id']])
            ->addColumn('permission_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('role_id', 'integer', ['null' => false, 'signed' => false])
            ->addForeignKey('permission_id', 'permissions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('role_user', ['id' => false, 'primary_key' => ['role_id', 'user_id']])
            ->addColumn('role_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addForeignKey('role_id', 'roles', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
