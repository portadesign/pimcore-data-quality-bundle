<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Service;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\RuleCheckerInterface;
use Portadesign\DataQualityBundle\Dto\QualityCheck;
use Portadesign\DataQualityBundle\Dto\QualityResult;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class QualityEvaluationService
{
    /**
     * @param iterable<RuleCheckerInterface> $ruleCheckers
     */
    public function __construct(
        #[TaggedIterator('quality.rule_checker')]
        private readonly iterable $ruleCheckers,
        private readonly QualityConfigurationResolver $resolver,
    ) {
    }

    /**
     * @param list<QualityConfigurationInterface>|null $activeRules Pre-fetched result of
     *                                                               QualityConfigurationResolver::loadActiveRules(), to avoid
     *                                                               re-querying when evaluating multiple channel/category axes for
     *                                                               the same save. Pass null to have this call resolve/query rules
     *                                                               itself (single-axis convenience).
     */
    public function evaluate(Concrete $object, ?Concrete $channel = null, ?Concrete $category = null, ?array $activeRules = null): QualityResult
    {
        $rules = $activeRules !== null
            ? $this->resolver->filter($activeRules, $channel, $category)
            : $this->resolver->resolve($channel, $category);

        $checks = [];
        $mandatorySatisfiedWeight = 0.0;
        $mandatoryTotalWeight = 0.0;

        foreach ($rules as $rule) {
            $checker = $this->findChecker($rule);
            $satisfied = $checker->check($object, $rule);

            $level = (string) $rule->getRequirementLevel();
            $weight = (float) $rule->getWeight();

            $checks[] = new QualityCheck(
                ruleId: (string) $rule->getId(),
                ruleName: (string) $rule->getName(),
                satisfied: $satisfied,
                level: $level,
                weight: $weight,
                message: $rule->getMessage(),
            );

            if ($level === 'mandatory') {
                $mandatoryTotalWeight += $weight;

                if ($satisfied) {
                    $mandatorySatisfiedWeight += $weight;
                }
            }
        }

        $score = $mandatoryTotalWeight > 0.0
            ? \round($mandatorySatisfiedWeight / $mandatoryTotalWeight * 100, 2)
            : 100.0;

        // Exact comparison, computed before rounding: with skewed weights the rounded percentage
        // can read 100.00 while a mandatory rule is still unmet. Both sides are sums of the same
        // unrounded `weight` values, so exact float equality is safe here.
        $mandatoryComplete = $mandatoryTotalWeight <= 0.0 || $mandatorySatisfiedWeight === $mandatoryTotalWeight;

        return new QualityResult(
            score: $score,
            mandatoryComplete: $mandatoryComplete,
            channelId: $channel?->getId(),
            categoryId: $category?->getId(),
            checks: $checks,
        );
    }

    private function findChecker(QualityConfigurationInterface $rule): RuleCheckerInterface
    {
        foreach ($this->ruleCheckers as $checker) {
            if ($checker->supports($rule)) {
                return $checker;
            }
        }

        throw new RuleConfigurationException(\sprintf(
            'No rule checker supports rule "%s" (targetType "%s", ruleType "%s").',
            (string) $rule->getName(),
            (string) $rule->getTargetType(),
            (string) $rule->getRuleType(),
        ));
    }
}
