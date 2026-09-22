<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Dto;

final class GateResult
{
    /**
     * @param list<QualityCheck> $checks Checks of the rules this gate evaluates, already narrowed to its required level.
     */
    public function __construct(
        public readonly string $workflow,
        public readonly string $transition,
        public readonly string $label,
        public readonly string $requiredLevel,
        public readonly array $checks,
    ) {
    }

    public function passed(): bool
    {
        return $this->failedChecks() === [];
    }

    /**
     * @return list<QualityCheck>
     */
    public function failedChecks(): array
    {
        return \array_values(\array_filter(
            $this->checks,
            static fn (QualityCheck $check): bool => ! $check->satisfied,
        ));
    }

    public function covers(string $ruleId): bool
    {
        foreach ($this->checks as $check) {
            if ($check->ruleId === $ruleId) {
                return true;
            }
        }

        return false;
    }

    public function blocks(string $ruleId): bool
    {
        foreach ($this->failedChecks() as $check) {
            if ($check->ruleId === $ruleId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{workflow: string, transition: string, label: string, requiredLevel: string, passed: bool, failedChecks: list<array{ruleId: string, ruleName: string, satisfied: bool, level: string, weight: float, targetKey: ?string, label: string}>}
     */
    public function toArray(): array
    {
        return [
            'workflow' => $this->workflow,
            'transition' => $this->transition,
            'label' => $this->label,
            'requiredLevel' => $this->requiredLevel,
            'passed' => $this->passed(),
            'failedChecks' => \array_map(static fn (QualityCheck $check): array => $check->toArray(), $this->failedChecks()),
        ];
    }
}
