<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\QualityObserverInterface;
use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;
use Portadesign\DataQualityBundle\EventSubscriber\QualityThresholdEventSubscriber;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Psr\Log\LoggerInterface;

final class QualityThresholdEventSubscriberTest extends TestCase
{
    public function testAllObserversAreInvokedOnThresholdReached(): void
    {
        $first = $this->createMock(QualityObserverInterface::class);
        $first->expects(self::once())->method('onThresholdReached');
        $first->expects(self::never())->method('onThresholdLost');

        $second = $this->createMock(QualityObserverInterface::class);
        $second->expects(self::once())->method('onThresholdReached');
        $second->expects(self::never())->method('onThresholdLost');

        $logger = $this->createStub(LoggerInterface::class);

        $subscriber = new QualityThresholdEventSubscriber([$first, $second], $logger);
        $subscriber->onThresholdCrossed($this->makeEvent('reached'));
    }

    public function testAllObserversAreInvokedOnThresholdLost(): void
    {
        $first = $this->createMock(QualityObserverInterface::class);
        $first->expects(self::once())->method('onThresholdLost');
        $first->expects(self::never())->method('onThresholdReached');

        $logger = $this->createStub(LoggerInterface::class);

        $subscriber = new QualityThresholdEventSubscriber([$first], $logger);
        $subscriber->onThresholdCrossed($this->makeEvent('lost'));
    }

    public function testOneObserverThrowingDoesNotPreventOthersFromBeingCalledAndIsLogged(): void
    {
        $broken = $this->createMock(QualityObserverInterface::class);
        $broken->expects(self::once())->method('onThresholdReached')->willThrowException(new \RuntimeException('boom'));

        $healthy = $this->createMock(QualityObserverInterface::class);
        $healthy->expects(self::once())->method('onThresholdReached');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $subscriber = new QualityThresholdEventSubscriber([$broken, $healthy], $logger);

        // Must not bubble up into the product save path that dispatched the event.
        $subscriber->onThresholdCrossed($this->makeEvent('reached'));
    }

    private function makeEvent(string $direction): QualityThresholdCrossedEvent
    {
        $product = new FakeCoreFieldObject();

        return new QualityThresholdCrossedEvent(
            object: $product,
            channel: null,
            category: null,
            direction: $direction,
            score: 100.0,
        );
    }
}
