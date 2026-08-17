<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Exception\RuleConfigurationException;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeRuleChecker;

final class QualityEvaluationServiceTest extends TestCase
{
    public function testScoreIsWeightedRatioOfSatisfiedMandatoryRules(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetType: 'coreField', requirementLevel: 'mandatory', weight: 3.0),
            new FakeQualityRule(id: 2, targetType: 'coreField', requirementLevel: 'mandatory', weight: 1.0),
            // recommended rule: reported, but excluded from the score
            new FakeQualityRule(id: 3, targetType: 'coreField', requirementLevel: 'recommended', weight: 5.0),
        ];

        $checker = new FakeRuleChecker('coreField', [
            '1' => true,  // satisfied, weight 3
            '2' => false, // unsatisfied, weight 1
            '3' => false, // recommended, irrelevant to score
        ]);

        $service = $this->makeService($rules, [$checker]);
        $result = $service->evaluate(new FakeCoreFieldObject());

        // 3 / (3 + 1) * 100 = 75.0
        self::assertSame(75.0, $result->score);
        self::assertFalse($result->mandatoryComplete);
        self::assertCount(3, $result->checks);
    }

    public function testAllMandatoryRulesSatisfiedScoresOneHundred(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetType: 'coreField', requirementLevel: 'mandatory', weight: 2.0),
            new FakeQualityRule(id: 2, targetType: 'coreField', requirementLevel: 'mandatory', weight: 2.0),
        ];

        $checker = new FakeRuleChecker('coreField', ['1' => true, '2' => true]);

        $service = $this->makeService($rules, [$checker]);
        $result = $service->evaluate(new FakeCoreFieldObject());

        self::assertSame(100.0, $result->score);
        self::assertTrue($result->mandatoryComplete);
    }

    public function testNoMandatoryRulesResolvedScoresOneHundred(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetType: 'coreField', requirementLevel: 'recommended', weight: 1.0),
            new FakeQualityRule(id: 2, targetType: 'coreField', requirementLevel: 'optional', weight: 1.0),
        ];

        $checker = new FakeRuleChecker('coreField', ['1' => false, '2' => false]);

        $service = $this->makeService($rules, [$checker]);
        $result = $service->evaluate(new FakeCoreFieldObject());

        self::assertSame(100.0, $result->score);
        self::assertTrue($result->mandatoryComplete);
        self::assertCount(2, $result->checks);
    }

    public function testNoRulesResolvedAtAllScoresOneHundred(): void
    {
        $service = $this->makeService([], []);
        $result = $service->evaluate(new FakeCoreFieldObject());

        self::assertSame(100.0, $result->score);
        self::assertTrue($result->mandatoryComplete);
        self::assertSame([], $result->checks);
    }

    public function testWeightSkewRoundsScoreToOneHundredButMandatoryCompleteStaysFalse(): void
    {
        // Total mandatory weight 20001, satisfied 20000: the ratio rounds to "100.00" at two
        // decimals, but one mandatory rule is still genuinely unmet. mandatoryComplete must reflect
        // the exact (pre-rounding) comparison, not the rounded score.
        $rules = [
            new FakeQualityRule(id: 1, targetType: 'coreField', requirementLevel: 'mandatory', weight: 20000.0),
            new FakeQualityRule(id: 2, targetType: 'coreField', requirementLevel: 'mandatory', weight: 1.0),
        ];

        $checker = new FakeRuleChecker('coreField', ['1' => true, '2' => false]);

        $service = $this->makeService($rules, [$checker]);
        $result = $service->evaluate(new FakeCoreFieldObject());

        self::assertSame(100.0, $result->score);
        self::assertFalse($result->mandatoryComplete);
    }

    public function testScoreRoundsToTwoDecimals(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetType: 'coreField', requirementLevel: 'mandatory', weight: 1.0),
            new FakeQualityRule(id: 2, targetType: 'coreField', requirementLevel: 'mandatory', weight: 1.0),
            new FakeQualityRule(id: 3, targetType: 'coreField', requirementLevel: 'mandatory', weight: 1.0),
        ];

        $checker = new FakeRuleChecker('coreField', ['1' => true, '2' => false, '3' => false]);

        $service = $this->makeService($rules, [$checker]);
        $result = $service->evaluate(new FakeCoreFieldObject());

        // 1 / 3 * 100 = 33.333... -> 33.33
        self::assertSame(33.33, $result->score);
    }

    public function testUnsupportedRuleThrowsRuleConfigurationException(): void
    {
        $rules = [
            new FakeQualityRule(id: 1, targetType: 'classificationStoreKey', requirementLevel: 'mandatory'),
        ];

        // Only a "coreField" checker is registered — nothing supports this rule.
        $checker = new FakeRuleChecker('coreField', ['1' => true]);

        $service = $this->makeService($rules, [$checker]);

        $this->expectException(RuleConfigurationException::class);

        $service->evaluate(new FakeCoreFieldObject());
    }

    /**
     * @param list<FakeQualityRule> $rules
     * @param list<FakeRuleChecker> $checkers
     */
    private function makeService(array $rules, array $checkers): QualityEvaluationService
    {
        $resolver = $this->createStub(QualityConfigurationResolver::class);
        $resolver->method('resolve')->willReturn($rules);

        return new QualityEvaluationService($checkers, $resolver);
    }
}
