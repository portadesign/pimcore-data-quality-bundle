<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\EventSubscriber;

use Pimcore\Bundle\StudioBackendBundle\Workflow\Event\PreResponse\WorkflowDetailsEvent;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Workflow\Manager;
use Pimcore\Workflow\Transition as PimcoreTransition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class WorkflowDetailsSubscriber implements EventSubscriberInterface
{
    public const string ATTRIBUTE = 'blockedTransitions';

    private const array DATA_OBJECT_ELEMENT_TYPES = ['data-object', 'object'];

    public function __construct(
        private readonly Manager $workflowManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowDetailsEvent::EVENT_NAME => 'onWorkflowDetails'];
    }

    public function onWorkflowDetails(WorkflowDetailsEvent $event): void
    {
        $subject = $this->resolveSubject();

        if ($subject === null) {
            return;
        }

        $workflow = $this->workflowManager->getWorkflowIfExists($subject, $event->getWorkflowDetails()->getWorkflowName());

        if ($workflow === null) {
            return;
        }

        $event->addAdditionalAttribute(self::ATTRIBUTE, $this->collectBlockedTransitions($workflow, $subject));
    }

    /**
     * Transitions leaving the subject's current place that are not enabled, each with every guard's blockers.
     *
     * @return list<array{name: string, label: string, blockers: list<array{code: string, message: string, parameters: array<string, mixed>}>}>
     */
    public function collectBlockedTransitions(WorkflowInterface $workflow, object $subject): array
    {
        $places = \array_keys($workflow->getMarking($subject)->getPlaces());
        $enabled = \array_map(static fn (Transition $transition): string => $transition->getName(), $workflow->getEnabledTransitions($subject));
        $blocked = [];

        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $name = $transition->getName();

            if (isset($blocked[$name]) || \in_array($name, $enabled, true) || \array_intersect($transition->getFroms(), $places) === []) {
                continue;
            }

            $blockers = [];

            foreach ($workflow->buildTransitionBlockerList($subject, $name) as $blocker) {
                $blockers[] = [
                    'code' => $blocker->getCode(),
                    'message' => $blocker->getMessage(),
                    'parameters' => $blocker->getParameters(),
                ];
            }

            $blocked[$name] = [
                'name' => $name,
                'label' => $transition instanceof PimcoreTransition ? $transition->getLabel() : $name,
                'blockers' => $blockers,
            ];
        }

        return \array_values($blocked);
    }

    private function resolveSubject(): ?Concrete
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null || ! \in_array($request->query->get('elementType'), self::DATA_OBJECT_ELEMENT_TYPES, true)) {
            return null;
        }

        $subject = Concrete::getById($request->query->getInt('elementId'));

        return $subject instanceof Concrete ? $subject : null;
    }
}
