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
     * @param list<Concrete>                            $scopeObjects Objects the evaluated rule set is
     *                                                                  matched against — a rule with a
     *                                                                  non-empty `dependentObjects` list
     *                                                                  applies only if at least one of
     *                                                                  its dependent objects appears
     *                                                                  here. Pass an empty list to
     *                                                                  evaluate only unscoped rules.
     * @param list<QualityConfigurationInterface>|null  $activeRules  Pre-fetched result of
     *                                                                  QualityConfigurationResolver::loadActiveRules(), to avoid
     *                                                                  re-querying when evaluating multiple scopes for
     *                                                                  the same save. Pass null to have this call resolve/query rules
     *                                                                  itself (single-scope convenience).
     */
    public function evaluate(Concrete $object, array $scopeObjects = [], ?array $activeRules = null): QualityResult
    {
        $rules = $activeRules !== null
            ? $this->resolver->filter($activeRules, $scopeObjects)
            : $this->resolver->resolve($scopeObjects);

        $checks = [];
        $mandatorySatisfiedWeight = 0.0;
        $mandatoryTotalWeight = 0.0;

        foreach ($rules as $rule) {
            $checker = $this->findChecker($rule);
            $satisfied = $checker->check($object, $rule);

            $level = (string) $rule->getRequirementLevel();
            $weight = (float) $rule->getWeight();

            $checks[] = new QualityCheck(
                // getId() already returns ?string (a synthetic "objectId:index" composite for
                // field-collection-backed rules) — cast only guards against the null case.
                ruleId: (string) $rule->getId(),
                ruleName: (string) $rule->getDescription(),
                satisfied: $satisfied,
                level: $level,
                weight: $weight,
                message: $rule->getMessage(),
                targetKey: $rule->getTargetKey(),
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
            channelId: $this->findScopeId($scopeObjects, 'Channel'),
            categoryId: $this->findScopeId($scopeObjects, 'Category'),
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
            (string) $rule->getDescription(),
            (string) $rule->getTargetType(),
            (string) $rule->getRuleType(),
        ));
    }

    /**
     * QualityResult keeps dedicated channelId/categoryId fields for backwards-compatible report
     * shape even though $scopeObjects is now class-agnostic — derived here by matching the first
     * scope object of the given class name, since in practice a Product's own scope objects are
     * still exactly Channel/Category instances (see ProductQualityPostUpdateListener /
     * QualityReportController, both of which still gather scope per Channel/Category relation).
     *
     * @param list<Concrete> $scopeObjects
     */
    private function findScopeId(array $scopeObjects, string $className): ?int
    {
        foreach ($scopeObjects as $scopeObject) {
            if ($scopeObject->getClassName() === $className) {
                return $scopeObject->getId();
            }
        }

        return null;
    }
}
