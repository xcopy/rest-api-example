<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\HttpUnprocessableEntityException;
use App\Validators\Validator;
use DI\Attribute\Inject;
use DI\FactoryInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class ValidatingController extends Controller
{
    #[Inject]
    protected FactoryInterface $factory;

    /** @return class-string<Validator> */
    abstract protected function getValidatorClass(): string;

    protected function getValidator(): Validator
    {
        return $this->factory->make($this->getValidatorClass());
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function validateRequest(
        Request $request,
        string $scenario = 'create',
        array $context = []
    ): array {
        $validator = $this->getValidator();

        $data = $validator->validate($request->getParsedBody() ?? [], $scenario, $context);

        if ($data === false) {
            throw new HttpUnprocessableEntityException($request, $validator->getErrors());
        }

        return $data;
    }
}
