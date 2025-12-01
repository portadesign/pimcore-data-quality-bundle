<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\View;

class DataQualityFieldViewModel
{
    public function __construct(
        private string $name,
        private int $weight,
        private bool $valid,
        private ?string $language = null,
        private ?array $data = null,
    ) {}

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }
}
