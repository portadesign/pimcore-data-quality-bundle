<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;

final class FakeObjectWithAttributes extends Concrete
{
    private ?Classificationstore $attributes = null;

    public function setAttributes(?Classificationstore $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getAttributes(): ?Classificationstore
    {
        return $this->attributes;
    }
}
