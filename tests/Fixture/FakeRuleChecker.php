<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;

/**
 * Rule checker double for QualityEvaluationService tests: `$supports` decides dispatch (real
 * checkers now decide via a targetKey membership test against the object; these tests only care
 * about the resulting scoring math, not the dispatch rule itself, so a plain predicate is enough),
 * and returns a fixed/keyed satisfaction result so scoring math can be asserted independently of
 * any real checker implementation.
 */
final class FakeRuleChecker implements RuleCheckerInterface
{
    /**
     * @param \Closure(QualityConfigurationInterface, Concrete): bool $supports
     * @param array<string, bool>                                    $resultsByRuleId
     */
    public function __construct(
        private readonly \Closure $supports,
        private readonly array $resultsByRuleId,
    ) {
    }

    public function supports(QualityConfigurationInterface $rule, Concrete $object): bool
    {
        return ($this->supports)($rule, $object);
    }

    public function check(Concrete $object, QualityConfigurationInterface $rule): bool
    {
        return $this->resultsByRuleId[(string) $rule->getId()] ?? false;
    }
}
