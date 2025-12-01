<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\View;

class DataQualityViewModel
{
    /**
     * @param DataQualityGroupViewModel[] $groups
     */
    public function __construct(
        private string $title,
        private int $percentage,
        private array $groups
    ) {}

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return int
     */
    public function getPercentage(): int
    {
        return $this->percentage;
    }

    /**
     * @return DataQualityGroupViewModel[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
