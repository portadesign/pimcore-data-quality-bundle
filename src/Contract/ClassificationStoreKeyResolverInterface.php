<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

interface ClassificationStoreKeyResolverInterface
{
    /**
     * Resolves a Classification Store key code to its numeric key id within the given store, or
     * null when the code is unknown.
     */
    public function resolveKeyId(string $keyCode, int $storeId): ?int;
}
