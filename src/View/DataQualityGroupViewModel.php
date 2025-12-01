<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\View;

class DataQualityGroupViewModel
{
    /** @var DataQualityFieldViewModel[] */
    public function __construct(
        private string $name,
        private array $fields,
    ) {}

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return DataQualityFieldViewModel[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
