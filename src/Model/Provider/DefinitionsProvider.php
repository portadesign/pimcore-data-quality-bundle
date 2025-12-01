<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Model\Provider;

use Basilicom\DataQualityBundle\DefinitionsCollection\DefinitionsCollection;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;

class DefinitionsProvider implements SelectOptionsProviderInterface
{
    private DefinitionsCollection $definitionsCollection;

    public function __construct(DefinitionsCollection $definitionsCollection)
    {
        $this->definitionsCollection = $definitionsCollection;
    }

    public function getOptions($context, $fieldDefinition): array
    {
        $options = [];
        foreach ($this->definitionsCollection->getAllTypes() as $definitionKey => $definitionClass) {
            $options[] = [
                'value' => $definitionClass,
                'key'   => $definitionKey
            ];
        }

        return $options;
    }

    public function hasStaticOptions($context, $fieldDefinition): bool
    {
        return false;
    }

    public function getDefaultValue($context, $fieldDefinition): ?string
    {
        return $fieldDefinition->getDefaultValue();
    }
}
