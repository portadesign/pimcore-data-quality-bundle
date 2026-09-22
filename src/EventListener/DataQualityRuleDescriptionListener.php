<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\EventListener;

use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\Element\AbstractElement;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Pimcore\Security\User\TokenStorageUserResolver;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Generates DataQualityRule.description (readonly in the Studio editor) from targetKey +
 * requirementLevel + weight + active. Runs on PRE_ADD/PRE_UPDATE, not POST_*, so the value lands
 * in the actual persisted row without a second ->save() call.
 */
final class DataQualityRuleDescriptionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ClassificationStoreKeyResolverInterface $keyResolver,
        private readonly TranslatorInterface $translator,
        private readonly TokenStorageUserResolver $userResolver,
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
            $csKeyTitlesByCode[$key['code']] = $key['title'] ?? '';
        }

        $targetClass = $subject->getTargetClass();
        $locale = $this->userResolver->getUser()?->getLanguage();

        foreach ($rules->getItems() as $rule) {
            /** @phpstan-ignore instanceof.alwaysTrue, function.alreadyNarrowedType */
            if (! $rule instanceof QualityConfigurationInterface || ! \method_exists($rule, 'setDescription')) {
                continue;
            }

            $rule->setDescription($this->buildDescription($rule, $targetClass, $csKeyTitlesByCode, $locale));
        }
    }

    /**
     * @param array<string, string> $csKeyTitlesByCode
     */
    private function buildDescription(QualityConfigurationInterface $rule, ?string $targetClass, array $csKeyTitlesByCode, ?string $locale): string
    {
        $scope = $this->resolveScopeLabel($rule->getDependentObjects(), $locale);
        $keyLabel = $this->resolveKeyLabel($rule->getTargetKey(), $targetClass, $csKeyTitlesByCode, $locale);
        $requirementLevel = $rule->getRequirementLevel();
        $level = $requirementLevel !== null && $requirementLevel !== ''
            ? $this->trans('portadesign_data_quality.level.' . $requirementLevel, $locale)
            : '?';
        $weight = $rule->getWeight();
        $weightSuffix = $weight !== null
            ? ' / ' . $this->trans('portadesign_data_quality.description.weight', $locale, ['%weight%' => (int) $weight])
            : '';
        $inactiveSuffix = $rule->getActive() === false
            ? ' ' . $this->trans('portadesign_data_quality.description.inactive', $locale)
            : '';

        return \sprintf('%s / %s / %s%s%s', $scope, $keyLabel, $level, $weightSuffix, $inactiveSuffix);
    }

    /**
     * @param list<AbstractElement> $dependentObjects
     */
    private function resolveScopeLabel(array $dependentObjects, ?string $locale): string
    {
        $labels = [];

        foreach ($dependentObjects as $dependentObject) {
            if (! $dependentObject instanceof Concrete) {
                continue;
            }

            $labels[] = $this->resolveDependentObjectLabel($dependentObject);
        }

        return $labels === [] ? $this->trans('portadesign_data_quality.description.global', $locale) : \implode(' + ', $labels);
    }

    private function resolveDependentObjectLabel(Concrete $dependentObject): string
    {
        $name = \method_exists($dependentObject, 'getName') ? $dependentObject->getName() : null;

        return \sprintf(
            '%s: %s',
            $dependentObject->getClassName(),
            \is_string($name) && $name !== '' ? $name : $dependentObject->getKey(),
        );
    }

    /**
     * @param array<string, string> $csKeyTitlesByCode
     */
    private function resolveKeyLabel(?string $targetKey, ?string $targetClassName, array $csKeyTitlesByCode, ?string $locale): string
    {
        if ($targetKey === null || $targetKey === '') {
            return $this->trans('portadesign_data_quality.description.no_target_key', $locale);
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

    /**
     * @param array<string, string|int> $parameters
     */
    private function trans(string $key, ?string $locale, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters, 'studio', $locale);
    }
}
