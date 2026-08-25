<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Support;

use Pimcore\Model\DataObject\Classificationstore\KeyConfig;
use Pimcore\Model\DataObject\Classificationstore\KeyConfig\Listing as KeyConfigListing;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;

final class PimcoreClassificationStoreKeyResolver implements ClassificationStoreKeyResolverInterface
{
    public function resolveKeyId(string $keyCode, int $storeId): ?int
    {
        $keyConfig = KeyConfig::getByName($keyCode, $storeId);

        return $keyConfig instanceof KeyConfig ? $keyConfig->getId() : null;
    }

    public function listActiveKeys(int $storeId): array
    {
        $listing = new KeyConfigListing();
        $listing->setCondition('storeId = ? AND enabled = 1', [$storeId]);
        $listing->setOrderKey('name');
        $listing->setOrder('ASC');

        return \array_map(
            static fn (KeyConfig $keyConfig): array => ['code' => $keyConfig->getName(), 'title' => $keyConfig->getTitle()],
            $listing->getList(),
        );
    }
}
