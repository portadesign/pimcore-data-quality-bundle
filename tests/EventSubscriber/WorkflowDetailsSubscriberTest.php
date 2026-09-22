<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Pimcore\Workflow\Manager;
use Portadesign\DataQualityBundle\EventSubscriber\WorkflowDetailsSubscriber;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeWorkflowSubject;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;

final class WorkflowDetailsSubscriberTest extends TestCase
{
    public function testListsOnlyBlockedTransitionsLeavingCurrentPlace(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.product.guard.markReady', static function (GuardEvent $event): void {
            $event->addTransitionBlocker(new TransitionBlocker('Missing: Brand.', 'dq_gate', ['failedChecks' => []]));
        });
        $dispatcher->addListener('workflow.product.guard.proposeChange', static function (GuardEvent $event): void {
            $event->setBlocked(true, 'Proposing a change requires the Sales Manager role.');
        });

        $workflow = new StateMachine(
            new Definition(
                ['draft', 'enriched', 'ready', 'changeRequested'],
                [
                    new Transition('startEnrichment', 'draft', 'enriched'),
                    new Transition('markReady', 'enriched', 'ready'),
                    new Transition('revertToDraft', 'enriched', 'draft'),
                    new Transition('revertToDraft', 'ready', 'draft'),
                    new Transition('proposeChange', 'enriched', 'changeRequested'),
                ],
            ),
            new MethodMarkingStore(true, 'workflowState'),
            $dispatcher,
            'product',
        );

        $blocked = $this->subscriber()->collectBlockedTransitions($workflow, new FakeWorkflowSubject('enriched'));

        self::assertSame(['markReady', 'proposeChange'], \array_column($blocked, 'name'));
        self::assertSame('dq_gate', $blocked[0]['blockers'][0]['code']);
        self::assertSame('Missing: Brand.', $blocked[0]['blockers'][0]['message']);
        self::assertSame('Proposing a change requires the Sales Manager role.', $blocked[1]['blockers'][0]['message']);
    }

    private function subscriber(): WorkflowDetailsSubscriber
    {
        return new WorkflowDetailsSubscriber($this->createStub(Manager::class), new RequestStack(), new NullLogger());
    }
}
