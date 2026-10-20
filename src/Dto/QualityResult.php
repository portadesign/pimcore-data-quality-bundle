<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Dto;

final class QualityResult
{
    /**
     * @param list<QualityCheck> $checks
     * @param bool               $configured False when no mandatory weight applied: the 100% score and
     *                                       mandatoryComplete are then vacuous, not a readiness result.
     */
    public function __construct(
        public readonly float $score,
        public readonly bool $mandatoryComplete,
        public readonly array $checks,
        public readonly bool $configured = true,
    ) {
    }

    /**
     * @return array{score: float, mandatoryComplete: bool, configured: bool, checks: list<array{ruleId: string, ruleName: string, satisfied: bool, level: string, weight: float, targetKey: ?string, label: string}>}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'mandatoryComplete' => $this->mandatoryComplete,
            'configured' => $this->configured,
            'checks' => \array_map(static fn (QualityCheck $check): array => $check->toArray(), $this->checks),
        ];
    }
}
