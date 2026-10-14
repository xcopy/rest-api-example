<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Exceptions\UnprocessableEntityException;
use App\Validators\Validator;
use DI\FactoryInterface;

trait ValidatesData
{
    abstract protected function factory(): FactoryInterface;

    /** @return class-string<Validator> */
    abstract protected function validatorClass(): string;

    protected function getValidator(): Validator
    {
        return $this->factory()->make($this->validatorClass());
    }

    /**
     * @param array<string, string> $data
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function validate(array $data, string $scenario, array $context = []): array
    {
        $validator = $this->getValidator();

        $data = $validator->validate($data, $scenario, $context);

        if ($data === false) {
            throw new UnprocessableEntityException($validator->getErrors());
        }

        return $data;
    }
}
