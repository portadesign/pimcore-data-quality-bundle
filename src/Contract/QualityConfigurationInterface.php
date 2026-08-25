<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

use Pimcore\Model\Element\AbstractElement;

interface QualityConfigurationInterface
{
    /**
     * DataQualityRule field-collection items have no DataObject-style int primary key (no
     * oo_id) — only a position (getIndex()) within the owning DataQualityConfiguration object
     * (getObject()). Implementations synthesize a composite id from those, hence string here
     * rather than int.
     */
    public function getId(): ?string;

    public function getDescription(): ?string;

    /**
     * The set of DataObjects this rule is scoped to, of any class (no restriction). An empty list
     * means the rule is unscoped — it always applies, regardless of which object(s) the evaluated
     * scope is checked against. Declared as a list of AbstractElement (not Concrete) to match the
     * return type Pimcore's class generator emits for manyToManyObjectRelation fields.
     *
     * @return list<AbstractElement>
     */
    public function getDependentObjects(): array;

    public function getTargetKey(): ?string;

    /**
     * One of mandatory|recommended|optional.
     */
    public function getRequirementLevel(): ?string;

    public function getWeight(): ?float;

    public function getActive(): ?bool;
}
