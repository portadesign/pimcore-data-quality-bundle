<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Controller;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;
use Portadesign\DataQualityBundle\Installer;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
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
     * @return array{overall: array, byChannel: list<array>, byCategory: list<array>}
     */
    public function buildReport(Product $object): array
    {
        // Loaded once and reused across every axis below, instead of each evaluate() call
        // re-querying+re-filtering the full active-rule listing.
        $activeRules = $this->resolver->loadActiveRules();

        $overall = $this->evaluationService->evaluate($object, null, null, $activeRules);

        $byChannel = [];

        foreach ($this->getRelations($object, $this->channelRelationFieldName) as $channel) {
            $result = $this->evaluationService->evaluate($object, $channel, null, $activeRules);

            $byChannel[] = [
                'channelId' => $channel->getId(),
                'channelName' => $this->getRelationName($channel),
                ...$result->toArray(),
            ];
        }

        $byCategory = [];

        foreach ($this->getRelations($object, $this->categoryRelationFieldName) as $category) {
            $result = $this->evaluationService->evaluate($object, null, $category, $activeRules);

            $byCategory[] = [
                'categoryId' => $category->getId(),
                'categoryName' => $this->getRelationName($category),
                ...$result->toArray(),
            ];
        }

        return [
            'overall' => $overall->toArray(),
            'byChannel' => $byChannel,
            'byCategory' => $byCategory,
        ];
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
