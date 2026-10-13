<?php

declare(strict_types=1);

namespace App\Validators;

use DI\Attribute\Inject;
use Leaf\Form;

abstract class Validator
{
    #[Inject]
    protected Form $form;

    /** @var array<string, string|list<string>> */
    protected array $errors = [];

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
