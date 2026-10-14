<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Repositories\Repository;
use DI\FactoryInterface;

trait HasRepository
{
    abstract protected function factory(): FactoryInterface;

    /** @return class-string<Repository> */
    abstract protected function repositoryClass(): string;

    protected function getRepository(): Repository
    {
        return $this->factory()->make($this->repositoryClass());
    }
}
