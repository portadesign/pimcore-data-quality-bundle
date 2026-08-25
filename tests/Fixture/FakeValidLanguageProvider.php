<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Portadesign\DataQualityBundle\Contract\ValidLanguageProviderInterface;

final class FakeValidLanguageProvider implements ValidLanguageProviderInterface
{
    /**
     * @param list<string> $languages
     */
    public function __construct(
        private readonly array $languages = ['en', 'cs'],
    ) {
    }

    public function getValidLanguages(): array
    {
        return $this->languages;
    }
}
