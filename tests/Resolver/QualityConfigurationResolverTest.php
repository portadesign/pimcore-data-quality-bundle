<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;

final class QualityConfigurationResolverTest extends TestCase
{
    public function testChannelScopedRuleAppliesOnlyToItsOwnChannel(): void
    {
        $channelA = $this->makeElement(10);
        $channelB = $this->makeElement(20);

        $rule = new FakeQualityRule(id: 1, channel: $channelA);

        $resolver = new QualityConfigurationResolver('channels', 'categories');

        self::assertSame([$rule], $resolver->filter([$rule], $channelA, null));
        self::assertSame([], $resolver->filter([$rule], $channelB, null));
        self::assertSame([], $resolver->filter([$rule], null, null));
    }

    public function testCategoryScopedRuleAppliesOnlyToItsOwnCategory(): void
    {
        $categoryA = $this->makeElement(30);
        $categoryB = $this->makeElement(40);

        $rule = new FakeQualityRule(id: 1, category: $categoryA);

        $resolver = new QualityConfigurationResolver('channels', 'categories');

        self::assertSame([$rule], $resolver->filter([$rule], null, $categoryA));
        self::assertSame([], $resolver->filter([$rule], null, $categoryB));
        self::assertSame([], $resolver->filter([$rule], null, null));
    }

    public function testGlobalRuleWithNoChannelOrCategoryIsAlwaysIncluded(): void
    {
        $channel = $this->makeElement(10);
        $category = $this->makeElement(30);

        $rule = new FakeQualityRule(id: 1, channel: null, category: null);

        $resolver = new QualityConfigurationResolver('channels', 'categories');

        self::assertSame([$rule], $resolver->filter([$rule], $channel, null));
        self::assertSame([$rule], $resolver->filter([$rule], null, $category));
        self::assertSame([$rule], $resolver->filter([$rule], null, null));
    }

    public function testInactiveRuleIsExcludedEvenWhenScopeMatches(): void
    {
        $channel = $this->makeElement(10);

        $rule = new FakeQualityRule(id: 1, channel: $channel, active: false);

        $resolver = new QualityConfigurationResolver('channels', 'categories');

        self::assertSame([], $resolver->filter([$rule], $channel, null));
    }

    private function makeElement(int $id): FakeCoreFieldObject
    {
        $element = new FakeCoreFieldObject();
        $element->setId($id);

        return $element;
    }
}
