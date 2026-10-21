<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Resolver;

use Pimcore\Model\Element\AbstractElement;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Support\RuleConstraints;

/**
 * A rule of a scoped DataQualityConfiguration (a quality profile): the configuration's scope
 * objects are added to the rule's own dependent objects, so every downstream consumer (subset
 * filter, per-scope narrowing, workflow gates) sees the effective scope without knowing about
 * profiles. Everything else delegates to the stored rule, including its id.
 */
final class ScopedQualityRule implements QualityConfigurationInterface
{
    /**
     * @param list<AbstractElement> $scopeObjects
     */
    public function __construct(
        private readonly QualityConfigurationInterface $rule,
        private readonly array $scopeObjects,
    ) {
    }

    public function getId(): ?string
    {
        return $this->rule->getId();
    }

    public function getDescription(): ?string
    {
        return $this->rule->getDescription();
    }

    /**
     * @return list<AbstractElement>
     */
    public function getDependentObjects(): array
    {
        $merged = [];

        foreach ([...$this->scopeObjects, ...$this->rule->getDependentObjects()] as $object) {
            $merged[(int) $object->getId()] = $object;
        }

        return \array_values($merged);
    }

    public function getTargetKey(): ?string
    {
        return $this->rule->getTargetKey();
    }

    public function getRequirementLevel(): ?string
    {
        return $this->rule->getRequirementLevel();
    }

    public function getWeight(): ?float
    {
        return $this->rule->getWeight();
    }

    public function getActive(): ?bool
    {
        return $this->rule->getActive();
    }

    public function getMaxLength(): ?int
    {
        return RuleConstraints::maxLength($this->rule);
    }
}
