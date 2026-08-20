<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Observer;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('quality.observer')]
final class ChannelNoteObserver extends AbstractQualityNoteObserver
{
    private const string CLASS_NAME = 'Channel';

    protected function getScope(QualityThresholdCrossedEvent $event): ?Concrete
    {
        $scopeObject = $event->getScopeObject();

        return $scopeObject->getClassName() === self::CLASS_NAME ? $scopeObject : null;
    }

    protected function getScopeLabel(): string
    {
        return 'channel';
    }
}
