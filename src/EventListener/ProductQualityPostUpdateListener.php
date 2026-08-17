<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\EventListener;

use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\QualityScoreStateRepositoryInterface;
use Portadesign\DataQualityBundle\Event\QualityThresholdCrossedEvent;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Re-evaluates a Product's quality score against every Channel/Category it is assigned to, on every
 * real save (not workflow transitions — this fires on every persist, see
 * \Pimcore\Event\DataObjectEvents::POST_UPDATE). Never calls ->save() on any DataObject: this runs
 * inside the save transaction of the very object that triggered it.
 */
final class ProductQualityPostUpdateListener
{
    private static bool $inProgress = false;

    public function __construct(
        private readonly QualityEvaluationService $evaluationService,
        private readonly QualityConfigurationResolver $resolver,
        private readonly QualityScoreStateRepositoryInterface $stateRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        #[Autowire('%portadesign_data_quality.channel_relation_field_name%')]
        private readonly string $channelRelationFieldName,
        #[Autowire('%portadesign_data_quality.category_relation_field_name%')]
        private readonly string $categoryRelationFieldName,
    ) {
    }

    #[AsEventListener(event: DataObjectEvents::POST_UPDATE)]
    public function onPostUpdate(DataObjectEvent $event): void
    {
        if (self::$inProgress) {
            return;
        }

        if (($event->getArguments()['isAutoSave'] ?? false) === true) {
            return;
        }

        $subject = $event->getObject();

        if (! $subject instanceof Product) {
            return;
        }

        self::$inProgress = true;

        try {
            // Loaded once and reused across every channel/category axis below, instead of each
            // evaluate() call re-querying+re-filtering the full active-rule listing.
            $activeRules = $this->resolver->loadActiveRules();

            $this->evaluateChannels($subject, $activeRules);
            $this->evaluateCategories($subject, $activeRules);
        } finally {
            self::$inProgress = false;
        }
    }

    /**
     * @param list<QualityConfigurationInterface> $activeRules
     */
    private function evaluateChannels(Product $product, array $activeRules): void
    {
        $getter = 'get' . \ucfirst($this->channelRelationFieldName);

        if (! \method_exists($product, $getter)) {
            $this->logger->error('ProductQualityPostUpdateListener: Product has no {getter}() method for channel relation field "{field}".', [
                'getter' => $getter,
                'field' => $this->channelRelationFieldName,
            ]);

            return;
        }

        $channels = $product->{$getter}();

        foreach ((array) $channels as $channel) {
            if (! $channel instanceof Concrete) {
                continue;
            }

            $this->evaluateScope($product, $channel, null, 'channel', $activeRules);
        }
    }

    /**
     * @param list<QualityConfigurationInterface> $activeRules
     */
    private function evaluateCategories(Product $product, array $activeRules): void
    {
        $getter = 'get' . \ucfirst($this->categoryRelationFieldName);

        if (! \method_exists($product, $getter)) {
            $this->logger->error('ProductQualityPostUpdateListener: Product has no {getter}() method for category relation field "{field}".', [
                'getter' => $getter,
                'field' => $this->categoryRelationFieldName,
            ]);

            return;
        }

        $categories = $product->{$getter}();

        foreach ((array) $categories as $category) {
            if (! $category instanceof Concrete) {
                continue;
            }

            $this->evaluateScope($product, null, $category, 'category', $activeRules);
        }
    }

    /**
     * @param list<QualityConfigurationInterface> $activeRules
     */
    private function evaluateScope(Product $product, ?Concrete $channel, ?Concrete $category, string $scopeType, array $activeRules): void
    {
        $scope = $channel ?? $category;

        if ($scope === null) {
            return;
        }

        try {
            $result = $this->evaluationService->evaluate($product, $channel, $category, $activeRules);
            $mandatoryComplete = $result->mandatoryComplete;

            $previous = $this->stateRepository->getPreviousState((int) $product->getId(), $scopeType, (int) $scope->getId());

            $shouldDispatch = false;
            $direction = 'reached';

            if ($previous !== null) {
                if ($previous['mandatory_complete'] !== $mandatoryComplete) {
                    $shouldDispatch = true;
                    $direction = $mandatoryComplete ? 'reached' : 'lost';
                }
            } elseif ($mandatoryComplete) {
                // First-ever save landing incomplete does NOT dispatch; first-ever save
                // landing complete DOES dispatch "reached".
                $shouldDispatch = true;
                $direction = 'reached';
            }

            $this->stateRepository->upsertState(
                (int) $product->getId(),
                $scopeType,
                (int) $scope->getId(),
                $mandatoryComplete,
                $result->score,
            );

            if ($shouldDispatch) {
                $this->eventDispatcher->dispatch(new QualityThresholdCrossedEvent(
                    object: $product,
                    channel: $channel,
                    category: $category,
                    direction: $direction,
                    score: $result->score,
                ));
            }
        } catch (\Throwable $exception) {
            // One bad relation must never abort the others or break the actual product
            // save in flight.
            $this->logger->error('ProductQualityPostUpdateListener: failed evaluating {scopeType} scope {scopeId} for product {productId}: {message}', [
                'scopeType' => $scopeType,
                'scopeId' => $scope->getId(),
                'productId' => $product->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }
}
