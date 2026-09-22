<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Dto\GateResult;
use Portadesign\DataQualityBundle\Dto\QualityCheck;

final class GateResultTest extends TestCase
{
    public function testPassesWhenEveryCheckIsSatisfied(): void
    {
        $result = $this->makeResult([$this->check('1', true), $this->check('2', true)]);

        self::assertTrue($result->passed());
        self::assertSame([], $result->failedChecks());
    }

    public function testFailsAndListsUnsatisfiedChecks(): void
    {
        $result = $this->makeResult([$this->check('1', true), $this->check('2', false)]);

        self::assertFalse($result->passed());
        self::assertSame(['2'], \array_map(static fn (QualityCheck $check): string => $check->ruleId, $result->failedChecks()));
    }

    public function testCoversAndBlocksByRuleId(): void
    {
        $result = $this->makeResult([$this->check('1', true), $this->check('2', false)]);

        self::assertTrue($result->covers('1'));
        self::assertFalse($result->blocks('1'));
        self::assertTrue($result->blocks('2'));
        self::assertFalse($result->covers('3'));
    }

    public function testToArrayExposesFailedChecks(): void
    {
        $array = $this->makeResult([$this->check('2', false)])->toArray();

        self::assertSame('product', $array['workflow']);
        self::assertSame('publish', $array['transition']);
        self::assertSame('Publish', $array['label']);
        self::assertSame('mandatory', $array['requiredLevel']);
        self::assertFalse($array['passed']);
        self::assertSame('2', $array['failedChecks'][0]['ruleId']);
    }

    /**
     * @param list<QualityCheck> $checks
     */
    private function makeResult(array $checks): GateResult
    {
        return new GateResult(
            workflow: 'product',
            transition: 'publish',
            label: 'Publish',
            requiredLevel: 'mandatory',
            checks: $checks,
        );
    }

    private function check(string $ruleId, bool $satisfied): QualityCheck
    {
        return new QualityCheck(
            ruleId: $ruleId,
            ruleName: 'Rule ' . $ruleId,
            satisfied: $satisfied,
            level: 'mandatory',
            weight: 1.0,
            label: 'Field ' . $ruleId,
        );
    }
}
