<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

interface WorkflowTransitionCatalogInterface
{
    /**
     * Transitions of every workflow supporting the given DataObject class name; null lists all workflows.
     *
     * @return list<array{workflow: string, workflowLabel: string, transition: string, label: string}>
     */
    public function listTransitions(?string $className): array;

    public function getTransitionLabel(string $workflow, string $transition): string;
}
