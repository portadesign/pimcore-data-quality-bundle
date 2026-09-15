<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Controller\QualityReportController;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeProduct;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeRuleChecker;
use Psr\Log\NullLogger;

/**
 * Pins the per-scope score semantics for a rule with `dependentObjects` spanning both axes (the
 * approved "AND for applicability, then per-scope AND/no-sibling-dependent for which score it
 * feeds" design): a rule applies to a Product at all only if every dependent id is among the
 * product's linked categories+channels (already covered by QualityConfigurationResolverTest), but
 * once applicable it only counts toward one particular scope's score if that scope is itself among
 * the dependents, or the rule has no dependent object of that scope's class at all.
 *
 * Every scenario uses a fixed baseline mandatory rule (weight 3, always satisfied) plus the rule
 * under test (weight 1, always unsatisfied): a scope score of 75.0 means the rule under test was
 * counted for that scope, 100.0 means it was not — falsifiable via the resulting score rather than
 * inspecting any intermediate rule list. Uses QualityConfigurationResolver::filter() unmocked
 * (only loadActiveRules() is stubbed, since that alone needs a DB listing) so these tests exercise
 * production scoping logic end to end through the controller's public buildReport().
 */
final class QualityReportControllerScopedDependentObjectsTest extends TestCase
{
    private const int CATEGORY_SPRCHOVE_KOUTY = 477;

    private const int CATEGORY_WC_KOMBI = 485;

    private const int CHANNEL_SIKO_CZ = 470;

    private const int CHANNEL_LIVEA_FR = 475;

    public function testSingleCategoryDependentRuleCountsForOwnCategoryAndSiblingChannel(): void
    {
        $category = $this->makeScopeObject(self::CATEGORY_SPRCHOVE_KOUTY, 'Category');
        $channel = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');

        $rule = $this->makeTargetRule([$category]);
        $report = $this->buildReport([$rule], [$category], [$channel]);

        self::assertSame(75.0, $report['byCategory'][0]['score']);
        self::assertSame(75.0, $report['byChannel'][0]['score']);
    }

    public function testTwoAxisDependentRuleCountsForListedScopesNotForSiblingCategory(): void
    {
        $categoryDependent = $this->makeScopeObject(self::CATEGORY_WC_KOMBI, 'Category');
        $categorySibling = $this->makeScopeObject(self::CATEGORY_SPRCHOVE_KOUTY, 'Category');
        $channel = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');

        $rule = $this->makeTargetRule([$categoryDependent, $channel]);
        $report = $this->buildReport([$rule], [$categorySibling, $categoryDependent], [$channel]);

        self::assertSame(75.0, $this->scoreFor($report['byChannel'], 'channelId', self::CHANNEL_SIKO_CZ));
        self::assertSame(75.0, $this->scoreFor($report['byCategory'], 'categoryId', self::CATEGORY_WC_KOMBI));
        self::assertSame(100.0, $this->scoreFor($report['byCategory'], 'categoryId', self::CATEGORY_SPRCHOVE_KOUTY));
    }

    public function testTwoAxisDependentRuleNotApplicableAtAllWhenProductMissingOneDependent(): void
    {
        $categoryDependent = $this->makeScopeObject(self::CATEGORY_WC_KOMBI, 'Category');
        $categoryPresent = $this->makeScopeObject(self::CATEGORY_SPRCHOVE_KOUTY, 'Category');
        $channel = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');

        $rule = $this->makeTargetRule([$categoryDependent, $channel]);
        $report = $this->buildReport([$rule], [$categoryPresent], [$channel]);

        self::assertSame(100.0, $report['byChannel'][0]['score']);
        self::assertSame(100.0, $report['byCategory'][0]['score']);
    }

    public function testRuleWithTwoChannelDependentsCountsForBothChannels(): void
    {
        $channelA = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');
        $channelB = $this->makeScopeObject(self::CHANNEL_LIVEA_FR, 'Channel');

        $rule = $this->makeTargetRule([$channelA, $channelB]);
        $report = $this->buildReport([$rule], [], [$channelA, $channelB]);

        self::assertSame(75.0, $this->scoreFor($report['byChannel'], 'channelId', self::CHANNEL_SIKO_CZ));
        self::assertSame(75.0, $this->scoreFor($report['byChannel'], 'channelId', self::CHANNEL_LIVEA_FR));
    }

    public function testChannelDependentRuleNotApplicableIsAbsentFromSiblingChannelScore(): void
    {
        $channelDependent = $this->makeScopeObject(self::CHANNEL_LIVEA_FR, 'Channel');
        $channelPresent = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');

        $rule = $this->makeTargetRule([$channelDependent]);
        $report = $this->buildReport([$rule], [], [$channelPresent]);

        self::assertSame(100.0, $report['byChannel'][0]['score']);
    }

    public function testUnscopedRuleCountsInEveryScopeScore(): void
    {
        $category = $this->makeScopeObject(self::CATEGORY_SPRCHOVE_KOUTY, 'Category');
        $channel = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');

        $rule = $this->makeTargetRule([]);
        $report = $this->buildReport([$rule], [$category], [$channel]);

        self::assertSame(75.0, $report['overall']['score']);
        self::assertSame(75.0, $report['byCategory'][0]['score']);
        self::assertSame(75.0, $report['byChannel'][0]['score']);
    }

    /**
     * @param list<FakeCoreFieldObject> $dependentObjects
     */
    private function makeTargetRule(array $dependentObjects): FakeQualityRule
    {
        return new FakeQualityRule(
            id: 'target',
            dependentObjects: $dependentObjects,
            targetKey: null,
            requirementLevel: 'mandatory',
            weight: 1.0,
        );
    }

    private function makeScopeObject(int $id, string $className): FakeCoreFieldObject
    {
        $object = new FakeCoreFieldObject();
        $object->setId($id);
        $object->setClassName($className);

        return $object;
    }

    /**
     * @param list<FakeQualityRule>     $rules
     * @param list<FakeCoreFieldObject> $categories
     * @param list<FakeCoreFieldObject> $channels
     *
     * @return array{overall: array, byChannel: list<array>, byCategory: list<array>}
     */
    private function buildReport(array $rules, array $categories, array $channels): array
    {
        $baseline = new FakeQualityRule(id: 'baseline', dependentObjects: [], targetKey: null, requirementLevel: 'mandatory', weight: 3.0);

        $product = new FakeProduct();
        $product->setFakeCategories($categories);
        $product->setFakeChannels($channels);

        $resolver = $this->createPartialMock(QualityConfigurationResolver::class, ['loadActiveRules']);
        $resolver->expects(self::once())->method('loadActiveRules')->willReturn([$baseline, ...$rules]);

        $checker = new FakeRuleChecker(static fn (): bool => true, ['baseline' => true, 'target' => false]);

        $keyResolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $keyResolver->method('listActiveKeys')->willReturn([]);

        $evaluationService = new QualityEvaluationService([$checker], $resolver, $keyResolver, 1);

        $controller = new QualityReportController(
            $evaluationService,
            $resolver,
            new NullLogger(),
            'channels',
            'categories',
        );

        return $controller->buildReport($product);
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function scoreFor(array $entries, string $idKey, int $id): float
    {
        foreach ($entries as $entry) {
            if ($entry[$idKey] === $id) {
                return $entry['score'];
            }
        }

        self::fail(\sprintf('No report entry found with %s = %d', $idKey, $id));
    }
}
