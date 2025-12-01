<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\DefinitionsCollection\Factory;

use Basilicom\DataQualityBundle\Contract\DefinitionInterface;
use Basilicom\DataQualityBundle\DefinitionsCollection\FieldDefinition;
use Pimcore\Model\DataObject\Fieldcollection\Data\DataQualityFieldDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

class FieldDefinitionFactory
{
    const DEFAULT_GROUP = '__default__';

    public function __construct(
        private readonly ContainerInterface $container
    ) {}

    public function get(DataQualityFieldDefinition $definition): FieldDefinition
    {
        list($fieldName, $title) = explode('@@@', $definition->getField());

        if (strpos($title, '###')) {
            list($title, $language) = explode('###', $title);
        }

        return new FieldDefinition(
            $this->getClass($definition->getCondition()),
            $fieldName,
            $title,
            empty($definition->getWeight()) ? 0 : (int) $definition->getWeight(),
            $this->parameterStringToArray((string) $definition->getParameters()),
            $language ?? null
        );
    }

    private function parameterStringToArray(string $parameterString): array
    {
        $parameters = [];
        $parameterString = trim($parameterString);
        if (empty($parameterString)) {
            return $parameters;
        }

        foreach (str_getcsv($parameterString, ';') as $parameterItem) {
            $parameters[] = trim($parameterItem);
        }

        return $parameters;
    }

    private function getClass(?string $conditionClass): ?DefinitionInterface
    {
        if (!class_exists($conditionClass)) {
            return null;
        }

        // Try to get from container first (for proper DI)
        if ($this->container->has($conditionClass)) {
            return $this->container->get($conditionClass);
        }

        // Fallback to direct instantiation if not in container
        return new $conditionClass();
    }
}
