<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Service;

use Pimcore\Security\User\TokenStorageUserResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RuleProblemTranslator
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?TokenStorageUserResolver $userResolver = null,
    ) {
    }

    public function missing(?string $language = null): string
    {
        return $language === null
            ? $this->trans('portadesign_data_quality.problem.missing', [], 'missing')
            : $this->trans('portadesign_data_quality.problem.missing_in', ['%language%' => $language], \sprintf('missing (%s)', $language));
    }

    public function tooLong(int $length, int $maxLength, ?string $language = null): string
    {
        $parameters = ['%length%' => \number_format($length), '%max%' => \number_format($maxLength)];
        $fallback = \sprintf('%s / %s characters', $parameters['%length%'], $parameters['%max%']);

        return $language === null
            ? $this->trans('portadesign_data_quality.problem.too_long', $parameters, $fallback)
            : $this->trans('portadesign_data_quality.problem.too_long_in', [...$parameters, '%language%' => $language], \sprintf('%s (%s)', $fallback, $language));
    }

    /**
     * @param array<string, string> $parameters
     */
    public function translate(string $key, string $fallback, array $parameters = []): string
    {
        return $this->trans($key, $parameters, $fallback);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters, string $fallback): string
    {
        if ($this->translator === null) {
            return $fallback;
        }

        return $this->translator->trans($key, $parameters, 'studio', $this->userResolver?->getUser()?->getLanguage());
    }
}
