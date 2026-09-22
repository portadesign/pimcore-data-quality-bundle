<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Portadesign\DataQualityBundle\Contract\WorkflowTransitionCatalogInterface;

final class FakeWorkflowTransitionCatalog implements WorkflowTransitionCatalogInterface
{
    /**
     * @var list<?string>
     */
    public array $requestedClassNames = [];

    /**
     * @param list<array{workflow: string, workflowLabel: string, transition: string, label: string}> $transitions
     */
    public function __construct(
        private readonly array $transitions = [],
    ) {
    }

    public function listTransitions(?string $className): array
    {
        $this->requestedClassNames[] = $className;

        return $this->transitions;
    }

    public function getTransitionLabel(string $workflow, string $transition): string
    {
        foreach ($this->transitions as $candidate) {
            if ($candidate['workflow'] === $workflow && $candidate['transition'] === $transition) {
                return $candidate['label'];
            }
        }

        return \ucfirst($transition);
    }
}
