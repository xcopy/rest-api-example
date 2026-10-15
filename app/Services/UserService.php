<?php

declare(strict_types=1);

namespace App\Services;

use App\Concerns\HasFactory;
use App\Exceptions\NotFoundException;
use App\Repositories\UserRepository;
use App\Services\Concerns\HasRepository;
use App\Services\Concerns\ValidatesData;
use App\Validators\UserValidator;
use DI\Attribute\Inject;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use RuntimeException;
use Throwable;

class UserService
{
    use HasFactory;
    use HasRepository;
    use ValidatesData;

    #[Inject]
    private Adapter $db;

    #[Inject]
    private Sql $sql;

    protected function repositoryClass(): string
    {
        return UserRepository::class;
    }

    protected function validatorClass(): string
    {
        return UserValidator::class;
    }

    /**
     * @param array<string, string> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $data = $this->validate($data, 'create');
        $data['password'] = hashPassword($data['password']);

        $repository = $this->getRepository();

        $user = null;

        $connection = $this->db->getDriver()->getConnection();
        $connection->beginTransaction();

        try {
            if ($user_id = $repository->insert($data)) {
                $user = $repository->findOne($user_id);
            }

            if ($user === null) {
                throw new RuntimeException('The newly created user could not be loaded.');
            }

            $select = $this->sql
                ->select('roles')
                ->columns(['id'])
                ->where(['name' => 'user'])
                ->limit(1);

            $role = $this->sql
                ->prepareStatementForSqlObject($select)
                ->execute()
                ->current();

            if ($role === false) {
                throw new RuntimeException('The default user role is not configured.');
            }

            $insert = $this->sql
                ->insert('role_user')
                ->values([
                    'role_id' => $role['id'],
                    'user_id' => $user_id,
                ]);

            $this->sql
                ->prepareStatementForSqlObject($insert)
                ->execute();

            $connection->commit();
        } catch (Throwable $e) {
            $connection->rollback();
            throw $e;
        }

        return $user;
    }

    /** @param array<string, string> $data */
    public function update(int $id, array $data): void
    {
        $this->findOne($id);

        $data = $this->validate($data, 'update');

        $this->getRepository()->update($data, compact('id'));
    }

    public function delete(int $id): void
    {
        $this->findOne($id);

        $this->getRepository()->delete(compact('id'));
    }

    /**
     * @param int|array<string, mixed> $where
     * @return array<string, mixed>
     */
    public function findOne(int|array $where): array
    {
        $user = $this->getRepository()->findOne($where);

        if ($user === null) {
            throw new NotFoundException();
        }

        return $user;
    }
}
