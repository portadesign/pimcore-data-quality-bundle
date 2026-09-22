<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Support;

use Pimcore\Workflow\Manager;
use Pimcore\Workflow\Transition as PimcoreTransition;
use Portadesign\DataQualityBundle\Contract\WorkflowTransitionCatalogInterface;
use Symfony\Component\Workflow\Transition;

final class PimcoreWorkflowTransitionCatalog implements WorkflowTransitionCatalogInterface
{
    public function __construct(
        private readonly Manager $workflowManager,
    ) {
    }

    public function listTransitions(?string $className): array
    {
        $transitions = [];

        foreach ($this->workflowManager->getAllWorkflows() as $workflowName) {
            if ($className !== null && ! $this->supports($workflowName, $className)) {
                continue;
            }

            $config = $this->workflowManager->getWorkflowConfig($workflowName);
            $workflow = $this->workflowManager->getWorkflowByName($workflowName);

            if ($workflow === null) {
                continue;
            }

            $seen = [];

            foreach ($workflow->getDefinition()->getTransitions() as $transition) {
                if (isset($seen[$transition->getName()])) {
                    continue;
                }

                $seen[$transition->getName()] = true;
                $transitions[] = [
                    'workflow' => $workflowName,
                    'workflowLabel' => $config->getLabel(),
                    'transition' => $transition->getName(),
                    'label' => $this->labelOf($transition),
                ];
            }
        }

        return $transitions;
    }

    public function getTransitionLabel(string $workflow, string $transition): string
    {
        try {
            $definition = $this->workflowManager->getTransitionByName($workflow, $transition);
        } catch (\LogicException) {
            return $transition;
        }

        return $definition !== null ? $this->labelOf($definition) : $transition;
    }

    private function supports(string $workflowName, string $className): bool
    {
        $subjectClass = 'Pimcore\\Model\\DataObject\\' . $className;

        if (! \class_exists($subjectClass)) {
            return false;
        }

        try {
            $subject = (new \ReflectionClass($subjectClass))->newInstanceWithoutConstructor();
        } catch (\ReflectionException) {
            return false;
        }

        return $this->workflowManager->getWorkflowIfExists($subject, $workflowName) !== null;
    }

    private function labelOf(Transition $transition): string
    {
        return $transition instanceof PimcoreTransition ? $transition->getLabel() : $transition->getName();
    }
}
