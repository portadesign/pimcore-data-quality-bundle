<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Model;

final class ValidationResultBag
{
    public function __construct(
        private bool $valid,
        private array $data = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
