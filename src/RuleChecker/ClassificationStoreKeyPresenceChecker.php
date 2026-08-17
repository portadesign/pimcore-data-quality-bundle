<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\RuleChecker;

use Pimcore\Model\DataObject\Classificationstore;
use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('quality.rule_checker')]
final class ClassificationStoreKeyPresenceChecker implements RuleCheckerInterface
{
    use PresenceCheckTrait;

    public function __construct(
        private readonly int $classificationStoreId,
        private readonly ClassificationStoreKeyResolverInterface $keyResolver,
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
                (string) $rule->getName(),
            ));
        }

        $keyId = $this->keyResolver->resolveKeyId($targetKey, $this->classificationStoreId);

        if ($keyId === null) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" references Classification Store key "%s", which does not exist in store %d.',
                (string) $rule->getName(),
                $targetKey,
                $this->classificationStoreId,
            ));
        }

        if (! \method_exists($object, 'getAttributes')) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is a classificationStoreKey rule but %s has no "attributes" Classification Store field.',
                (string) $rule->getName(),
                $object::class,
            ));
        }

        $store = $object->getAttributes();

        if (! $store instanceof Classificationstore) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is a classificationStoreKey rule but %s::getAttributes() did not return a Classificationstore.',
                (string) $rule->getName(),
                $object::class,
            ));
        }

        // The key code alone doesn't encode which group it lives in within the object's
        // active groups — try every active group and take the first non-empty value.
        foreach (\array_keys($store->getActiveGroups()) as $groupId) {
            $value = $store->getLocalizedKeyValue((int) $groupId, $keyId);

            if ($this->isPresent($value)) {
                return true;
            }
        }

        return false;
    }
}
