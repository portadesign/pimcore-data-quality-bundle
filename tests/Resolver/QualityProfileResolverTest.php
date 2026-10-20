<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Resolver\QualityProfileResolver;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;

final class QualityProfileResolverTest extends TestCase
{
    public function testMandatoryRuleIdsOnlyCoverActiveMandatoryRulesWithPositiveWeight(): void
    {
        $rules = [
            new FakeQualityRule(id: 'mandatory'),
            new FakeQualityRule(id: 'recommended', requirementLevel: 'recommended'),
            new FakeQualityRule(id: 'inactive', active: false),
            new FakeQualityRule(id: 'zeroWeight', weight: 0.0),
        ];

        self::assertSame(['mandatory'], QualityProfileResolver::mandatoryRuleIdsOf($rules));
    }
}
