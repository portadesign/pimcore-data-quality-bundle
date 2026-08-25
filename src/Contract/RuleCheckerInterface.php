<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;

interface RuleCheckerInterface
{
    /**
     * $object is the evaluated element — checkers dispatch by testing membership (does a getter
     * for targetKey exist on $object / does targetKey resolve in $object's Classification Store)
     * rather than trusting a stored rule-level "targetType", since that field was removed.
     */
    public function supports(QualityConfigurationInterface $rule, Concrete $object): bool;

    /**
     * @throws RuleConfigurationException when the rule references a field/key that cannot be
     *                                     resolved against $object
     */
    public function check(Concrete $object, QualityConfigurationInterface $rule): bool;
}
