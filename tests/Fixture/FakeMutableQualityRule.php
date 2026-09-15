<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\Element\AbstractElement;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

/**
 * Stand-in for a generated DataQualityRule field-collection item: unlike FakeQualityRule
 * (immutable, used to feed rule checkers), this one exposes a mutable setDescription() so it can
 * round-trip through DataQualityRuleDescriptionListener::onPreSave(), the same duck-typed
 * "instanceof QualityConfigurationInterface && method_exists('setDescription')" shape the
 * listener checks for.
 */
final class FakeMutableQualityRule implements QualityConfigurationInterface
{
    private ?string $description = null;

    /**
     * @param list<AbstractElement> $dependentObjects
     */
    public function __construct(
        private readonly ?string $id = '1',
        private readonly array $dependentObjects = [],
        private readonly ?string $targetKey = null,
        private readonly ?string $requirementLevel = 'mandatory',
        private readonly ?float $weight = 1.0,
        private readonly ?bool $active = true,
    ) {
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDependentObjects(): array
    {
        return $this->dependentObjects;
    }

    public function getTargetKey(): ?string
    {
        return $this->targetKey;
    }

    public function getRequirementLevel(): ?string
    {
        return $this->requirementLevel;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }
}
