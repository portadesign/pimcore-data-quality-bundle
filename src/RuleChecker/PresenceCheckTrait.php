<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\RuleChecker;

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

        return true;
    }
}
