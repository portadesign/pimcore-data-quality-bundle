<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Dto;

final class QualityResult
{
    /**
     * @param list<QualityCheck> $checks
     */
    public function __construct(
        public readonly float $score,
        public readonly bool $mandatoryComplete,
        public readonly array $checks,
    ) {
    }

    /**
     * @return array{score: float, mandatoryComplete: bool, checks: list<array{ruleId: string, ruleName: string, satisfied: bool, level: string, weight: float, targetKey: ?string, label: string}>}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'mandatoryComplete' => $this->mandatoryComplete,
            'checks' => \array_map(static fn (QualityCheck $check): array => $check->toArray(), $this->checks),
        ];
    }
}
