<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\DefinitionsCollection;

use Basilicom\DataQualityBundle\Contract\DefinitionInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class DefinitionsCollection
{
    /**
     * @var iterable<DefinitionInterface>
     */
    private iterable $definitions;

    /**
     * @param iterable<DefinitionInterface> $definitions
     */
    public function __construct(
        #[TaggedIterator('data_quality.definition')] iterable $definitions
    ) {
        $this->definitions = $definitions;
    }

    /**
     * @return array<string, string> Array mapping definition name to class name
     */
    public function getAllTypes(): array
    {
        $types = [];
        foreach ($this->definitions as $definition) {
            $types[$definition->getName()] = get_class($definition);
        }

        return $types;
    }

    /**
     * @return iterable<DefinitionInterface>
     */
    public function getDefinitions(): iterable
    {
        return $this->definitions;
    }
}
