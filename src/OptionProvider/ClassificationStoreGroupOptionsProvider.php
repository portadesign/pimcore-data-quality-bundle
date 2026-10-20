<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\OptionProvider;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig;
use Pimcore\Model\DataObject\Classificationstore\GroupConfig\Listing as GroupConfigListing;

/**
 * Options for DataQualityConfiguration.classificationStoreGroups: every group of the configured
 * Classification Store, fetched live so a newly added group shows up without a class change.
 */
final class ClassificationStoreGroupOptionsProvider implements SelectOptionsProviderInterface
{
    public function __construct(
        private readonly int $classificationStoreId,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array{key: string, value: string}>
     */
    public function getOptions(array $context, Data $fieldDefinition): array
    {
        $listing = new GroupConfigListing();
        $listing->setCondition('storeId = ?', [$this->classificationStoreId]);
        $listing->setOrderKey('name');
        $listing->setOrder('ASC');

        return \array_values(\array_map(
            static fn (GroupConfig $group): array => ['key' => (string) $group->getName(), 'value' => (string) $group->getId()],
            $listing->getList(),
        ));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasStaticOptions(array $context, Data $fieldDefinition): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function getDefaultValue(array $context, Data $fieldDefinition): string|array|null
    {
        return null;
    }
}
