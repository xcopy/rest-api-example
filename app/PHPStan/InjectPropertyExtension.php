<?php

declare(strict_types=1);

namespace App\PHPStan;

use DI\Attribute\Inject;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

final class InjectPropertyExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return $this->hasInjectAttribute($property, $propertyName);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return $this->hasInjectAttribute($property, $propertyName);
    }

    private function hasInjectAttribute(PropertyReflection $property, string $propertyName): bool
    {
        $nativeProperty = $property
            ->getDeclaringClass()
            ->getNativeReflection()
            ->getProperty($propertyName);

        return $nativeProperty->getAttributes(Inject::class) !== [];
    }
}
