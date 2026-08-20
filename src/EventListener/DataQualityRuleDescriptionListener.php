<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\EventListener;

use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Generates DataQualityRule.description (readonly in the Studio editor) from targetKey +
 * requirementLevel + weight + active. Runs on PRE_ADD/PRE_UPDATE, not POST_*, so the value lands
 * in the actual persisted row without a second ->save() call.
 */
final class DataQualityRuleDescriptionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClassificationStoreKeyResolverInterface $keyResolver,
        private readonly int $classificationStoreId,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectEvents::PRE_ADD => 'onPreSave',
            DataObjectEvents::PRE_UPDATE => 'onPreSave',
        ];
    }

    public function onPreSave(DataObjectEvent $event): void
    {
        $subject = $event->getObject();

        if (! $subject instanceof DataQualityConfiguration) {
            return;
        }

        $rules = $subject->getRules();

        if ($rules === null) {
            return;
        }

        // Fetched once per save, not once per rule - listActiveKeys() is a real DB query.
        $csKeyTitlesByCode = [];
        foreach ($this->keyResolver->listActiveKeys($this->classificationStoreId) as $key) {
            $csKeyTitlesByCode[$key['code']] = $key['title'];
        }

        $targetClass = $subject->getTargetClass();

        foreach ($rules->getItems() as $rule) {
            if (! $rule instanceof QualityConfigurationInterface || ! \method_exists($rule, 'setDescription')) {
                continue;
            }

            $rule->setDescription($this->buildDescription($rule, $targetClass, $csKeyTitlesByCode));
        }
    }

    /**
     * @param array<string, string> $csKeyTitlesByCode
     */
    private function buildDescription(QualityConfigurationInterface $rule, ?string $targetClass, array $csKeyTitlesByCode): string
    {
        $scope = $this->resolveScopeLabel($rule->getDependentObjects());
        $keyLabel = $this->resolveKeyLabel($rule->getTargetKey(), $targetClass, $csKeyTitlesByCode);
        $level = \ucfirst($rule->getRequirementLevel() ?? '?');
        $weight = $rule->getWeight();
        $weightSuffix = $weight !== null ? ' / Weight ' . (int) $weight : '';
        $inactiveSuffix = $rule->getActive() === false ? ' [inactive]' : '';

        return \sprintf('%s / %s / %s%s%s', $scope, $keyLabel, $level, $weightSuffix, $inactiveSuffix);
    }

    /**
     * @param list<Concrete> $dependentObjects
     */
    private function resolveScopeLabel(array $dependentObjects): string
    {
        $first = $dependentObjects[0] ?? null;

        if (! $first instanceof Concrete) {
            return 'Global';
        }

        $name = \method_exists($first, 'getName') ? $first->getName() : null;

        return \sprintf('%s: %s', $first->getClassName(), \is_string($name) && $name !== '' ? $name : $first->getKey());
    }

    /**
     * @param array<string, string> $csKeyTitlesByCode
     */
    private function resolveKeyLabel(?string $targetKey, ?string $targetClassName, array $csKeyTitlesByCode): string
    {
        if ($targetKey === null || $targetKey === '') {
            return '(no target key)';
        }

        if ($targetClassName !== null && $targetClassName !== '') {
            $class = ClassDefinition::getByName($targetClassName);
            $fieldDefinition = $class?->getFieldDefinition($targetKey);

            if ($fieldDefinition !== null) {
                return $fieldDefinition->getTitle() ?: $targetKey;
            }
        }

        if (isset($csKeyTitlesByCode[$targetKey])) {
            $title = $csKeyTitlesByCode[$targetKey];

            return $title !== '' && $title !== $targetKey ? \sprintf('%s (%s)', $title, $targetKey) : $targetKey;
        }

        // Localized fields (Product.name/description) are nested under "localizedfields", not
        // found by the lookup above - same gap TargetKeyOptionsProvider's dropdown has.
        return \ucfirst($targetKey);
    }
}
