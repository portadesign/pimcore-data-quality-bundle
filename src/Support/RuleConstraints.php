<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Support;

use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;

/**
 * Optional rule constraints. Read via method_exists() rather than QualityConfigurationInterface so
 * rules generated before a constraint field existed (and test doubles) keep working.
 */
final class RuleConstraints
{
    /**
     * Maximum text length in characters, counted on plain text (see textLength()); null = no limit.
     */
    public static function maxLength(QualityConfigurationInterface $rule): ?int
    {
        if (! \method_exists($rule, 'getMaxLength')) {
            return null;
        }

        $maxLength = $rule->getMaxLength();

        return \is_numeric($maxLength) && (int) $maxLength > 0 ? (int) $maxLength : null;
    }

    /**
     * Length of a value as a reader sees it: HTML tags removed, entities decoded, whitespace runs
     * collapsed, so a WYSIWYG description is measured like the text a channel displays.
     */
    public static function textLength(string $value): int
    {
        $text = \html_entity_decode(\strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = \trim((string) \preg_replace('/\s+/u', ' ', $text));

        return \mb_strlen($text);
    }
}
