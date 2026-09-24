<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\RuleChecker;

use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;

trait PresenceCheckTrait
{
    /**
     * 0, 0.0 and false are still "present data" — a bare falsy check would wrongly treat a
     * legitimate zero value or an explicit "no" as missing.
     */
    private function isPresent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value === '') {
            return false;
        }

        if ($value === []) {
            return false;
        }

        // An image field/gallery getter returns a wrapper object even when no image is set, so
        // the wrapper itself says nothing about presence — its image(s) do.
        if ($value instanceof Hotspotimage) {
            return $value->getImage() !== null;
        }

        if ($value instanceof ImageGallery) {
            foreach ($value->getItems() as $item) {
                if ($item instanceof Hotspotimage && $item->getImage() !== null) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
