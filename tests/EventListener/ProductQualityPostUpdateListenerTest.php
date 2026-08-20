<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\QualityScoreStateRepositoryInterface;
use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;
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
 * Covers the dual-axis dispatch truth table in ProductQualityPostUpdateListener::evaluateScope():
 * whether a QualityThresholdCrossedEvent is dispatched (and in which direction) depending on the
 * previously persisted state vs. the freshly evaluated mandatoryComplete flag, exercised
 * independently for the channel and the category axis.
 *
 * QualityEvaluationService is used as a real instance rather than mocked (it's final, so PHPUnit
 * can't double it) — its behaviour is made deterministic via a single mandatory rule and a
 * mocked (non-final) QualityConfigurationResolver, giving the same control over the resulting
 * score/mandatoryComplete without needing to touch the class's finality.
 */
final class ProductQualityPostUpdateListenerTest extends TestCase
{
    private const int PRODUCT_ID = 1;
    private const int SCOPE_ID = 99;

    /**
     * @return iterable<string, array{0: string, 1: array{mandatory_complete: bool, score: float}|null, 2: bool, 3: ?string}>
     */
    public static function truthTableProvider(): iterable
    {
        foreach (['channel', 'category'] as $scopeType) {
            yield "{$scopeType}: no prior state, lands incomplete -> no dispatch" => [$scopeType, null, false, null];
            yield "{$scopeType}: no prior state, lands complete -> dispatch reached" => [$scopeType, null, true, 'reached'];
            yield "{$scopeType}: prior incomplete, now complete -> dispatch reached" => [
                $scopeType,
                ['mandatory_complete' => false, 'score' => 0.0],
                true,
                'reached',
            ];
            yield "{$scopeType}: prior complete, now incomplete -> dispatch lost" => [
                $scopeType,
                ['mandatory_complete' => true, 'score' => 100.0],
                false,
                'lost',
            ];
            yield "{$scopeType}: no change (complete -> complete) -> no dispatch, state still persisted" => [
                $scopeType,
                ['mandatory_complete' => true, 'score' => 100.0],
                true,
                null,
            ];
        }
    }

    #[DataProvider('truthTableProvider')]
    public function testDispatchTruthTable(string $scopeType, ?array $previousState, bool $satisfied, ?string $expectedDirection): void
    {
        $expectedScore = $satisfied ? 100.0 : 0.0;

        $rule = new FakeQualityRule(id: 1);
        $checker = new FakeRuleChecker(static fn (): bool => true, ['1' => $satisfied]);

        $resolver = $this->createStub(QualityConfigurationResolver::class);
        $resolver->method('loadActiveRules')->willReturn([$rule]);
        $resolver->method('filter')->willReturn([$rule]);

        $evaluationService = new QualityEvaluationService([$checker], $resolver);

        $stateRepository = $this->createMock(QualityScoreStateRepositoryInterface::class);
        $stateRepository->expects(self::once())
            ->method('getPreviousState')
            ->with(self::PRODUCT_ID, $scopeType, self::SCOPE_ID)
            ->willReturn($previousState);
        $stateRepository->expects(self::once())
            ->method('upsertState')
            ->with(self::PRODUCT_ID, $scopeType, self::SCOPE_ID, $satisfied, $expectedScore);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        if ($expectedDirection !== null) {
            $eventDispatcher->expects(self::once())
                ->method('dispatch')
                ->with(self::callback(function (QualityThresholdCrossedEvent $event) use ($expectedDirection, $expectedScore): bool {
                    self::assertSame($expectedDirection, $event->getDirection());
                    self::assertSame($expectedScore, $event->getScore());

                    return true;
                }))
                ->willReturnArgument(0);
        } else {
            $eventDispatcher->expects(self::never())->method('dispatch');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $listener = new ProductQualityPostUpdateListener(
            $evaluationService,
            $resolver,
            $stateRepository,
            $eventDispatcher,
            $logger,
            'channels',
            'categories',
        );

        $scopeElement = new FakeCoreFieldObject();
        $scopeElement->setId(self::SCOPE_ID);

        $product = new FakeProduct();
        $product->setId(self::PRODUCT_ID);
        $product->setKey('test-product');
        $product->setFakeChannels($scopeType === 'channel' ? [$scopeElement] : []);
        $product->setFakeCategories($scopeType === 'category' ? [$scopeElement] : []);

        $listener->onPostUpdate(new DataObjectEvent($product));
    }

    public function testAutoSaveIsIgnored(): void
    {
        $resolver = $this->createMock(QualityConfigurationResolver::class);
        $resolver->expects(self::never())->method('loadActiveRules');

        $evaluationService = new QualityEvaluationService([], $resolver);

        $stateRepository = $this->createMock(QualityScoreStateRepositoryInterface::class);
        $stateRepository->expects(self::never())->method('getPreviousState');
        $stateRepository->expects(self::never())->method('upsertState');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $logger = $this->createStub(LoggerInterface::class);

        $listener = new ProductQualityPostUpdateListener(
            $evaluationService,
            $resolver,
            $stateRepository,
            $eventDispatcher,
            $logger,
            'channels',
            'categories',
        );

        $product = new FakeProduct();
        $product->setId(self::PRODUCT_ID);

        $listener->onPostUpdate(new DataObjectEvent($product, ['isAutoSave' => true]));
    }

    public function testNonProductObjectIsIgnored(): void
    {
        $resolver = $this->createMock(QualityConfigurationResolver::class);
        $resolver->expects(self::never())->method('loadActiveRules');

        $evaluationService = new QualityEvaluationService([], $resolver);

        $stateRepository = $this->createMock(QualityScoreStateRepositoryInterface::class);
        $stateRepository->expects(self::never())->method('getPreviousState');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $logger = $this->createStub(LoggerInterface::class);

        $listener = new ProductQualityPostUpdateListener(
            $evaluationService,
            $resolver,
            $stateRepository,
            $eventDispatcher,
            $logger,
            'channels',
            'categories',
        );

        $listener->onPostUpdate(new DataObjectEvent(new FakeCoreFieldObject()));
    }
}
