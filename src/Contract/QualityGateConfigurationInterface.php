<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

interface QualityGateConfigurationInterface
{
    /**
     * "<workflow>:<transition>", e.g. "product:publish".
     */
    public function getTransition(): ?string;

    /**
     * One of mandatory|mandatory_recommended.
     */
    public function getRequiredLevel(): ?string;

    public function getActive(): ?bool;
}
