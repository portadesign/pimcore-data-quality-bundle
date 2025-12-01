<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Contract;

use Basilicom\DataQualityBundle\Model\ValidationResultBag;
use Pimcore\Model\DataObject\ClassDefinition\Data;

interface DefinitionInterface
{
    /**
     * @param mixed $content The content to validate (can be string, int, float, object, array, null, etc. depending on field type)
     * @param Data $fieldDefinition
     * @param array $parameters
     *
     * @return ValidationResultBag Returns ValidationResultBag with validation status and optional data
     *
     * @throws DefinitionException
     */
    public function validate(mixed $content, Data $fieldDefinition, array $parameters): ValidationResultBag;

    /**
     * @return int
     */
    public function getNecessaryParameterCount(): int;

    /**
     * @param array $parameters
     *
     * @throws DefinitionException
     */
    public function setParameters(array $parameters);

    /**
     * @return string Human-readable name of the definition
     */
    public function getName(): string;
}
