<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Service\RuleProblemTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RuleProblemTranslatorTest extends TestCase
{
    public function testFallsBackToEnglishWithoutTranslator(): void
    {
        $translator = new RuleProblemTranslator();

        self::assertSame('missing', $translator->missing());
        self::assertSame('missing (cs)', $translator->missing('cs'));
        self::assertSame('1,245 / 1,000 characters', $translator->tooLong(1245, 1000));
        self::assertSame('1,245 / 1,000 characters (cs)', $translator->tooLong(1245, 1000, 'cs'));
    }

    public function testDelegatesToTheStudioDomainWithParameters(): void
    {
        $symfonyTranslator = $this->createMock(TranslatorInterface::class);
        $symfonyTranslator->expects(self::once())
            ->method('trans')
            ->with('portadesign_data_quality.problem.too_long_in', ['%length%' => '12', '%max%' => '5', '%language%' => 'cs'], 'studio', null)
            ->willReturn('12 / 5 znaků (cs)');

        self::assertSame('12 / 5 znaků (cs)', (new RuleProblemTranslator($symfonyTranslator))->tooLong(12, 5, 'cs'));
    }

    public function testReturnsTheFallbackWhenTheKeyHasNoTranslation(): void
    {
        $symfonyTranslator = $this->createStub(TranslatorInterface::class);
        $symfonyTranslator->method('trans')->willReturnArgument(0);

        $translator = new RuleProblemTranslator($symfonyTranslator);

        self::assertSame('missing (cs)', $translator->missing('cs'));
        self::assertSame('1 / 0 characters', $translator->tooLong(1, 0));
        self::assertSame('custom fallback', $translator->translate('channel_content.unknown', 'custom fallback'));
    }
}
