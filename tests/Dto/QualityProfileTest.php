<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Dto\QualityProfile;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeNamedScopeObject;

final class QualityProfileTest extends TestCase
{
    public function testProfileAppliesOnlyWhenEveryScopeObjectIsLinked(): void
    {
        $profile = $this->profile([10, 20]);

        self::assertTrue($profile->appliesTo([10, 20]));
        self::assertTrue($profile->appliesTo([20, 99, 10]));
        self::assertFalse($profile->appliesTo([10]));
        self::assertFalse($profile->appliesTo([20, 99]));
        self::assertFalse($profile->appliesTo([]));
    }

    public function testExactScopeIgnoresOrderAndDuplicatesButNotExtraOrMissingObjects(): void
    {
        $profile = $this->profile([20, 10, 20]);

        self::assertTrue($profile->hasExactScope([10, 20]));
        self::assertTrue($profile->hasExactScope([20, 10, 10]));
        self::assertFalse($profile->hasExactScope([10]));
        self::assertFalse($profile->hasExactScope([10, 20, 30]));
        self::assertFalse($profile->hasExactScope([10, 30]));
    }

    public function testScopeIdsAreSortedAndUnique(): void
    {
        self::assertSame([5, 10, 20], $this->profile([20, 5, 10, 20])->scopeIds());
    }

    /**
     * @param list<int> $scopeIds
     */
    private function profile(array $scopeIds): QualityProfile
    {
        $scopeObjects = [];

        foreach ($scopeIds as $id) {
            $object = new FakeNamedScopeObject();
            $object->setId($id);
            $scopeObjects[] = $object;
        }

        return new QualityProfile(1, 'Profile', $scopeObjects, []);
    }
}
