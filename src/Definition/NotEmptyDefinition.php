<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Definition;

use Basilicom\DataQualityBundle\Model\ValidationResultBag;
use Pimcore\Model\DataObject\ClassDefinition\Data;

class NotEmptyDefinition extends AbstractDefinition
{
    public function getName(): string
    {
        return 'Not Empty';
    }

    public function validate(mixed $content, Data $fieldDefinition, array $parameters): ValidationResultBag
    {
        $fieldType = $fieldDefinition->getFieldtype();

        $valid = false;

        $valid = match ($fieldType) {
            'input', 'textarea' => ($content !== null) && (trim((string)$content) !== ''),
            'numeric' => !(empty($content) && $content !== 0),
            'quantityValue' => $content !== null && !empty($content->getValue()),
            default => !empty($content),
        };

        return new ValidationResultBag($valid);
    }
}
