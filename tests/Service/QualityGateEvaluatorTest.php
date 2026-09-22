<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Dto\GateResult;
use Portadesign\DataQualityBundle\Dto\QualityCheck;
use Portadesign\DataQualityBundle\Tests\Fixture\CreatesQualityGateEvaluator;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeProduct;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityGate;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;

final class QualityGateEvaluatorTest extends TestCase
{
    use CreatesQualityGateEvaluator;

    public function testReturnsNullWhenTransitionHasNoGate(): void
    {
        $evaluator = $this->createGateEvaluator([new FakeQualityGate('product:publish')], [], []);

        self::assertNull($evaluator->evaluate(new FakeProduct(), 'product', 'revertToDraft'));
    }

    public function testGateEvaluatesUnscopedAndCategoryRulesButNotChannelRules(): void
    {
        $channel = $this->scopeObject(470);
        $category = $this->scopeObject(477);
        $product = new FakeProduct();
        $product->setFakeChannels([$channel]);
        $product->setFakeCategories([$category]);

        $rules = [
            new FakeQualityRule(id: 1, targetKey: null),
            new FakeQualityRule(id: 2, dependentObjects: [$category], targetKey: null),
            new FakeQualityRule(id: 3, dependentObjects: [$channel], targetKey: null),
        ];

        $evaluator = $this->createGateEvaluator([new FakeQualityGate('product:publish')], $rules, ['1' => false, '2' => false, '3' => false]);
        $result = $evaluator->evaluate($product, 'product', 'publish');

        self::assertInstanceOf(GateResult::class, $result);
        self::assertFalse($result->passed());
        self::assertSame(['1', '2'], $this->ruleIds($result->failedChecks()));
        self::assertSame('Publish', $result->label);
    }

    public function testMandatoryGateIgnoresRecommendedRules(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetKey: null, requirementLevel: 'mandatory'),
            new FakeQualityRule(id: 2, targetKey: null, requirementLevel: 'recommended'),
        ];

        $result = $this->createGateEvaluator([new FakeQualityGate('product:publish', 'mandatory')], $rules, ['1' => true, '2' => false])
            ->evaluate(new FakeProduct(), 'product', 'publish');

        self::assertTrue($result?->passed());
        self::assertSame(['1'], $this->ruleIds($result->checks));
    }

    public function testMandatoryRecommendedGateBlocksOnRecommendedRules(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetKey: null, requirementLevel: 'mandatory'),
            new FakeQualityRule(id: 2, targetKey: null, requirementLevel: 'recommended'),
            new FakeQualityRule(id: 3, targetKey: null, requirementLevel: 'optional'),
        ];

        $result = $this->createGateEvaluator([new FakeQualityGate('product:publish', 'mandatory_recommended')], $rules, ['1' => true, '2' => false, '3' => false])
            ->evaluate(new FakeProduct(), 'product', 'publish');

        self::assertFalse($result?->passed());
        self::assertSame(['2'], $this->ruleIds($result->failedChecks()));
    }

    public function testEvaluateAllReturnsOneResultPerGate(): void
    {
        $gates = [new FakeQualityGate('product:markReady'), new FakeQualityGate('product:publish')];
        $rules = [new FakeQualityRule(id: 1, targetKey: null)];

        $results = $this->createGateEvaluator($gates, $rules, ['1' => true])->evaluateAll(new FakeProduct());

        self::assertSame(['markReady', 'publish'], \array_map(static fn (GateResult $result): string => $result->transition, $results));
        self::assertSame('Mark Ready', $results[0]->label);
    }

    public function testIgnoresGatesWithMalformedTransition(): void
    {
        $results = $this->createGateEvaluator([new FakeQualityGate('publish'), new FakeQualityGate(null)], [], [])
            ->evaluateAll(new FakeProduct());

        self::assertSame([], $results);
    }

    private function scopeObject(int $id): FakeCoreFieldObject
    {
        $object = new FakeCoreFieldObject();
        $object->setId($id);

        return $object;
    }

    /**
     * @param list<QualityCheck> $checks
     *
     * @return list<string>
     */
    private function ruleIds(array $checks): array
    {
        return \array_map(static fn (QualityCheck $check): string => $check->ruleId, $checks);
    }
}
