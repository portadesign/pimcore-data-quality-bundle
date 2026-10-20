<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Resolver;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\DataObject\DataQualityConfiguration\Listing as DataQualityConfigurationListing;
use Portadesign\DataQualityBundle\Dto\QualityProfile;

/**
 * Loads quality profiles: DataQualityConfiguration objects with a non-empty `scopeObjects`
 * relation. A profile adds requirements (and optionally Classification Store groups) on top of the
 * unscoped configuration for objects linked to all of its scope objects.
 */
class QualityProfileResolver
{
    /**
     * @return list<QualityProfile>
     */
    public function loadProfiles(string $targetClassName): array
    {
        $listing = new DataQualityConfigurationListing();
        $listing->setCondition('targetClass = ?', [$targetClassName]);

        $profiles = [];

        foreach ($listing->getObjects() as $configuration) {
            $scopeObjects = self::scopeObjectsOf($configuration);

            if ($scopeObjects === []) {
                continue;
            }

            $profiles[] = new QualityProfile(
                configurationId: (int) $configuration->getId(),
                name: (string) $configuration->getKey(),
                scopeObjects: $scopeObjects,
                classificationStoreGroupIds: self::groupIdsOf($configuration),
            );
        }

        return $profiles;
    }

    /**
     * @param list<QualityProfile> $profiles
     * @param list<Concrete>       $objectScope Every scope object (channels, categories) the evaluated object is linked to.
     *
     * @return list<QualityProfile>
     */
    public function applicableProfiles(array $profiles, array $objectScope): array
    {
        $scopeIds = \array_map(static fn (Concrete $object): int => (int) $object->getId(), $objectScope);

        return \array_values(\array_filter(
            $profiles,
            static fn (QualityProfile $profile): bool => $profile->appliesTo($scopeIds),
        ));
    }

    /**
     * Tolerates a DataQualityConfiguration class generated before the `scopeObjects` field existed.
     *
     * @return list<Concrete>
     */
    public static function scopeObjectsOf(DataQualityConfiguration $configuration): array
    {
        // @phpstan-ignore function.alreadyNarrowedType (generated class may predate the field)
        if (! \method_exists($configuration, 'getScopeObjects')) {
            return [];
        }

        return \array_values(\array_filter(
            (array) $configuration->getScopeObjects(),
            static fn (mixed $object): bool => $object instanceof Concrete,
        ));
    }

    /**
     * @return list<int>
     */
    private static function groupIdsOf(DataQualityConfiguration $configuration): array
    {
        // @phpstan-ignore function.alreadyNarrowedType (generated class may predate the field)
        if (! \method_exists($configuration, 'getClassificationStoreGroups')) {
            return [];
        }

        return \array_values(\array_map('intval', (array) $configuration->getClassificationStoreGroups()));
    }
}
