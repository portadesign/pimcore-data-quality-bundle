<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

final class FakeMaxLengthRule implements QualityConfigurationInterface
{
    public function __construct(
        private readonly ?string $targetKey,
        private readonly ?int $maxLength,
        private readonly string $id = 'limited',
    ) {
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return 'Limited rule';
    }

    public function getDependentObjects(): array
    {
        return [];
    }

    public function getTargetKey(): ?string
    {
        return $this->targetKey;
    }

    public function getRequirementLevel(): ?string
    {
        return 'mandatory';
    }

    public function getWeight(): ?float
    {
        return 1.0;
    }

    public function getActive(): ?bool
    {
        return true;
    }
}
