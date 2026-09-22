<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Controller;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Dto\GateResult;
use Portadesign\DataQualityBundle\Dto\QualityResult;
use Portadesign\DataQualityBundle\Installer;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Service\QualityGateEvaluator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Read-only live quality report for a single Product: an "overall" score (rules scoped to no
 * particular channel/category), plus one score per channel/category the product is actually
 * assigned to. Never persists anything — this is a separate read path from
 * ProductQualityPostUpdateListener, which is what maintains the `portadesign_quality_scores`
 * table on save.
 */
#[Route(path: '/pimcore-studio/api/quality-bundle', name: 'portadesign_data_quality_')]
#[IsGranted(Installer::PERMISSION_KEY)]
class QualityReportController extends AbstractController
{
    public function __construct(
        private readonly QualityEvaluationService $evaluationService,
        private readonly QualityConfigurationResolver $resolver,
        private readonly LoggerInterface $logger,
        #[Autowire('%portadesign_data_quality.channel_relation_field_name%')]
        private readonly string $channelRelationFieldName,
        #[Autowire('%portadesign_data_quality.category_relation_field_name%')]
        private readonly string $categoryRelationFieldName,
        private readonly QualityGateEvaluator $gateEvaluator,
    ) {
    }

    #[Route('/objects/{id}/report', name: 'report', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function report(int $id): JsonResponse
    {
        $object = Concrete::getById($id);

        if (! $object instanceof Product) {
            return $this->json(['error' => \sprintf('Product #%d not found', $id)], 404);
        }

        return $this->json($this->buildReport($object));
    }

    /**
     * Assembles the report payload for an already-loaded Product. Split out from report() so the
     * response-shape logic (relation traversal, name fallback, overall/byChannel/byCategory
     * assembly) is unit-testable without a booted Pimcore kernel/DB — only the thin
     * Concrete::getById() lookup above needs one.
     *
     * @return array{overall: array<string, mixed>, byChannel: list<array<string, mixed>>, byCategory: list<array<string, mixed>>, gates: list<array<string, mixed>>}
     */
    public function buildReport(Product $object): array
    {
        $activeRules = $this->resolver->loadActiveRules('Product');

        $channels = $this->getRelations($object, $this->channelRelationFieldName);
        $categories = $this->getRelations($object, $this->categoryRelationFieldName);
        $allScopeObjects = [...$channels, ...$categories];

        $overall = $this->evaluateSafely($object, $allScopeObjects, null, $activeRules, 'overall');

        $byChannel = [];

        foreach ($channels as $channel) {
            $result = $this->evaluateSafely($object, $allScopeObjects, $channel, $activeRules, 'channel');

            $byChannel[] = [
                ...$result->toArray(),
                'channelId' => $channel->getId(),
                'channelName' => $this->getRelationName($channel),
            ];
        }

        $byCategory = [];

        foreach ($categories as $category) {
            $result = $this->evaluateSafely($object, $allScopeObjects, $category, $activeRules, 'category');

            $byCategory[] = [
                ...$result->toArray(),
                'categoryId' => $category->getId(),
                'categoryName' => $this->getRelationName($category),
            ];
        }

        $gates = $this->evaluateGatesSafely($object);

        return [
            'overall' => $this->annotateGates([...$overall->toArray(), 'channelId' => null, 'categoryId' => null], $gates),
            'byChannel' => \array_map(fn (array $result): array => $this->annotateGates($result, $gates), $byChannel),
            'byCategory' => \array_map(fn (array $result): array => $this->annotateGates($result, $gates), $byCategory),
            'gates' => \array_map(static fn (GateResult $gate): array => $gate->toArray(), $gates),
        ];
    }

    /**
     * A single misconfigured rule must not take down the whole report; logs and degrades to a 0%
     * result instead of propagating.
     *
     * @param list<Concrete>                      $allScopeObjects
     * @param list<QualityConfigurationInterface> $activeRules
     */
    private function evaluateSafely(Product $object, array $allScopeObjects, ?Concrete $scopeObject, array $activeRules, string $scopeType): QualityResult
    {
        try {
            return $this->evaluationService->evaluateForScope($object, $allScopeObjects, $scopeObject, $activeRules);
        } catch (\Throwable $exception) {
            $this->logger->error('QualityReportController: failed evaluating {scopeType} scope for product {productId}: {message}', [
                'scopeType' => $scopeType,
                'productId' => $object->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new QualityResult(
                score: 0.0,
                mandatoryComplete: false,
                checks: [],
            );
        }
    }

    /**
     * @return list<GateResult>
     */
    private function evaluateGatesSafely(Product $object): array
    {
        try {
            return $this->gateEvaluator->evaluateAll($object);
        } catch (\Throwable $exception) {
            $this->logger->error('QualityReportController: failed evaluating quality gates for product {productId}: {message}', [
                'productId' => $object->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $result
     * @param list<GateResult>     $gates
     *
     * @return array<string, mixed>
     */
    private function annotateGates(array $result, array $gates): array
    {
        $result['checks'] = \array_map(static function (array $check) use ($gates): array {
            $check['gates'] = [];

            foreach ($gates as $gate) {
                if ($gate->covers($check['ruleId'])) {
                    $check['gates'][] = [
                        'workflow' => $gate->workflow,
                        'transition' => $gate->transition,
                        'label' => $gate->label,
                        'blocking' => $gate->blocks($check['ruleId']),
                    ];
                }
            }

            return $check;
        }, (array) ($result['checks'] ?? []));

        return $result;
    }

    /**
     * @return list<Concrete>
     */
    private function getRelations(Product $product, string $fieldName): array
    {
        $getter = 'get' . \ucfirst($fieldName);

        if (! \method_exists($product, $getter)) {
            $this->logger->error('QualityReportController: Product has no {getter}() method for relation field "{field}".', [
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

    private function getRelationName(Concrete $relation): string
    {
        if (\method_exists($relation, 'getName')) {
            $name = $relation->getName();

            if (\is_string($name) && $name !== '') {
                return $name;
            }
        }

        return (string) $relation->getKey();
    }
}
