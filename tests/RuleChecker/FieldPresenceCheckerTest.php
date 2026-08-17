<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\RuleChecker;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Portadesign\DataQualityBundle\RuleChecker\FieldPresenceChecker;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;

final class FieldPresenceCheckerTest extends TestCase
{
    private FieldPresenceChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FieldPresenceChecker();
    }

    public function testSupportsCoreFieldTargetTypeOnly(): void
    {
        self::assertTrue($this->checker->supports(new FakeQualityRule(targetType: 'coreField')));
        self::assertFalse($this->checker->supports(new FakeQualityRule(targetType: 'classificationStoreKey')));
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
}
