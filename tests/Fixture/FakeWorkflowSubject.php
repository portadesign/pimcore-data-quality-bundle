<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

final class FakeWorkflowSubject
{
    public function __construct(
        private ?string $workflowState,
    ) {
    }

    public function getWorkflowState(): ?string
    {
        return $this->workflowState;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setWorkflowState(?string $workflowState, array $context = []): void
    {
        $this->workflowState = $workflowState;
    }
}
