<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Portadesign\DataQualityBundle\Contract\QualityGateConfigurationInterface;

final class FakeQualityGate implements QualityGateConfigurationInterface
{
    public function __construct(
        private readonly ?string $transition,
        private readonly ?string $requiredLevel = 'mandatory',
        private readonly ?bool $active = true,
    ) {
    }

    public function getTransition(): ?string
    {
        return $this->transition;
    }

    public function getRequiredLevel(): ?string
    {
        return $this->requiredLevel;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }
}
