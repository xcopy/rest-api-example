<?php

declare(strict_types=1);

namespace App\Repositories;

use DI\Attribute\Inject;
use Laminas\Db\Sql\Sql;
use RuntimeException;

abstract class Repository
{
    #[Inject]
    protected Sql $sql;

    /** @var list<string> */
    private array $additionalColumns = [];

    abstract protected function getTable(): string;

    /** @return array<string> */
    protected function getColumns(): array
    {
        return ['*'];
    }

    /** @param list<string> $columns */
    public function addColumns(array $columns): static
    {
        $repository = clone $this;
        $repository->additionalColumns = array_values(array_unique([
            ...$this->additionalColumns,
            ...$columns,
        ]));

        return $repository;
    }

    /** @return list<string> */
    private function columns(): array
    {
        return array_values(array_unique([
            ...$this->getColumns(),
            ...$this->additionalColumns,
        ]));
    }

    /** @param array<string, mixed> $where
     *  @return list<array<string, mixed>>
     */
    public function findAll(array $where = []): array
    {
        $select = $this->sql
            ->select($this->getTable())
            ->columns($this->columns());

        if ($where !== []) {
            $select->where($where);
        }

        $result = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute();

        return iterator_to_array($result, false);
    }

    /**
     * @param int|array<string, mixed> $where
     * @return array<string, mixed>|null
     */
    public function findOne(int|array $where): ?array
    {
        if ($where === []) {
            throw new RuntimeException('A where condition is required to find a repository record');
        }

        if (is_int($where)) {
            $where = ['id' => $where];
        }

        $select = $this->sql
            ->select($this->getTable())
            ->columns($this->columns())
            ->where($where)
            ->limit(1);

        $result = $this->sql
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return $result === false ? null : $result;
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $insert = $this->sql
            ->insert($this->getTable())
            ->values($data);

        $result = $this->sql
            ->prepareStatementForSqlObject($insert)
            ->execute();

        return (int) $result->getGeneratedValue();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update(array $data, array $where): int
    {
        if ($where === []) {
            throw new RuntimeException('A where condition is required to update repository records');
        }

        $update = $this->sql
            ->update($this->getTable())
            ->set($data)
            ->where($where);

        return $this->sql
            ->prepareStatementForSqlObject($update)
            ->execute()
            ->getAffectedRows();
    }

    /** @param array<string, mixed> $where */
    public function delete(array $where): int
    {
        if ($where === []) {
            throw new RuntimeException('A where condition is required to delete repository records');
        }

        $delete = $this->sql
            ->delete($this->getTable())
            ->where($where);

        return $this->sql
            ->prepareStatementForSqlObject($delete)
            ->execute()
            ->getAffectedRows();
    }
}
