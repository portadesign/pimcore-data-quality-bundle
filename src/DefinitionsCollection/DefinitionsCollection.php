<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\DefinitionsCollection;

use Basilicom\DataQualityBundle\Contract\DefinitionInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class DefinitionsCollection
{
    public function __construct(
        #[TaggedIterator('data_quality.definition')]
        private readonly iterable $definitions
    ) {}

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
