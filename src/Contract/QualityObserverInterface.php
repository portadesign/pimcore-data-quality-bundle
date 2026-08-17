<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;

interface QualityObserverInterface
{
    public function onThresholdReached(QualityThresholdCrossedEvent $event): void;

    public function onThresholdLost(QualityThresholdCrossedEvent $event): void;
}
