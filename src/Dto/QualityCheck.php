<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Dto;

final class QualityCheck
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $ruleName,
        public readonly bool $satisfied,
        public readonly string $level,
        public readonly float $weight,
        public readonly ?string $message,
    ) {
    }

    /**
     * @return array{ruleId: string, ruleName: string, satisfied: bool, level: string, weight: float, message: ?string}
     */
    public function toArray(): array
    {
        return [
            'ruleId' => $this->ruleId,
            'ruleName' => $this->ruleName,
            'satisfied' => $this->satisfied,
            'level' => $this->level,
            'weight' => $this->weight,
            'message' => $this->message,
        ];
    }
}
