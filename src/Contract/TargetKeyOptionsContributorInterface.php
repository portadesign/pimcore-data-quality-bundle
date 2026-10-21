<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

/**
 * Lets the host application offer extra rule targets (beyond class fields and Classification
 * Store keys) in the DataQualityRule "Target Key" select, for targets its own checkers support.
 * Autoconfigured services implementing it are tagged `quality.target_key_options` (see
 * PortadesignDataQualityExtension).
 */
interface TargetKeyOptionsContributorInterface
{
    /**
     * @return list<array{key: string, value: string}>
     */
    public function getTargetKeyOptions(string $targetClassName, ?string $locale): array;
}
