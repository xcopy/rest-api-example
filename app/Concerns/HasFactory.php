<?php

declare(strict_types=1);

namespace App\Concerns;

use DI\Attribute\Inject;
use DI\FactoryInterface;

trait HasFactory
{
    #[Inject]
    private FactoryInterface $factory;

    protected function factory(): FactoryInterface
    {
        return $this->factory;
    }
}
