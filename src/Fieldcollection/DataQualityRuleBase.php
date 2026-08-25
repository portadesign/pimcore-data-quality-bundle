<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Fieldcollection;

use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData;

/**
 * Base class the generated DataQualityRule field-collection item class extends (wired via the
 * DataQualityRule field collection definition's `parentClass`, see
 * Resources/install/fieldcollection_DataQualityRule_export.json).
 *
 * Field-collection items have no DataObject-style int primary key (no oo_id) — only getIndex()
 * (position within the collection) and getObject() (the owning DataQualityConfiguration) from
 * AbstractData. This provides QualityConfigurationInterface::getId() as a synthetic composite of
 * the two, so DataQualityRule items can implement the interface (declared via that same JSON's
 * implementsInterfaces) the same way the old, now-removed QualityConfiguration DataObject class
 * did via its own native getId().
 */
abstract class DataQualityRuleBase extends AbstractData
{
    public function getId(): ?string
    {
        return \sprintf('%d:%d', $this->getObject()?->getId() ?? 0, $this->getIndex());
    }
}
