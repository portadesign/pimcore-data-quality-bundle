<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\EventSubscriber;

use Basilicom\DataQualityBundle\Service\DataQualityService;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Tool\Admin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DataQualityPostUpdateSubscriber implements EventSubscriberInterface
{
    private static bool $subscriberEnabled = true;

    public function __construct(
        private DataQualityService $dataQualityService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectEvents::POST_UPDATE => 'onPostUpdate',
        ];
    }

    public function onPostUpdate(DataObjectEvent $event)
    {
        if ($this->areEventConditionsNotSatisfied($event)) {
            return;
        }

        $dataObject = $event->getObject();

        if (!$dataObject instanceof Concrete) {
            return;
        }

        $dataQualityConfigs = $this->dataQualityService->getDataQualityConfigs($dataObject);

        if (empty($dataQualityConfigs)) {
            return; // no data quality configurations
        }

        self::$subscriberEnabled = false;

        foreach ($dataQualityConfigs as $dataQualityConfig) {
            $isSystemAllowed = (bool) $dataQualityConfig->getDataQualitySystemAllowed();

            if (!$isSystemAllowed && $this->isBackendUserActive()) {
                continue;
            }

            $this->dataQualityService->calculateDataQuality($dataObject, $dataQualityConfig);
        }

        self::$subscriberEnabled = true;
    }

    private function areEventConditionsNotSatisfied(DataObjectEvent $event): bool
    {
        return !$this->isSubscriberEnabled() || !$this->isAutoSave($event->getArguments());
    }

    private function isSubscriberEnabled(): bool
    {
        return self::$subscriberEnabled;
    }

    private function isAutoSave(array $arguments): bool
    {
        return !isset($arguments['isAutoSave']) || !$arguments['isAutoSave'];
    }

    private function isBackendUserActive(): bool
    {
        $userId = 0;
        $user = Admin::getCurrentUser();

        if ($user) {
            $userId = $user->getId();
        }

        return $userId === 0;
    }
}
