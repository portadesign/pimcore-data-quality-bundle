<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Event;

use Pimcore\Model\DataObject\Concrete;

final class QualityThresholdCrossedEvent
{
    public function __construct(
        public readonly Concrete $object,
        public readonly ?Concrete $channel,
        public readonly ?Concrete $category,
        public readonly string $direction,
        public readonly float $score,
    ) {
    }

    public function getObject(): Concrete
    {
        return $this->object;
    }

    public function getChannel(): ?Concrete
    {
        return $this->channel;
    }

    public function getCategory(): ?Concrete
    {
        return $this->category;
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
