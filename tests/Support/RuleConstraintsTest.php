<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Support;

use PHPUnit\Framework\TestCase;
use Pimcore\Model\Element\AbstractElement;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Resolver\ScopedQualityRule;
use Portadesign\DataQualityBundle\RuleChecker\FieldPresenceChecker;
use Portadesign\DataQualityBundle\Support\RuleConstraints;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeValidLanguageProvider;

final class RuleConstraintsTest extends TestCase
{
    public function testTextLengthCountsPlainTextWithoutHtmlAndCollapsedWhitespace(): void
    {
        self::assertSame(13, RuleConstraints::textLength("<p>Sprchov&yacute;</p>\n\n<p>  kout</p>"));
    }

    public function testMaxLengthIsNullForRulesWithoutTheConstraint(): void
    {
        self::assertNull(RuleConstraints::maxLength(new FakeQualityRule()));
        self::assertNull(RuleConstraints::maxLength($this->ruleWithMaxLength(0)));
        self::assertSame(1000, RuleConstraints::maxLength($this->ruleWithMaxLength(1000)));
    }

    public function testScopedRuleExposesTheWrappedRulesMaxLength(): void
    {
        self::assertSame(5, (new ScopedQualityRule($this->ruleWithMaxLength(5), []))->getMaxLength());
    }

    public function testFieldPresenceCheckerFailsAndExplainsTextOverTheLimit(): void
    {
        $checker = new FieldPresenceChecker(new FakeValidLanguageProvider(['en']));
        $object = new FakeCoreFieldObject();
        $object->setEan('1234567890123');
        $rule = $this->ruleWithMaxLength(5);

        self::assertFalse($checker->check($object, $rule));
        self::assertSame('13 / 5 characters', $checker->explain($object, $rule));

        $object->setEan('12345');
        self::assertTrue($checker->check($object, $rule));
    }

    private function ruleWithMaxLength(?int $maxLength): QualityConfigurationInterface
    {
        return new class($maxLength) implements QualityConfigurationInterface {
            public function __construct(private readonly ?int $maxLength)
            {
            }

            public function getMaxLength(): ?int
            {
                return $this->maxLength;
            }

            public function getId(): ?string
            {
                return 'limited';
            }

            public function getDescription(): ?string
            {
                return 'Limited rule';
            }

            /**
             * @return list<AbstractElement>
             */
            public function getDependentObjects(): array
            {
                return [];
            }

            public function getTargetKey(): ?string
            {
                return 'ean';
            }

            public function getRequirementLevel(): ?string
            {
                return 'mandatory';
            }

            public function getWeight(): ?float
            {
                return 1.0;
            }

            public function getActive(): ?bool
            {
                return true;
            }
        };
    }
}
