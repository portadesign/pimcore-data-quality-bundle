<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\EventSubscriber;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Dto\QualityCheck;
use Portadesign\DataQualityBundle\Service\QualityGateEvaluator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

final class QualityGateGuardSubscriber implements EventSubscriberInterface
{
    public const string BLOCKER_CODE = 'dq_gate';

    public function __construct(
        private readonly QualityGateEvaluator $gateEvaluator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return ['workflow.guard' => 'onGuard'];
    }

    public function onGuard(GuardEvent $event): void
    {
        $subject = $event->getSubject();

        if (! $subject instanceof Concrete) {
            return;
        }

        try {
            $result = $this->gateEvaluator->evaluate($subject, $event->getWorkflowName(), $event->getTransition()->getName());
        } catch (\Throwable $exception) {
            $this->logger->error('QualityGateGuardSubscriber: failed evaluating gate {workflow}:{transition} for object {objectId}: {message}', [
                'workflow' => $event->getWorkflowName(),
                'transition' => $event->getTransition()->getName(),
                'objectId' => $subject->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            $event->addTransitionBlocker(new TransitionBlocker('Quality gate could not be evaluated.', self::BLOCKER_CODE, ['failedChecks' => []]));

            return;
        }

        if ($result === null || $result->passed()) {
            return;
        }

        $failedChecks = $result->failedChecks();

        $event->addTransitionBlocker(new TransitionBlocker(
            \sprintf('Missing: %s.', \implode(', ', \array_map(
                static fn (QualityCheck $check): string => $check->label !== '' ? $check->label : $check->ruleName,
                $failedChecks,
            ))),
            self::BLOCKER_CODE,
            ['failedChecks' => \array_map(static fn (QualityCheck $check): array => $check->toArray(), $failedChecks)],
        ));
    }
}
