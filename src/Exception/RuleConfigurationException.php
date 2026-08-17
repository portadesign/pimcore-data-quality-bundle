<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Exception;

/**
 * Thrown when a QualityConfiguration rule references a field, Classification Store key, or
 * ruleType/targetType combination that cannot actually be resolved against the object/config being
 * evaluated. Deliberately never caught silently — a typo'd targetKey must surface as a hard error
 * rather than being scored as "missing".
 */
final class RuleConfigurationException extends \RuntimeException
{
}
