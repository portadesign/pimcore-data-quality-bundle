<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;

/**
 * Rule checker double for QualityEvaluationService tests: supports a fixed targetType and returns
 * a fixed/keyed satisfaction result, so scoring math can be asserted independently of any real
 * checker implementation.
 */
final class FakeRuleChecker implements RuleCheckerInterface
{
    /**
     * @param array<string, bool> $resultsByRuleId
     */
    public function __construct(
        private readonly string $supportedTargetType,
        private readonly array $resultsByRuleId,
    ) {
    }

    public function supports(QualityConfigurationInterface $rule): bool
    {
        return $rule->getTargetType() === $this->supportedTargetType;
    }

    public function check(Concrete $object, QualityConfigurationInterface $rule): bool
    {
        return $this->resultsByRuleId[(string) $rule->getId()] ?? false;
    }
}
