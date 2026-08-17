<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

use Pimcore\Model\Element\AbstractElement;

interface QualityConfigurationInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    /**
     * Declared as ?AbstractElement (not ?Concrete) to match the return type Pimcore's class
     * generator emits for manyToOneRelation fields — the class definition restricts it to
     * Channel objects only (objectsAllowed: true, assetsAllowed/documentsAllowed: false), so in
     * practice this is always a Concrete or null.
     */
    public function getChannel(): ?AbstractElement;

    /**
     * @see self::getChannel()
     */
    public function getCategory(): ?AbstractElement;

    /**
     * One of coreField|classificationStoreKey.
     */
    public function getTargetType(): ?string;

    public function getTargetKey(): ?string;

    /**
     * One of mandatory|recommended|optional.
     */
    public function getRequirementLevel(): ?string;

    public function getWeight(): ?float;

    public function getMessage(): ?string;

    public function getRuleType(): ?string;

    public function getActive(): ?bool;
}
