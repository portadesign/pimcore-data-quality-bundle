<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Dto;

use Pimcore\Model\DataObject\Concrete;

/**
 * A scoped DataQualityConfiguration: its rules apply only to objects linked to every scope object
 * (typically one Category and one Channel), and it may require extra Classification Store groups.
 */
final class QualityProfile
{
    /**
     * @param list<Concrete> $scopeObjects
     * @param list<int>      $classificationStoreGroupIds
     */
    public function __construct(
        public readonly int $configurationId,
        public readonly string $name,
        public readonly array $scopeObjects,
        public readonly array $classificationStoreGroupIds,
    ) {
    }

    /**
     * @return list<int>
     */
    public function scopeIds(): array
    {
        $ids = \array_map(static fn (Concrete $object): int => (int) $object->getId(), $this->scopeObjects);
        \sort($ids);

        return \array_values(\array_unique($ids));
    }

    /**
     * @param list<int> $objectScopeIds Ids of every scope object (channels, categories) the evaluated object is linked to.
     */
    public function appliesTo(array $objectScopeIds): bool
    {
        return \array_diff($this->scopeIds(), $objectScopeIds) === [];
    }

    /**
     * @param list<int> $scopeIds
     */
    public function hasExactScope(array $scopeIds): bool
    {
        \sort($scopeIds);

        return $this->scopeIds() === \array_values(\array_unique($scopeIds));
    }
}
