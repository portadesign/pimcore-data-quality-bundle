<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\OptionProvider;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;

/**
 * Options for DataQualityConfiguration.targetClass: every installed Pimcore DataObject class name,
 * so an editor picks a real class instead of free-typing one that silently never matches anything.
 */
final class PimcoreClassNameOptionsProvider implements SelectOptionsProviderInterface
{
    /**
     * Guards against re-entrancy: building the class Listing below fully enriches every
     * ClassDefinition it returns, including DataQualityConfiguration itself. That enrichment
     * re-evaluates targetClass's own optionsProviderClass (this class), which would call
     * getOptions() again -> list classes again -> enrich DataQualityConfiguration again, ad
     * infinitum. While a call is in flight, any re-entrant call gets an empty fallback instead
     * of recursing.
     */
    private static bool $resolving = false;

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array{key: string, value: string}>
     */
    public function getOptions(array $context, Data $fieldDefinition): array
    {
        if (self::$resolving) {
            return [];
        }

        self::$resolving = true;

        try {
            $listing = new ClassDefinition\Listing();
            $listing->setOrderKey('name');
            $listing->setOrder('ASC');

            return \array_map(
                static fn (ClassDefinition $classDefinition): array => [
                    'key' => $classDefinition->getName(),
                    'value' => $classDefinition->getName(),
                ],
                $listing->getClasses(),
            );
        } finally {
            self::$resolving = false;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasStaticOptions(array $context, Data $fieldDefinition): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function getDefaultValue(array $context, Data $fieldDefinition): string|array|null
    {
        return null;
    }
}
