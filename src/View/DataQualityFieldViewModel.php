<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\View;

class DataQualityFieldViewModel
{
    private string $name;
    private int $weight;
    private bool $valid;
    private ?string $language;
    private ?array $data;

    public function __construct(string $name, int $weight, bool $valid, ?string $language = null, ?array $data = null)
    {
        $this->name        = $name;
        $this->weight      = $weight;
        $this->valid       = $valid;
        $this->language    = $language;
        $this->data        = $data;
    }

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
