<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

interface QualityScoreStateRepositoryInterface
{
    /**
     * @return array{mandatory_complete: bool, score: float}|null
     */
    public function getPreviousState(int $objectId, string $scopeType, int $scopeId): ?array;

    public function upsertState(int $objectId, string $scopeType, int $scopeId, bool $mandatoryComplete, float $score): void;
}
