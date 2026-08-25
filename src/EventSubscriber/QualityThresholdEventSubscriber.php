<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\EventSubscriber;

use Portadesign\DataQualityBundle\Contract\QualityObserverInterface;
use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class QualityThresholdEventSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<QualityObserverInterface> $observers
     */
    public function __construct(
        #[TaggedIterator('quality.observer')]
        private readonly iterable $observers,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            QualityThresholdCrossedEvent::class => 'onThresholdCrossed',
        ];
    }

    public function onThresholdCrossed(QualityThresholdCrossedEvent $event): void
    {
        foreach ($this->observers as $observer) {
            try {
                match ($event->getDirection()) {
                    'reached' => $observer->onThresholdReached($event),
                    'lost' => $observer->onThresholdLost($event),
                    default => null,
                };
            } catch (\Throwable $exception) {
                // One broken observer must never stop the others or bubble up into the
                // product save path.
                $this->logger->error('Quality observer {observer} failed while handling a threshold-{direction} event: {message}', [
                    'observer' => $observer::class,
                    'direction' => $event->getDirection(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }
    }
}
