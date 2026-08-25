<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Support;

use Pimcore\Tool;
use Portadesign\DataQualityBundle\Contract\ValidLanguageProviderInterface;

final class PimcoreValidLanguageProvider implements ValidLanguageProviderInterface
{
    /**
     * @return list<string>
     */
    public function getValidLanguages(): array
    {
        return Tool::getValidLanguages();
    }
}
