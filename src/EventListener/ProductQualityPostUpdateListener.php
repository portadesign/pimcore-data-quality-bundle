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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Re-evaluates a Product's quality score against every Channel/Category it is assigned to, on every
 * real save (not workflow transitions — this fires on every persist, see
 * \Pimcore\Event\DataObjectEvents::POST_UPDATE). Never calls ->save() on any DataObject: this runs
 * inside the save transaction of the very object that triggered it.
 */
final class ProductQualityPostUpdateListener implements EventSubscriberInterface
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

    public static function getSubscribedEvents(): array
    {
        return [
            DataObjectEvents::POST_UPDATE => ['onPostUpdate', 10],
        ];
    }

    public function onPostUpdate(DataObjectEvent $event): void
    {
        if (self::$inProgress) {
            return;
        }

        if (($event->getArguments()['isAutoSave'] ?? false) === true) {
            return;
        }

        $product = $event->getObject();

        if (! $product instanceof Product) {
            return;
        }

        self::$inProgress = true;

        try {
            // Hardcoded to 'Product', not derived from $product::class — this listener only fires
            // for Product (guarded above).
            $activeRules = $this->resolver->loadActiveRules('Product');

            $channels = $this->getRelations($product, $this->channelRelationFieldName);
            $categories = $this->getRelations($product, $this->categoryRelationFieldName);
            $allScopeObjects = [...$channels, ...$categories];

            foreach ($channels as $channel) {
                $this->evaluateScope($product, $channel, $allScopeObjects, 'channel', $activeRules);
            }

            foreach ($categories as $category) {
                $this->evaluateScope($product, $category, $allScopeObjects, 'category', $activeRules);
            }
        } finally {
            self::$inProgress = false;
        }
    }

    /**
     * @return list<Concrete>
     */
    private function getRelations(Product $product, string $fieldName): array
    {
        $getter = 'get' . \ucfirst($fieldName);

        if (! \method_exists($product, $getter)) {
            $this->logger->error('ProductQualityPostUpdateListener: Product has no {getter}() method for relation field "{field}".', [
                'getter' => $getter,
                'field' => $fieldName,
            ]);

            return [];
        }

        $relations = [];

        foreach ((array) $product->{$getter}() as $relation) {
            if ($relation instanceof Concrete) {
                $relations[] = $relation;
            }
        }

        return $relations;
    }

    /**
     * @param list<Concrete>                      $allScopeObjects
     * @param list<QualityConfigurationInterface> $activeRules
     */
    private function evaluateScope(Product $product, Concrete $scopeObject, array $allScopeObjects, string $scopeType, array $activeRules): void
    {
        try {
            $result = $this->evaluationService->evaluateForScope($product, $allScopeObjects, $scopeObject, $activeRules);
            $mandatoryComplete = $result->mandatoryComplete;

            $previous = $this->stateRepository->getPreviousState((int) $product->getId(), $scopeType, (int) $scopeObject->getId());

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
                (int) $scopeObject->getId(),
                $mandatoryComplete,
                $result->score,
            );

            if ($shouldDispatch) {
                $this->eventDispatcher->dispatch(new QualityThresholdCrossedEvent(
                    object: $product,
                    scopeObject: $scopeObject,
                    direction: $direction,
                    score: $result->score,
                ));
            }
        } catch (\Throwable $exception) {
            // One bad relation must never abort the others or break the actual product
            // save in flight.
            $this->logger->error('ProductQualityPostUpdateListener: failed evaluating {scopeType} scope {scopeId} for product {productId}: {message}', [
                'scopeType' => $scopeType,
                'scopeId' => $scopeObject->getId(),
                'productId' => $product->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }
}
