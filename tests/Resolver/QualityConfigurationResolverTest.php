<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;

final class QualityConfigurationResolverTest extends TestCase
{
    public function testScopedRuleAppliesOnlyWhenItsDependentObjectIsInScope(): void
    {
        $channelA = $this->makeElement(10);
        $channelB = $this->makeElement(20);

        $rule = new FakeQualityRule(id: 1, dependentObjects: [$channelA]);

        $resolver = new QualityConfigurationResolver();

        self::assertSame([$rule], $resolver->filter([$rule], [$channelA]));
        self::assertSame([], $resolver->filter([$rule], [$channelB]));
        self::assertSame([], $resolver->filter([$rule], []));
    }

    public function testRuleAppliesWhenAnyOfMultipleDependentObjectsIsInScope(): void
    {
        $channel = $this->makeElement(10);
        $category = $this->makeElement(30);
        $otherCategory = $this->makeElement(40);

        $rule = new FakeQualityRule(id: 1, dependentObjects: [$channel, $category]);

        $resolver = new QualityConfigurationResolver();

        self::assertSame([$rule], $resolver->filter([$rule], [$channel]));
        self::assertSame([$rule], $resolver->filter([$rule], [$category]));
        self::assertSame([], $resolver->filter([$rule], [$otherCategory]));
    }

    public function testGlobalRuleWithNoDependentObjectsIsAlwaysIncluded(): void
    {
        $channel = $this->makeElement(10);
        $category = $this->makeElement(30);

        $rule = new FakeQualityRule(id: 1, dependentObjects: []);

        $resolver = new QualityConfigurationResolver();

        self::assertSame([$rule], $resolver->filter([$rule], [$channel]));
        self::assertSame([$rule], $resolver->filter([$rule], [$category]));
        self::assertSame([$rule], $resolver->filter([$rule], []));
    }

    public function testInactiveRuleIsExcludedEvenWhenScopeMatches(): void
    {
        $channel = $this->makeElement(10);

        $rule = new FakeQualityRule(id: 1, dependentObjects: [$channel], active: false);

        $resolver = new QualityConfigurationResolver();

        self::assertSame([], $resolver->filter([$rule], [$channel]));
    }

    private function makeElement(int $id): FakeCoreFieldObject
    {
        $element = new FakeCoreFieldObject();
        $element->setId($id);

        return $element;
    }
}
