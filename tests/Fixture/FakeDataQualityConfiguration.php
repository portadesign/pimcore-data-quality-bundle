<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\DataObject\Fieldcollection;

/**
 * Stand-in for a generated DataQualityConfiguration Data Object: overrides getRules()/setRules()
 * so tests don't have to touch getClass() (which needs a booted Pimcore kernel/DB), the same way
 * FakeProduct stands in for a generated Product class.
 */
final class FakeDataQualityConfiguration extends DataQualityConfiguration
{
    private ?Fieldcollection $fakeRules = null;

    /**
     * @var list<\Pimcore\Model\DataObject\Concrete>
     */
    private array $fakeScopeObjects = [];

    /**
     * @param list<\Pimcore\Model\DataObject\Concrete> $scopeObjects
     */
    public function setFakeScopeObjects(array $scopeObjects): void
    {
        $this->fakeScopeObjects = $scopeObjects;
    }

    public function setFakeRules(?Fieldcollection $rules): void
    {
        $this->fakeRules = $rules;
    }

    public function getRules(): ?Fieldcollection
    {
        return $this->fakeRules;
    }

    /**
     * @return list<\Pimcore\Model\DataObject\Concrete>
     */
    public function getScopeObjects(): array
    {
        return $this->fakeScopeObjects;
    }
}
