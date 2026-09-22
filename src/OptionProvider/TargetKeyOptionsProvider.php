<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\OptionProvider;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData;
use Pimcore\Security\User\TokenStorageUserResolver;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly TranslatorInterface $translator,
        private readonly TokenStorageUserResolver $userResolver,
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

        $locale = $this->userResolver->getUser()?->getLanguage();

        return [
            ...$this->coreFieldOptions($configuration->getTargetClass(), $locale),
            ...$this->classificationStoreKeyOptions($locale),
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
    private function coreFieldOptions(?string $targetClassName, ?string $locale): array
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

            $name = (string) $fieldDefinition->getName();
            $options[] = [
                'key' => $this->prefixedLabel('portadesign_data_quality.option.field', $fieldDefinition->getTitle() ?: $name, $locale),
                'value' => $name,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function classificationStoreKeyOptions(?string $locale): array
    {
        return \array_map(
            fn (array $key): array => [
                'key' => $this->prefixedLabel('portadesign_data_quality.option.cs_key', $key['title'] ?: $key['code'], $locale),
                'value' => $key['code'],
            ],
            $this->keyResolver->listActiveKeys($this->classificationStoreId),
        );
    }

    private function prefixedLabel(string $key, string $label, ?string $locale): string
    {
        return $this->translator->trans($key, ['%label%' => $label], 'studio', $locale);
    }
}
