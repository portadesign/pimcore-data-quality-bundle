<?php

declare(strict_types=1);

namespace Basilicom\DataQualityBundle\Exception;

use Basilicom\DataQualityBundle\Exception\AbstractDataQualityException;

class DefinitionException extends AbstractDataQualityException
{
    const NOT_ENOUGH_PARAMETERS = 2000;
}
