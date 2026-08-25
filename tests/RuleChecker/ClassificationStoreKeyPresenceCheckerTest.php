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
use Portadesign\DataQualityBundle\Tests\Fixture\FakeValidLanguageProvider;

final class ClassificationStoreKeyPresenceCheckerTest extends TestCase
{
    private const int STORE_ID = 1;
    private const int KEY_ID = 42;
    private const int GROUP_ID = 7;

    public function testSupportsRulesWhoseTargetKeyResolvesInTheClassificationStore(): void
    {
        $object = new FakeObjectWithAttributes();

        self::assertTrue($this->makeChecker(self::KEY_ID)->supports(new FakeQualityRule(targetKey: 'AS136'), $object));
        self::assertFalse($this->makeChecker(null)->supports(new FakeQualityRule(targetKey: 'UNKNOWN_CODE'), $object));
        self::assertFalse($this->makeChecker(self::KEY_ID)->supports(new FakeQualityRule(targetKey: null), $object));
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

    public function testValuePresentInEveryConfiguredLanguageSatisfiesTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);

        $store = $this->createStub(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP_ID => true]);
        $store->method('getLocalizedKeyValue')->willReturnCallback(
            static fn (int $groupId, int $keyId, ?string $language = 'default'): ?string => match ($language) {
                'en' => 'English value',
                'cs' => 'Czech value',
                default => null,
            },
        );

        $object = new FakeObjectWithAttributes();
        $object->setAttributes($store);

        self::assertTrue($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testValueMissingInOneConfiguredLanguageDoesNotSatisfyTheRule(): void
    {
        $checker = $this->makeChecker(self::KEY_ID);

        $store = $this->createStub(Classificationstore::class);
        $store->method('getActiveGroups')->willReturn([self::GROUP_ID => true]);
        // 'cs' deliberately has no value (and no "default" fallback either, simulated by the
        // stub returning null): this is the bug being fixed - only checking one language/
        // "default" used to let this incomplete key silently pass.
        $store->method('getLocalizedKeyValue')->willReturnCallback(
            static fn (int $groupId, int $keyId, ?string $language = 'default'): ?string => match ($language) {
                'en' => 'English value',
                default => null,
            },
        );

        $object = new FakeObjectWithAttributes();
        $object->setAttributes($store);

        self::assertFalse($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
    }

    public function testDefaultOnlyValueSatisfiesTheRuleForEveryConfiguredLanguage(): void
    {
        // Simulates this project's actual current data shape: every value lives under Pimcore's
        // "default" pseudo-language bucket, never under a real per-language key.
        // Classificationstore::getLocalizedKeyValue() unconditionally falls back to "default"
        // when a language-specific value is absent, so this must stay a no-op - the same value
        // is returned regardless of which language is asked for.
        $checker = $this->makeChecker(self::KEY_ID);
        $object = $this->makeObjectWithValue('some value');

        self::assertTrue($checker->check($object, new FakeQualityRule(targetType: 'classificationStoreKey', targetKey: 'AS136')));
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

        return new ClassificationStoreKeyPresenceChecker(self::STORE_ID, $resolver, new FakeValidLanguageProvider(['en', 'cs']));
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
