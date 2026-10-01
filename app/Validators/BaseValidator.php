<?php

namespace App\Validators;

use Leaf\Form;

abstract class BaseValidator
{
    protected Form $form;

    protected array $errors = [];

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    abstract protected function rules(string $scenario, array $context = []): array;

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

    public function getErrors(): array
    {
        return $this->errors;
    }
}
