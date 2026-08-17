<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\RuleChecker;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('quality.rule_checker')]
final class FieldPresenceChecker implements RuleCheckerInterface
{
    use PresenceCheckTrait;

    public function supports(QualityConfigurationInterface $rule): bool
    {
        return $rule->getTargetType() === 'coreField';
    }

    public function check(Concrete $object, QualityConfigurationInterface $rule): bool
    {
        $targetKey = $rule->getTargetKey();

        if ($targetKey === null || $targetKey === '') {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" is of targetType "coreField" but has no targetKey configured.',
                (string) $rule->getName(),
            ));
        }

        $getter = 'get' . \ucfirst($targetKey);

        if (! \method_exists($object, $getter)) {
            throw new RuleConfigurationException(\sprintf(
                'Rule "%s" references core field "%s" (via %s::%s()), which does not exist on %s.',
                (string) $rule->getName(),
                $targetKey,
                $object::class,
                $getter,
                $object::class,
            ));
        }

        return $this->isPresent($object->{$getter}());
    }
}
