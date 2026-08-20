<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\OptionProvider;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;

/**
 * Options for DataQualityRule.targetKey: the union of the owning DataQualityConfiguration's
 * targetClass's core fields and the Classification Store's active keys. There is no more stored
 * `targetType` to scope by — RuleChecker::supports() now infers coreField-vs-CS-key itself from
 * targetKey (membership test), so this provider always offers both groups. Each option's label
 * (not its value) is prefixed with its kind — "Field: " for core fields, "CS Key: " for
 * Classification Store keys — so the two groups stay visually distinguishable in one dropdown.
 */
final class TargetKeyOptionsProvider implements SelectOptionsProviderInterface
{
    public function __construct(
        private readonly ClassificationStoreKeyResolverInterface $keyResolver,
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
        $configuration = $this->resolveConfiguration($context);

        if (! $configuration instanceof DataQualityConfiguration) {
            return [];
        }

        return [
            ...$this->coreFieldOptions($configuration->getTargetClass()),
            ...$this->classificationStoreKeyOptions(),
        ];
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

    /**
     * @param array<string, mixed> $context
     */
    private function resolveConfiguration(array $context): ?DataQualityConfiguration
    {
        $object = $context['object'] ?? null;

        if ($object instanceof DataQualityConfiguration) {
            return $object;
        }

        if ($object instanceof AbstractData) {
            $owner = $object->getObject();

            return $owner instanceof DataQualityConfiguration ? $owner : null;
        }

        return null;
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function coreFieldOptions(?string $targetClassName): array
    {
        if ($targetClassName === null || $targetClassName === '') {
            return [];
        }

        $class = ClassDefinition::getByName($targetClassName);

        if (! $class instanceof ClassDefinition) {
            return [];
        }

        $options = [];

        foreach ($class->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition->isRelationType()) {
                continue;
            }

            $options[] = ['key' => 'Field: ' . ($fieldDefinition->getTitle() ?: $fieldDefinition->getName()), 'value' => $fieldDefinition->getName()];
        }

        return $options;
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function classificationStoreKeyOptions(): array
    {
        return \array_map(
            static fn (array $key): array => ['key' => 'CS Key: ' . ($key['title'] ?: $key['code']), 'value' => $key['code']],
            $this->keyResolver->listActiveKeys($this->classificationStoreId),
        );
    }
}
