<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\EventSubscriber\QualityGateGuardSubscriber;
use Portadesign\DataQualityBundle\Service\QualityGateEvaluator;
use Portadesign\DataQualityBundle\Tests\Fixture\CreatesQualityGateEvaluator;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeProduct;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityGate;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Psr\Log\NullLogger;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

final class QualityGateGuardSubscriberTest extends TestCase
{
    use CreatesQualityGateEvaluator;

    public function testSubscribesToGenericGuardEvent(): void
    {
        self::assertSame(['workflow.guard' => 'onGuard'], QualityGateGuardSubscriber::getSubscribedEvents());
    }

    public function testBlocksWithFailedChecksWhenGateFails(): void
    {
        $event = $this->guardEvent('publish');

        $this->subscriber($this->createGateEvaluator(
            [new FakeQualityGate('product:publish')],
            [new FakeQualityRule(id: 1, description: 'Brand', targetKey: null)],
            ['1' => false],
        ))->onGuard($event);

        self::assertTrue($event->isBlocked());
        $blocker = $this->firstBlocker($event);
        self::assertSame(QualityGateGuardSubscriber::BLOCKER_CODE, $blocker->getCode());
        self::assertSame('Missing: Brand.', $blocker->getMessage());
        self::assertSame('1', $blocker->getParameters()['failedChecks'][0]['ruleId']);
    }

    public function testAllowsWhenGatePasses(): void
    {
        $event = $this->guardEvent('publish');

        $this->subscriber($this->createGateEvaluator(
            [new FakeQualityGate('product:publish')],
            [new FakeQualityRule(id: 1, targetKey: null)],
            ['1' => true],
        ))->onGuard($event);

        self::assertFalse($event->isBlocked());
    }

    public function testAllowsTransitionWithoutGate(): void
    {
        $event = $this->guardEvent('revertToDraft');

        $this->subscriber($this->createGateEvaluator([new FakeQualityGate('product:publish')], [], []))->onGuard($event);

        self::assertFalse($event->isBlocked());
    }

    public function testBlocksWhenGateCannotBeEvaluated(): void
    {
        $event = $this->guardEvent('publish');

        $this->subscriber($this->createGateEvaluator(
            [new FakeQualityGate('product:publish')],
            [new FakeQualityRule(id: 1, targetKey: null)],
            [],
            checkerSupports: false,
        ))->onGuard($event);

        self::assertTrue($event->isBlocked());
        self::assertSame(QualityGateGuardSubscriber::BLOCKER_CODE, $this->firstBlocker($event)->getCode());
    }

    private function subscriber(QualityGateEvaluator $evaluator): QualityGateGuardSubscriber
    {
        return new QualityGateGuardSubscriber($evaluator, new NullLogger());
    }

    private function guardEvent(string $transition): GuardEvent
    {
        $workflow = $this->createStub(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('product');

        return new GuardEvent(new FakeProduct(), new Marking(['ready' => 1]), new Transition($transition, 'ready', 'live'), $workflow);
    }

    private function firstBlocker(GuardEvent $event): TransitionBlocker
    {
        foreach ($event->getTransitionBlockerList() as $blocker) {
            return $blocker;
        }

        self::fail('No transition blocker was added.');
    }
}
