<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Contract\QualityScoreStateRepositoryInterface;
use Portadesign\DataQualityBundle\EventListener\ProductQualityPostUpdateListener;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeProduct;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeRuleChecker;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Confirms the persisted per-scope score also follows the two-axis dependentObjects semantics
 * (QualityReportControllerScopedDependentObjectsTest pins the same rule via the read-only report;
 * this pins it via the persisted upsertState call, since ProductQualityPostUpdateListener resolves
 * each channel/category axis through its own evaluateScope() call rather than sharing the
 * controller's code path).
 *
 * Rule under test has dependentObjects [category wc-kombi=485, channel siko-cz=470]; product is
 * linked to categories {sprchove-kouty=477, wc-kombi=485} and channel {siko-cz=470} — the rule is
 * applicable to the product overall (all dependents present), counts toward channel 470's score
 * and category 485's score, but must NOT count toward sibling category 477's score. A fixed
 * baseline mandatory rule (weight 3, satisfied) plus the rule under test (weight 1, unsatisfied)
 * makes 75.0 mean "counted" and 100.0 mean "not counted" — falsifiable via the persisted score.
 */
final class ProductQualityPostUpdateListenerScopedDependentObjectsTest extends TestCase
{
    private const int PRODUCT_ID = 1;

    private const int CATEGORY_WC_KOMBI = 485;

    private const int CATEGORY_SPRCHOVE_KOUTY = 477;

    private const int CHANNEL_SIKO_CZ = 470;

    public function testTwoAxisDependentRuleScoresChannelAndMatchingCategoryButNotSiblingCategory(): void
    {
        $channel = $this->makeScopeObject(self::CHANNEL_SIKO_CZ, 'Channel');
        $categoryDependent = $this->makeScopeObject(self::CATEGORY_WC_KOMBI, 'Category');
        $categorySibling = $this->makeScopeObject(self::CATEGORY_SPRCHOVE_KOUTY, 'Category');

        $baseline = new FakeQualityRule(id: 'baseline', dependentObjects: [], targetKey: null, requirementLevel: 'mandatory', weight: 3.0);
        $target = new FakeQualityRule(id: 'target', dependentObjects: [$categoryDependent, $channel], targetKey: null, requirementLevel: 'mandatory', weight: 1.0);

        $resolver = $this->createPartialMock(QualityConfigurationResolver::class, ['loadActiveRules']);
        $resolver->expects(self::once())->method('loadActiveRules')->willReturn([$baseline, $target]);

        $checker = new FakeRuleChecker(static fn (): bool => true, ['baseline' => true, 'target' => false]);

        $keyResolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $keyResolver->method('listActiveKeys')->willReturn([]);

        $evaluationService = new QualityEvaluationService([$checker], $resolver, $keyResolver, 1);

        /**
         * @var list<array{scopeType: string, scopeId: int, score: float}> $persistedScores
         */
        $persistedScores = [];

        $stateRepository = $this->createMock(QualityScoreStateRepositoryInterface::class);
        $stateRepository->method('getPreviousState')->willReturn(null);
        $stateRepository->expects(self::exactly(3))
            ->method('upsertState')
            ->willReturnCallback(function (int $productId, string $scopeType, int $scopeId, bool $mandatoryComplete, float $score) use (&$persistedScores): void {
                $persistedScores[] = ['scopeType' => $scopeType, 'scopeId' => $scopeId, 'score' => $score];
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $listener = new ProductQualityPostUpdateListener(
            $evaluationService,
            $resolver,
            $stateRepository,
            $this->createStub(EventDispatcherInterface::class),
            $logger,
            'channels',
            'categories',
        );

        $product = new FakeProduct();
        $product->setId(self::PRODUCT_ID);
        $product->setKey('test-product');
        $product->setFakeChannels([$channel]);
        $product->setFakeCategories([$categorySibling, $categoryDependent]);

        $listener->onPostUpdate(new DataObjectEvent($product));

        self::assertSame(75.0, $this->scoreFor($persistedScores, 'channel', self::CHANNEL_SIKO_CZ), 'channel siko-cz should count the dependent rule');
        self::assertSame(75.0, $this->scoreFor($persistedScores, 'category', self::CATEGORY_WC_KOMBI), 'category wc-kombi should count the dependent rule');
        self::assertSame(100.0, $this->scoreFor($persistedScores, 'category', self::CATEGORY_SPRCHOVE_KOUTY), 'sibling category sprchove-kouty must not count the dependent rule');
    }

    /**
     * @param list<array{scopeType: string, scopeId: int, score: float}> $persistedScores
     */
    private function scoreFor(array $persistedScores, string $scopeType, int $scopeId): float
    {
        foreach ($persistedScores as $entry) {
            if ($entry['scopeType'] === $scopeType && $entry['scopeId'] === $scopeId) {
                return $entry['score'];
            }
        }

        self::fail(\sprintf('No upsertState call recorded for %s#%d', $scopeType, $scopeId));
    }

    private function makeScopeObject(int $id, string $className): FakeCoreFieldObject
    {
        $object = new FakeCoreFieldObject();
        $object->setId($id);
        $object->setClassName($className);

        return $object;
    }
}
