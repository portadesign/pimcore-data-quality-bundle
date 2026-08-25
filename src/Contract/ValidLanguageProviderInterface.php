<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Contract;

interface ValidLanguageProviderInterface
{
    /**
     * Every language code configured as valid for this Pimcore install (system settings ->
     * General -> Valid languages), e.g. ['cs', 'de', 'en', 'fr', 'hu', 'sk'].
     *
     * @return list<string>
     */
    public function getValidLanguages(): array;
}
