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
 * Options for DataQualityRule.targetKey: dynamic, depends on the row's own `targetType`
 * (coreField|classificationStoreKey) and on the owning DataQualityConfiguration's `targetClass`.
 *
 * Studio re-resolves this provider's options whenever the select is opened, passing the current
 * (possibly unsaved) form state as $context['object'] — see
 * \Pimcore\Bundle\StudioBackendBundle\DataObject\Service\SelectOptionsService::getSelectOptions(),
 * which applies pending edits to the object before calling getOptions(). For a field living inside
 * a DataQualityRule field-collection row, that object is the owning DataQualityConfiguration; this
 * provider can't reliably identify *which* row is being edited from $context alone, so it inspects
 * every rule in `rules` and unions the options for whichever targetType(s) are actually set on
 * them — in practice there's normally exactly one row being edited at a time, and rows sharing the
 * same targetType get the same option list anyway. Falls back to offering both core fields and
 * Classification Store keys (rather than an empty list) if no targetClass/targetType can be
 * determined yet, so the field is never uselessly empty for an editor filling in a fresh row.
 *
 * Because of that union, an editor may see options for a targetType other than the row's own
 * (e.g. Classification Store keys listed while editing a coreField rule). This is an accepted
 * consequence of the Studio API limitation above, not a bug to "fix" by trying to scope options
 * more tightly — do not attempt that without Studio first exposing per-row context. To keep the
 * union usable, each option's label (not its value) is prefixed with its kind: "Field: " for core
 * fields, "CS Key: " for Classification Store keys.
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

        $targetTypes = $this->resolveTargetTypes($configuration);
        $options = [];

        if ($targetTypes === [] || \in_array('coreField', $targetTypes, true)) {
            $options = [...$options, ...$this->coreFieldOptions($configuration->getTargetClass())];
        }

        if ($targetTypes === [] || \in_array('classificationStoreKey', $targetTypes, true)) {
            $options = [...$options, ...$this->classificationStoreKeyOptions()];
        }

        return $options;
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
     * @return list<string>
     */
    private function resolveTargetTypes(DataQualityConfiguration $configuration): array
    {
        $rules = $configuration->getRules();

        if ($rules === null) {
            return [];
        }

        $targetTypes = [];

        foreach ($rules->getItems() as $rule) {
            if (\method_exists($rule, 'getTargetType')) {
                $targetType = $rule->getTargetType();

                if (\is_string($targetType) && $targetType !== '') {
                    $targetTypes[$targetType] = true;
                }
            }
        }

        return \array_keys($targetTypes);
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
