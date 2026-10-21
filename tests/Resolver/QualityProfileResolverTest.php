<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\DataQualityConfiguration;
use Portadesign\DataQualityBundle\Dto\QualityProfile;
use Portadesign\DataQualityBundle\Resolver\QualityProfileResolver;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeNamedScopeObject;
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

    public function testApplicableProfilesKeepsOnlyProfilesWhoseWholeScopeIsLinked(): void
    {
        $category = $this->scopeObject(10);
        $siko = $this->scopeObject(20);
        $leroyMerlin = $this->scopeObject(21);

        $categoryAndSiko = new QualityProfile(1, 'category x siko', [$category, $siko], []);
        $categoryAndLeroyMerlin = new QualityProfile(2, 'category x leroy merlin', [$category, $leroyMerlin], []);
        $sikoOnly = new QualityProfile(3, 'siko', [$siko], []);

        $applicable = (new QualityProfileResolver())->applicableProfiles(
            [$categoryAndSiko, $categoryAndLeroyMerlin, $sikoOnly],
            [$category, $siko],
        );

        self::assertSame([1, 3], \array_map(static fn (QualityProfile $profile): int => $profile->configurationId, $applicable));
    }

    public function testApplicableProfilesIsEmptyWhenObjectHasNoScope(): void
    {
        $profile = new QualityProfile(1, 'siko', [$this->scopeObject(20)], []);

        self::assertSame([], (new QualityProfileResolver())->applicableProfiles([$profile], []));
    }

    public function testScopeObjectsOfDropsEntriesThatAreNotDataObjects(): void
    {
        $configuration = new class extends DataQualityConfiguration {
            public function getScopeObjects(): array
            {
                $first = new FakeNamedScopeObject();
                $first->setId(10);
                $second = new FakeNamedScopeObject();
                $second->setId(20);

                return [3 => $first, 5 => 'broken', 6 => null, 9 => $second];
            }
        };

        $scopeObjects = QualityProfileResolver::scopeObjectsOf($configuration);

        self::assertSame([10, 20], \array_map(static fn (Concrete $object): ?int => $object->getId(), $scopeObjects));
        self::assertSame([0, 1], \array_keys($scopeObjects));
    }

    private function scopeObject(int $id): FakeNamedScopeObject
    {
        $object = new FakeNamedScopeObject();
        $object->setId($id);

        return $object;
    }
}
