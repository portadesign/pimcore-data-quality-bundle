<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;

interface RuleCheckerInterface
{
    public function supports(QualityConfigurationInterface $rule): bool;

    /**
     * @throws RuleConfigurationException when the rule references a field/key that cannot be
     *                                     resolved against $object
     */
    public function check(Concrete $object, QualityConfigurationInterface $rule): bool;
}
