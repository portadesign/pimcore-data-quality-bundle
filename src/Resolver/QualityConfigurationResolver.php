<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Resolver;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Pimcore\Model\DataObject\DataQualityConfiguration\Listing as DataQualityConfigurationListing;
use Pimcore\Model\Element\AbstractElement;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

class QualityConfigurationResolver
{
    /**
     * Loads every active rule configured for the given target Pimcore DataObject class name (e.g.
     * "Product"), in a single round-trip. Rules live as DataQualityRule field-collection items
     * inside the `rules` field of the DataQualityConfiguration object(s) whose `targetClass`
     * matches. Callers evaluating multiple scope axes for the same save (e.g.
     * ProductQualityPostUpdateListener, once per channel and once per category) should call this
     * once and reuse the result via filter() instead of calling resolve() repeatedly, which would
     * re-query on every call.
     *
     * In practice there should be at most one DataQualityConfiguration object per targetClass, but
     * this defensively merges active rules across every match rather than erroring if the data
     * somehow contains more than one — e.g. during a manual data-fix window.
     *
     * @return list<QualityConfigurationInterface>
     */
    public function loadActiveRules(string $targetClassName): array
    {
        $listing = new DataQualityConfigurationListing();
        $listing->setCondition('targetClass = ?', [$targetClassName]);

        $rules = [];

        foreach ($listing->getObjects() as $configuration) {
            if (! $configuration instanceof DataQualityConfiguration) {
                continue;
            }

            foreach ($configuration->getRules() ?? [] as $rule) {
                if ($rule instanceof QualityConfigurationInterface && $rule->getActive() === true) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Filters an already-loaded rule set down to the rules applicable to the given scope: a rule
     * applies when its own `dependentObjects` list is empty (unscoped, always applies), or when at
     * least one of its dependent object ids is present among $scopeObjects' ids. $scopeObjects is
     * generic — any mix of DataObject classes, not limited to a fixed "channel"/"category" axis
     * pair.
     *
     * The active check is re-applied here (in addition to the SQL condition in loadActiveRules())
     * as defense-in-depth for callers that assemble $rules some other way, and it's what keeps this
     * filtering logic unit-testable without a live DB/listing query.
     *
     * @param list<QualityConfigurationInterface> $rules
     * @param list<Concrete>                      $scopeObjects
     *
     * @return list<QualityConfigurationInterface>
     */
    public function filter(array $rules, array $scopeObjects): array
    {
        $scopeIds = [];

        foreach ($scopeObjects as $scopeObject) {
            if ($scopeObject instanceof Concrete) {
                $scopeIds[] = $scopeObject->getId();
            }
        }

        $filtered = [];

        foreach ($rules as $rule) {
            if ($rule->getActive() !== true) {
                continue;
            }

            $dependentObjects = $rule->getDependentObjects();

            if ($dependentObjects !== []) {
                $dependentIds = \array_map(
                    static fn (AbstractElement $dependentObject): ?int => $dependentObject->getId(),
                    $dependentObjects,
                );

                if (\array_intersect($dependentIds, $scopeIds) === []) {
                    continue;
                }
            }

            $filtered[] = $rule;
        }

        return $filtered;
    }

    /**
     * Convenience resolve: loads active rules for $targetClassName then filters against
     * $scopeObjects. Prefer loadActiveRules() + filter() when evaluating multiple scopes in one
     * request, to avoid re-querying per scope.
     *
     * $targetClassName is optional only for backwards compatibility with
     * QualityEvaluationService::evaluate()'s no-pre-fetched-rules fallback path, which today has
     * no production caller (every real caller pre-fetches via loadActiveRules() and passes
     * $activeRules explicitly) — omitting it here returns an empty rule set rather than guessing a
     * class, since rules are now scoped per target class.
     *
     * @param list<Concrete> $scopeObjects
     *
     * @return list<QualityConfigurationInterface>
     */
    public function resolve(array $scopeObjects, ?string $targetClassName = null): array
    {
        if ($targetClassName === null) {
            return [];
        }

        return $this->filter($this->loadActiveRules($targetClassName), $scopeObjects);
    }
}
