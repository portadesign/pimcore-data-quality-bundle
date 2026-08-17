<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\RuleChecker;

use Pimcore\Model\DataObject\Classificationstore;
use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Portadesign\DataQualityBundle\RuleChecker\ClassificationStoreKeyPresenceChecker;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeObjectWithAttributes;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeObjectWithoutAttributes;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;

final class ClassificationStoreKeyPresenceCheckerTest extends TestCase
{
    private const int STORE_ID = 1;
    private const int KEY_ID = 42;
    private const int GROUP_ID = 7;

    public function testSupportsClassificationStoreKeyTargetTypeOnly(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);

        self::assertTrue($checker->supports(new FakeQualityRule(targetType: 'classificationStoreKey')));
        self::assertFalse($checker->supports(new FakeQualityRule(targetType: 'coreField')));
    }

    public function testPresentValueInActiveGroupSatisfiesTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);
        $object = $this->makeObjectWithValue('some value');

        self::assertTrue($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testAbsentNullValueDoesNotSatisfyTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);
        $object = $this->makeObjectWithValue(null);

        self::assertFalse($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testZeroValueSatisfiesTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);
        $object = $this->makeObjectWithValue(0);

        self::assertTrue($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testFalseValueSatisfiesTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);
        $object = $this->makeObjectWithValue(false);

        self::assertTrue($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testNoActiveGroupsDoesNotSatisfyTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);

        $store = $this->createStub(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([]);

        $object = new FakeObjectWithAttributes();
        $object->setAttributes($store);

        self::assertFalse($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testUnknownKeyCodeThrowsRuleConfigurationException(): void
    {
        $checker = $this->makeChecker(null);
        $object = new FakeObjectWithAttributes();

        $this->expectException(RuleConfigurationException::class);

        $checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'UNKNOWN_CODE'));
    }

    public function testObjectOfWrongClassWithoutAttributesFieldThrowsRuleConfigurationException(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);
        $object = new FakeObjectWithoutAttributes();

        $this->expectException(RuleConfigurationException::class);

        $checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136'));
    }

    private function makeChecker(?int $resolvedKeyId): ClassificationStoreKeyPresenceChecker
    {
        $resolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $resolver->method('resolveKeyId')->willReturn($resolvedKeyId);

        return new ClassificationStoreKeyPresenceChecker(self::STORE_ID, $resolver);
    }

    private function makeObjectWithValue(mixed $value): FakeObjectWithAttributes
    {
        $store = $this->createStub(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP_ID => true]);
        $store->method('getLocalizedKeyValue')->willReturn($value);

        $object = new FakeObjectWithAttributes();
        $object->setAttributes($store);

        return $object;
    }
}
