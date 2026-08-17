<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;

/**
 * Stand-in for a generated Pimcore Data Object class: exposes plain get*() core fields, the same
 * shape FieldPresenceChecker reflects on via "get" . ucfirst($targetKey).
 */
final class FakeCoreFieldObject extends Concrete
{
    private ?string $ean = null;

    private ?int $stock = null;

    private ?bool $active = null;

    private array $tags = [];

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setStock(?int $stock): void
    {
        $this->stock = $stock;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
