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
        public readonly ?int $channelId,
        public readonly ?int $categoryId,
        public readonly array $checks,
    ) {
    }

    /**
     * @return array{score: float, mandatoryComplete: bool, channelId: ?int, categoryId: ?int, checks: list<array{ruleId: string, ruleName: string, satisfied: bool, level: string, weight: float, message: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'mandatoryComplete' => $this->mandatoryComplete,
            'channelId' => $this->channelId,
            'categoryId' => $this->categoryId,
            'checks' => \array_map(static fn (QualityCheck $check): array => $check->toArray(), $this->checks),
        ];
    }
}
