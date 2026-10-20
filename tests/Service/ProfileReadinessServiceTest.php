<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Dto\ProfileReadiness;
use Portadesign\DataQualityBundle\Dto\QualityProfile;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Resolver\ScopedQualityRule;
use Portadesign\DataQualityBundle\Service\ProfileReadinessService;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeNamedScopeObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeRuleChecker;
use Psr\Log\NullLogger;

final class ProfileReadinessServiceTest extends TestCase
{
    private FakeNamedScopeObject $showerEnclosures;

    private FakeNamedScopeObject $installation;

    private FakeNamedScopeObject $siko;

    private FakeNamedScopeObject $leroyMerlin;

    protected function setUp(): void
    {
        $this->showerEnclosures = $this->scopeObject(10, 'Shower enclosures');
        $this->installation = $this->scopeObject(11, 'Installation');
        $this->siko = $this->scopeObject(20, 'siko.cz');
        $this->leroyMerlin = $this->scopeObject(21, 'Leroy Merlin');
    }

    public function testCombinationWithoutProfileIsNotConfigured(): void
    {
        $readiness = $this->service([])->evaluatePair(new FakeCoreFieldObject(), $this->installation, $this->siko, [], []);

        self::assertSame(ProfileReadiness::STATUS_NOT_CONFIGURED, $readiness->status);
        self::assertNull($readiness->result);
        self::assertFalse($readiness->isReady());
    }

    public function testProfileWithOnlyRecommendedRequirementsIsNotConfiguredRatherThanReady(): void
    {
        $profile = $this->profile(1, [$this->showerEnclosures, $this->siko]);
        $rules = [
            new FakeQualityRule(id: 'global'),
            new ScopedQualityRule(new FakeQualityRule(id: 'r', requirementLevel: 'recommended'), $profile->scopeObjects),
        ];

        $readiness = $this->service(['global' => true, 'r' => true])->evaluatePair(new FakeCoreFieldObject(), $this->showerEnclosures, $this->siko, $rules, [$profile]);

        self::assertSame(ProfileReadiness::STATUS_NOT_CONFIGURED, $readiness->status);
        self::assertSame(1, $readiness->profileId);
    }

    public function testProfileReadinessCountsOwnCategoryAndChannelRulesOnly(): void
    {
        $siko = $this->profile(1, [$this->showerEnclosures, $this->siko], ['sikoOnly']);
        $leroyMerlin = $this->profile(2, [$this->showerEnclosures, $this->leroyMerlin], ['leroyMerlinOnly']);
        $rules = [
            new FakeQualityRule(id: 'global'),
            new FakeQualityRule(id: 'category', dependentObjects: [$this->showerEnclosures]),
            new FakeQualityRule(id: 'otherCategory', dependentObjects: [$this->installation]),
            new ScopedQualityRule(new FakeQualityRule(id: 'sikoOnly'), $siko->scopeObjects),
            new ScopedQualityRule(new FakeQualityRule(id: 'leroyMerlinOnly'), $leroyMerlin->scopeObjects),
        ];
        $service = $this->service(['global' => true, 'category' => true, 'otherCategory' => false, 'sikoOnly' => true, 'leroyMerlinOnly' => false]);

        $readiness = $service->evaluate(new FakeCoreFieldObject(), [$this->showerEnclosures], [$this->siko, $this->leroyMerlin], $rules, [$siko, $leroyMerlin]);

        self::assertCount(2, $readiness);
        self::assertSame(ProfileReadiness::STATUS_READY, $readiness[0]->status);
        self::assertSame(['global', 'category', 'sikoOnly'], $this->ruleIds($readiness[0]));
        self::assertSame(ProfileReadiness::STATUS_INCOMPLETE, $readiness[1]->status);
        self::assertSame(['global', 'category', 'leroyMerlinOnly'], $this->ruleIds($readiness[1]));
    }

    public function testChannelWideProfileConfiguresEveryCategoryOnThatChannel(): void
    {
        $channelWide = $this->profile(3, [$this->leroyMerlin], ['length']);
        $pair = $this->profile(2, [$this->showerEnclosures, $this->leroyMerlin], ['pairOnly']);
        $rules = [
            new ScopedQualityRule(new FakeQualityRule(id: 'length'), $channelWide->scopeObjects),
            new ScopedQualityRule(new FakeQualityRule(id: 'pairOnly'), $pair->scopeObjects),
        ];
        $service = $this->service(['length' => false, 'pairOnly' => true]);

        $installation = $service->evaluatePair(new FakeCoreFieldObject(), $this->installation, $this->leroyMerlin, $rules, [$channelWide, $pair]);
        $showerEnclosures = $service->evaluatePair(new FakeCoreFieldObject(), $this->showerEnclosures, $this->leroyMerlin, $rules, [$channelWide, $pair]);

        self::assertSame(ProfileReadiness::STATUS_INCOMPLETE, $installation->status);
        self::assertSame(['length'], $this->ruleIds($installation));
        self::assertSame(3, $installation->profileId);
        self::assertSame(['length', 'pairOnly'], $this->ruleIds($showerEnclosures));
        self::assertSame(2, $showerEnclosures->profileId);
        self::assertSame('Profile 2 + Profile 3', $showerEnclosures->profileName);
    }

    public function testTwoProfilesWithTheSameScopeAreAmbiguous(): void
    {
        $profiles = [
            $this->profile(1, [$this->showerEnclosures, $this->siko]),
            $this->profile(2, [$this->siko, $this->showerEnclosures]),
        ];

        $readiness = $this->service([])->evaluatePair(new FakeCoreFieldObject(), $this->showerEnclosures, $this->siko, [], $profiles);

        self::assertSame(ProfileReadiness::STATUS_AMBIGUOUS, $readiness->status);
    }

    public function testScopedRuleMergesConfigurationScopeWithOwnDependents(): void
    {
        $rule = new ScopedQualityRule(
            new FakeQualityRule(id: 'r', dependentObjects: [$this->siko, $this->installation]),
            [$this->showerEnclosures, $this->siko],
        );

        self::assertSame([10, 20, 11], \array_map(static fn (Concrete $object): ?int => $object->getId(), $rule->getDependentObjects()));
        self::assertSame('r', $rule->getId());
    }

    /**
     * @param array<string, bool> $resultsByRuleId
     */
    private function service(array $resultsByRuleId): ProfileReadinessService
    {
        $keyResolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $keyResolver->method('listActiveKeys')->willReturn([]);

        $evaluationService = new QualityEvaluationService(
            [new FakeRuleChecker(static fn (): bool => true, $resultsByRuleId)],
            new QualityConfigurationResolver(),
            $keyResolver,
            1,
        );

        return new ProfileReadinessService($evaluationService, new NullLogger());
    }

    /**
     * @param list<Concrete> $scopeObjects
     * @param list<string>   $mandatoryRuleIds
     */
    private function profile(int $id, array $scopeObjects, array $mandatoryRuleIds = []): QualityProfile
    {
        return new QualityProfile($id, 'Profile ' . $id, $scopeObjects, [], $mandatoryRuleIds);
    }

    private function scopeObject(int $id, string $name): FakeNamedScopeObject
    {
        $object = new FakeNamedScopeObject();
        $object->setId($id);
        $object->setName($name);

        return $object;
    }

    /**
     * @return list<string>
     */
    private function ruleIds(ProfileReadiness $readiness): array
    {
        return \array_map(static fn (array $check): string => $check['ruleId'], $readiness->toArray()['checks']);
    }
}
