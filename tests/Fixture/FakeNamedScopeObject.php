<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;

/**
 * Stand-in for a generated Data Object class with a "name" getter (e.g. Product/Category), the
 * shape DataQualityRuleDescriptionListener::resolveScopeLabel() reflects on via method_exists.
 */
final class FakeNamedScopeObject extends Concrete
{
    private ?string $name = null;

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
