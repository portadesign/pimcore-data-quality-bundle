<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Event;

use Pimcore\Model\DataObject\Concrete;

final class QualityThresholdCrossedEvent
{
    public function __construct(
        public readonly Concrete $object,
        public readonly Concrete $scopeObject,
        public readonly string $direction,
        public readonly float $score,
    ) {
    }

    public function getObject(): Concrete
    {
        return $this->object;
    }

    /**
     * The scope object (e.g. a Channel or Category, or any other DataObject) this threshold
     * crossing was evaluated against. Non-nullable: one event is dispatched per scope object, see
     * ProductQualityPostUpdateListener::evaluateScope().
     */
    public function getScopeObject(): Concrete
    {
        return $this->scopeObject;
    }

    /**
     * One of "reached"|"lost".
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}
