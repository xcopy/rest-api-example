<?php

declare(strict_types=1);

namespace App\Validators;

use Laminas\Db\Sql\Sql;
use Leaf\Form;

abstract class Validator
{
    private Form $form;

    /** @var array<string, string|list<string>> */
    private array $errors = [];

    public function __construct(Sql $sql)
    {
        $this->form = new Form();

        $this->form->rule('unique', function ($value, $param, $field) use ($sql) {
            $params = explode(',', $param);

            $table = $params[0];
            $currentUserId = $params[1] ?? null;

            $select = $sql
                ->select($table)
                ->where([$field => $value])
                ->limit(1);

            if ($currentUserId !== null) {
                $select->where(['id != ?' => $currentUserId]);
            }

            return $sql->prepareStatementForSqlObject($select)
                ->execute()
                ->count() === 0;
        }, 'The value for the field {field} is already taken');
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    abstract protected function rules(string $scenario, array $context = []): array;

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     * @return array<string, mixed>|false
     */
    public function validate(array $data, string $scenario = 'create', array $context = []): array|false
    {
        $rules = $this->rules($scenario, $context);

        $result = $this->form->validate($data, $rules);

        if ($result === false) {
            $this->errors = $this->form->errors();

            return false;
        }

        return $result;
    }

    /** @return array<string, string|list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
