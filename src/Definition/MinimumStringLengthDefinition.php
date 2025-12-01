<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Definition;

use Basilicom\DataQualityBundle\Exception\DefinitionException;
use Basilicom\DataQualityBundle\Model\ValidationResultBag;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class MinimumStringLengthDefinition extends AbstractDefinition
{
    const NECESSARY_PARAMETER_COUNT = 1;

    public function getName(): string
    {
        return 'Minimum String Length';
    }

    /**
     * @throws DefinitionException
     */
    public function validate(mixed $content, Data $fieldDefinition, array $parameters): ValidationResultBag
    {
        $fieldName = $fieldDefinition->getName();
        $fieldType = $fieldDefinition->getFieldtype();

        $length = empty($parameters) ? 0 : $parameters[0];

        $valid = match ($fieldType) {
            'input', 'textarea', 'number' => ($content !== null) && (mb_strlen(trim((string)$content)) >= $length),
            default => throw new DefinitionException('fieldtype ' . $fieldType . ' of field ' . $fieldName . ' is not supported.'),
        };

        return new ValidationResultBag($valid);
    }
}
