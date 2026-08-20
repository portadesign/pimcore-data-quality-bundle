<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\RuleChecker;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;
use Portadesign\DataQualityBundle\Contract\ValidLanguageProviderInterface;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// Higher priority than ClassificationStoreKeyPresenceChecker: core-field names and Classification
// Store key codes aren't expected to collide, but this is the deterministic tiebreak if they ever do.
#[AutoconfigureTag('quality.rule_checker', ['priority' => 20])]
final class FieldPresenceChecker implements RuleCheckerInterface
{
    use PresenceCheckTrait;

    public function __construct(
        private readonly ValidLanguageProviderInterface $languageProvider,
    ) {
    }

    public function supports(QualityConfigurationInterface $rule, Concrete $object): bool
    {
        $targetKey = $rule->getTargetKey();

        return $targetKey !== null && $targetKey !== '' && \method_exists($object, 'get' . \ucfirst($targetKey));
    }

    public function check(Concrete $object, QualityConfigurationInterface $rule): bool
    {
        $targetKey = $rule->getTargetKey();

        if ($targetKey === null || $targetKey === '') {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is of targetType "coreField" but has no targetKey configured.',
                (string) $rule->getDescription(),
            ));
        }

        $getter = 'get' . \ucfirst($targetKey);

        if (! \method_exists($object, $getter)) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" references core field "%s" (via %s::%s()), which does not exist on %s.',
                (string) $rule->getDescription(),
                $targetKey,
                $object::class,
                $getter,
                $object::class,
            ));
        }

        if (! $this->isLocalizedField($object, $getter)) {
            return $this->isPresent($object->{$getter}());
        }

        // Localized field (e.g. Product.name/description live in localizedfields): a bare
        // getter() only reflects the current request/session locale, so a product's
        // completeness would silently depend on which admin locale happened to be active.
        // Require presence in every configured language instead.
        foreach ($this->languageProvider->getValidLanguages() as $language) {
            if (! $this->isPresent($object->{$getter}($language))) {
                return false;
            }
        }

        return true;
    }

    /**
     * True when $getter's return value depends on which locale is passed/active — Pimcore's
     * codegen gives every localizedfields-backed getter an optional `?string $language = null`
     * parameter, while plain core-field getters take none. Detected via reflection on the getter
     * itself rather than via getClass()->getFieldDefinition('localizedfields') (stock's approach)
     * because that needs a booted Pimcore kernel/DB — this bundle's test suite deliberately avoids
     * that (see Tests\Fixture\FakeProduct's docblock), and reflection gives the same answer for a
     * real generated Data Object class without the dependency.
     */
    private function isLocalizedField(Concrete $object, string $getter): bool
    {
        return (new \ReflectionMethod($object, $getter))->getNumberOfParameters() > 0;
    }
}
