<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Service;

use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
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
        private readonly ClassificationStoreKeyResolverInterface $classificationStoreKeyResolver,
        private readonly int $classificationStoreId,
    ) {
    }

    /**
     * Single-scope convenience, called by QualityGateEvaluator; see evaluateForScope() for the multi-scope entry point.
     *
     * @param list<Concrete>                           $scopeObjects Rules with a non-empty `dependentObjects` apply only if all of them are in this list.
     * @param list<QualityConfigurationInterface>|null $activeRules  Pre-fetched via QualityConfigurationResolver::loadActiveRules(); null resolves/queries internally.
     */
    public function evaluate(Concrete $object, array $scopeObjects = [], ?array $activeRules = null): QualityResult
    {
        $rules = $activeRules !== null
            ? $this->resolver->filter($activeRules, $scopeObjects)
            : $this->resolver->resolve($scopeObjects);

        return $this->buildResult($object, $rules);
    }

    /**
     * Shared entry point for ProductQualityPostUpdateListener and QualityReportController: filters
     * $activeRules to what's applicable across $allScopeObjects, then narrows to what counts
     * toward $scopeObject specifically (see filterForScope()). $scopeObject = null evaluates the
     * unscoped "overall" score.
     *
     * @param list<Concrete>                      $allScopeObjects Every scope axis object $object is linked to.
     * @param list<QualityConfigurationInterface> $activeRules     Pre-fetched via QualityConfigurationResolver::loadActiveRules().
     */
    public function evaluateForScope(Concrete $object, array $allScopeObjects, ?Concrete $scopeObject, array $activeRules): QualityResult
    {
        $applicableRules = $this->resolver->filter($activeRules, $allScopeObjects);
        $rules = $this->filterForScope($applicableRules, $scopeObject);

        return $this->buildResult($object, $rules);
    }

    /**
     * A rule counts toward $scopeObject when it's among its dependentObjects, or when the rule has
     * no dependent object of $scopeObject's class at all. $scopeObject = null keeps only rules
     * with no dependent objects.
     *
     * @param list<QualityConfigurationInterface> $rules
     *
     * @return list<QualityConfigurationInterface>
     */
    private function filterForScope(array $rules, ?Concrete $scopeObject): array
    {
        $filtered = [];

        foreach ($rules as $rule) {
            $dependentObjects = $rule->getDependentObjects();

            if ($scopeObject === null) {
                if ($dependentObjects === []) {
                    $filtered[] = $rule;
                }

                continue;
            }

            $matchesScope = false;
            $hasSameClassDependent = false;

            foreach ($dependentObjects as $dependentObject) {
                if (! $dependentObject instanceof Concrete || $dependentObject->getClassName() !== $scopeObject->getClassName()) {
                    continue;
                }

                $hasSameClassDependent = true;

                if ($dependentObject->getId() === $scopeObject->getId()) {
                    $matchesScope = true;
                }
            }

            if ($matchesScope || ! $hasSameClassDependent) {
                $filtered[] = $rule;
            }
        }

        return $filtered;
    }

    /**
     * @param list<QualityConfigurationInterface> $rules
     */
    private function buildResult(Concrete $object, array $rules): QualityResult
    {
        $checks = [];
        $mandatorySatisfiedWeight = 0.0;
        $mandatoryTotalWeight = 0.0;
        $classificationStoreKeyTitles = $this->loadClassificationStoreKeyTitles();

        foreach ($rules as $rule) {
            $checker = $this->findChecker($rule, $object);
            $satisfied = $checker->check($object, $rule);

            $level = (string) $rule->getRequirementLevel();
            $weight = (float) $rule->getWeight();
            $ruleName = (string) $rule->getDescription();

            $checks[] = new QualityCheck(
                // getId() already returns ?string (a synthetic "objectId:index" composite for
                // field-collection-backed rules) — cast only guards against the null case.
                ruleId: (string) $rule->getId(),
                ruleName: $ruleName,
                satisfied: $satisfied,
                level: $level,
                weight: $weight,
                targetKey: $rule->getTargetKey(),
                label: $this->resolveLabel($rule->getTargetKey(), $ruleName, $object->getClassName(), $classificationStoreKeyTitles),
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
            checks: $checks,
        );
    }

    /**
     * Resolves a check's targetKey to the human-readable title the user actually sees in the
     * editor - the class field's title, or the Classification Store key's title - falling back to
     * the rule's description (unscoped rules) or the raw key (e.g. "WEB02") when no title is
     * configured.
     *
     * @param array<string, string> $classificationStoreKeyTitles
     */
    private function resolveLabel(?string $targetKey, string $ruleName, ?string $className, array $classificationStoreKeyTitles): string
    {
        if ($targetKey === null || $targetKey === '') {
            return $ruleName;
        }

        $fieldDefinition = $className !== null && $className !== ''
            ? ClassDefinition::getByName($className)?->getFieldDefinition($targetKey)
            : null;

        if ($fieldDefinition !== null) {
            return $fieldDefinition->getTitle() ?: $targetKey;
        }

        if (isset($classificationStoreKeyTitles[$targetKey]) && $classificationStoreKeyTitles[$targetKey] !== '') {
            return $classificationStoreKeyTitles[$targetKey];
        }

        return $targetKey;
    }

    /**
     * @return array<string, string>
     */
    private function loadClassificationStoreKeyTitles(): array
    {
        $titles = [];

        foreach ($this->classificationStoreKeyResolver->listActiveKeys($this->classificationStoreId) as $key) {
            $titles[$key['code']] = $key['title'] ?? '';
        }

        return $titles;
    }

    private function findChecker(QualityConfigurationInterface $rule, Concrete $object): RuleCheckerInterface
    {
        foreach ($this->ruleCheckers as $checker) {
            if ($checker->supports($rule, $object)) {
                return $checker;
            }
        }

        throw new RuleConfigurationException(\sprintf(
            'No rule checker supports rule "%s" (targetKey "%s") against %s.',
            (string) $rule->getDescription(),
            (string) $rule->getTargetKey(),
            $object::class,
        ));
    }

}
