<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Definition;

use Basilicom\DataQualityBundle\Exception\DefinitionException;
use Basilicom\DataQualityBundle\Contract\DefinitionInterface;
use Basilicom\DataQualityBundle\Model\ValidationResultBag;
use Pimcore\Model\DataObject\ClassDefinition\Data;

abstract class AbstractDefinition implements DefinitionInterface
{
    const NECESSARY_PARAMETER_COUNT = 0;

    protected array $parameters = [];

    public function getNecessaryParameterCount(): int
    {
        return static::NECESSARY_PARAMETER_COUNT;
    }

    public function setParameters(array $parameters): void
    {
        // bastodo: check if this is needed
        if (count($parameters) < $this->getNecessaryParameterCount()) {
            throw new DefinitionException(
                'Not enough parameters. ' .
                    'Given ' . count($parameters) . ', necessary are ' . $this->getNecessaryParameterCount(),
                DefinitionException::NOT_ENOUGH_PARAMETERS
            );
        }

        $this->parameters = $parameters;
    }

    /**
     * @throws DefinitionException
     */
    abstract public function validate(mixed $content, Data $fieldDefinition, array $parameters): ValidationResultBag;

    abstract public function getName(): string;
}
