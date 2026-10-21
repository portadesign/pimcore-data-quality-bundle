<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

use Pimcore\Model\DataObject\Concrete;

/**
 * A checker that can say why a check failed (e.g. "1,245 / 1,000 characters in cs"), shown next
 * to the check in the report. Called only after check() returned false.
 */
interface ExplainingRuleCheckerInterface extends RuleCheckerInterface
{
    public function explain(Concrete $object, QualityConfigurationInterface $rule): ?string;
}
