<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\RuleChecker;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Portadesign\DataQualityBundle\RuleChecker\FieldPresenceChecker;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeValidLanguageProvider;

final class FieldPresenceCheckerTest extends TestCase
{
    private FieldPresenceChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FieldPresenceChecker(new FakeValidLanguageProvider(['en', 'cs']));
    }

    public function testSupportsRulesWhoseTargetKeyIsARealGetterOnTheObject(): void
    {
        $object = new FakeCoreFieldObject();

        self::assertTrue($this->checker->supports(new FakeQualityRule(targetKey: 'ean'), $object));
        self::assertFalse($this->checker->supports(new FakeQualityRule(targetKey: 'thisFieldDoesNotExist'), $object));
        self::assertFalse($this->checker->supports(new FakeQualityRule(targetKey: null), $object));
    }

    public function testPresentStringValueSatisfiesTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setEan('1234567890123');

        self::assertTrue($this->checker->check($object, new FakeQualityRule(targetKey: 'ean')));
    }

    public function testAbsentNullValueDoesNotSatisfyTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setEan(null);

        self::assertFalse($this->checker->check($object, new FakeQualityRule(targetKey: 'ean')));
    }

    public function testAbsentEmptyStringDoesNotSatisfyTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setEan('');

        self::assertFalse($this->checker->check($object, new FakeQualityRule(targetKey: 'ean')));
    }

    public function testAbsentEmptyArrayDoesNotSatisfyTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setTags([]);

        self::assertFalse($this->checker->check($object, new FakeQualityRule(targetKey: 'tags')));
    }

    public function testZeroValueSatisfiesTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setStock(0);

        self::assertTrue($this->checker->check($object, new FakeQualityRule(targetKey: 'stock')));
    }

    public function testFalseValueSatisfiesTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setActive(false);

        self::assertTrue($this->checker->check($object, new FakeQualityRule(targetKey: 'active')));
    }

    public function testUnknownFieldThrowsRuleConfigurationException(): void
    {
        $object = new FakeCoreFieldObject();

        $this->expectException(RuleConfigurationException::class);

        $this->checker->check($object, new FakeQualityRule(targetKey: 'thisFieldDoesNotExist'));
    }

    public function testMissingTargetKeyThrowsRuleConfigurationException(): void
    {
        $object = new FakeCoreFieldObject();

        $this->expectException(RuleConfigurationException::class);

        $this->checker->check($object, new FakeQualityRule(targetKey: null));
    }

    public function testLocalizedFieldPresentInEveryConfiguredLanguageSatisfiesTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        $object->setTitle(['en' => 'Hello', 'cs' => 'Ahoj']);

        self::assertTrue($this->checker->check($object, new FakeQualityRule(targetKey: 'title')));
    }

    public function testLocalizedFieldMissingInOneConfiguredLanguageDoesNotSatisfyTheRule(): void
    {
        $object = new FakeCoreFieldObject();
        // 'cs' deliberately left unset - this is exactly the bug being fixed: a plain getter()
        // (no $language arg) would only ever see the current request/session locale, so
        // whether this rule passes used to depend on which locale happened to be active.
        $object->setTitle(['en' => 'Hello']);

        self::assertFalse($this->checker->check($object, new FakeQualityRule(targetKey: 'title')));
    }

    public function testLocalizedFieldEmptyInEveryConfiguredLanguageDoesNotSatisfyTheRule(): void
    {
        $object = new FakeCoreFieldObject();

        self::assertFalse($this->checker->check($object, new FakeQualityRule(targetKey: 'title')));
    }

    public function testNonLocalizedFieldIsUnaffectedByConfiguredLanguages(): void
    {
        $checker = new FieldPresenceChecker(new FakeValidLanguageProvider(['en', 'cs', 'de']));
        $object = new FakeCoreFieldObject();
        $object->setEan('1234567890123');

        // A plain (non-localized) getter must be called exactly once, with no arguments -
        // iterating $languages for it would be both wrong (it doesn't vary by language) and
        // wasteful.
        self::assertTrue($checker->check($object, new FakeQualityRule(targetKey: 'ean')));
    }
}
