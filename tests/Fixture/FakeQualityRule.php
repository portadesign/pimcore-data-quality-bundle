<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

final class FakeQualityRule implements QualityConfigurationInterface
{
    /**
     * $id accepts int|string (test call sites overwhelmingly pass plain int literals like
     * `id: 1`) and is normalized to string in getId(), matching
     * QualityConfigurationInterface::getId()'s ?string return type.
     *
     * @param list<Concrete> $dependentObjects
     */
    public function __construct(
        private readonly int|string|null $id = 1,
        private readonly ?string $description = 'Fake rule',
        private readonly array $dependentObjects = [],
        private readonly ?string $targetType = 'coreField',
        private readonly ?string $targetKey = 'ean',
        private readonly ?string $requirementLevel = 'mandatory',
        private readonly ?float $weight = 1.0,
        private readonly ?string $message = null,
        private readonly ?string $ruleType = 'presence',
        private readonly ?bool $active = true,
    ) {
    }

    public function getId(): ?string
    {
        return $this->id !== null ? (string) $this->id : null;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDependentObjects(): array
    {
        return $this->dependentObjects;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getRuleType(): ?string
    {
        return $this->ruleType;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }
}
