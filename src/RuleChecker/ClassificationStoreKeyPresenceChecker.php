<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\RuleChecker;

use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;
use Portadesign\DataQualityBundle\Contract\ValidLanguageProviderInterface;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('quality.rule_checker')]
final class ClassificationStoreKeyPresenceChecker implements RuleCheckerInterface
{
    use PresenceCheckTrait;

    public function __construct(
        private readonly int $classificationStoreId,
        private readonly ClassificationStoreKeyResolverInterface $keyResolver,
        private readonly ValidLanguageProviderInterface $languageProvider,
    ) {
    }

    public function supports(QualityConfigurationInterface $rule): bool
    {
        return $rule->getTargetType() === 'classificationStoreKey';
    }

    public function check(Concrete $object, QualityConfigurationInterface $rule): bool
    {
        $targetKey = $rule->getTargetKey();

        if ($targetKey === null || $targetKey === '') {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is of targetType "classificationStoreKey" but has no targetKey configured.',
                (string) $rule->getDescription(),
            ));
        }

        $keyId = $this->keyResolver->resolveKeyId($targetKey, $this->classificationStoreId);

        if ($keyId === null) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" references Classification Store key "%s", which does not exist in store %d.',
                (string) $rule->getDescription(),
                $targetKey,
                $this->classificationStoreId,
            ));
        }

        if (! \method_exists($object, 'getAttributes')) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is a classificationStoreKey rule but %s has no "attributes" Classification Store field.',
                (string) $rule->getDescription(),
                $object::class,
            ));
        }

        $store = $object->getAttributes();

        if (! $store instanceof Classificationstore) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is a classificationStoreKey rule but %s::getAttributes() did not return a Classificationstore.',
                (string) $rule->getDescription(),
                $object::class,
            ));
        }

        // Classification Store values are always stored per-language internally (with a
        // "default" pseudo-language for keys nobody ever translates) — Classificationstore::
        // getLocalizedKeyValue() unconditionally falls back to the "default" bucket when a
        // given language has no value of its own (see Classificationstore::getLocalizedKeyValue()
        // lines handling $ignoreDefaultLanguage). That makes iterating every configured language
        // here a no-op for keys that only ever hold "default" data (this project's current
        // state), while correctly requiring presence in every language for keys that genuinely
        // do carry distinct per-language values.
        foreach ($this->languageProvider->getValidLanguages() as $language) {
            if (! $this->isPresentInAnyActiveGroup($store, $keyId, $language)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The key code alone doesn't encode which group it lives in within the object's active
     * groups — try every active group and take the first non-empty value for $language.
     */
    private function isPresentInAnyActiveGroup(Classificationstore $store, int $keyId, string $language): bool
    {
        foreach (\array_keys($store->getActiveGroups()) as $groupId) {
            $value = $store->getLocalizedKeyValue((int) $groupId, $keyId, $language);

            if ($this->isPresent($value)) {
                return true;
            }
        }

        return false;
    }
}
