<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Resolver;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\QualityConfiguration;
use Pimcore\Model\DataObject\QualityConfiguration\Listing as QualityConfigurationListing;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

class QualityConfigurationResolver
{
    public function __construct(
        private readonly string $channelRelationFieldName,
        private readonly string $categoryRelationFieldName,
    ) {
    }

    /**
     * Queries every active QualityConfiguration rule in a single round-trip, unfiltered by
     * channel/category. Callers evaluating multiple axes for the same save (e.g.
     * ProductQualityPostUpdateListener, once per channel and once per category) should call this
     * once and reuse the result via filter() instead of calling resolve() repeatedly, which would
     * re-query the full listing on every call.
     *
     * @return list<QualityConfigurationInterface>
     */
    public function loadActiveRules(): array
    {
        $listing = new QualityConfigurationListing();
        $listing->setCondition('active = 1');

        $rules = [];

        foreach ($listing->getObjects() as $rule) {
            if ($rule instanceof QualityConfigurationInterface) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Filters an already-loaded rule set down to the rules applicable to $object for a single
     * axis: pass either $channel (with $category null) or $category (with $channel null), never
     * both non-null in one call. A rule applies when its own channel/category is unset, or matches
     * the one given.
     *
     * The active check is re-applied here (in addition to the SQL condition in loadActiveRules())
     * as defense-in-depth for callers that assemble $rules some other way, and it's what keeps this
     * filtering logic unit-testable without a live DB/listing query.
     *
     * @param list<QualityConfigurationInterface> $rules
     *
     * @return list<QualityConfigurationInterface>
     */
    public function filter(array $rules, ?Concrete $channel, ?Concrete $category): array
    {
        $filtered = [];

        foreach ($rules as $rule) {
            if ($rule->getActive() !== true) {
                continue;
            }

            $ruleChannel = $rule->getChannel();
            if ($ruleChannel !== null && (! $channel instanceof Concrete || $ruleChannel->getId() !== $channel->getId())) {
                continue;
            }

            $ruleCategory = $rule->getCategory();
            if ($ruleCategory !== null && (! $category instanceof Concrete || $ruleCategory->getId() !== $category->getId())) {
                continue;
            }

            $filtered[] = $rule;
        }

        return $filtered;
    }

    /**
     * Convenience single-axis resolve: loads active rules then filters. Prefer loadActiveRules() +
     * filter() when evaluating multiple axes in one request, to avoid re-querying per axis.
     *
     * @return list<QualityConfigurationInterface>
     */
    public function resolve(?Concrete $channel, ?Concrete $category): array
    {
        return $this->filter($this->loadActiveRules(), $channel, $category);
    }
}
