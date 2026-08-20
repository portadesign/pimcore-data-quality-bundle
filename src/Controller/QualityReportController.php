<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Controller;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Dto\QualityResult;
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
        // re-querying+re-filtering the full active-rule listing. Hardcoded to 'Product' — this
        // controller/report is Product-specific throughout (see class docblock).
        $activeRules = $this->resolver->loadActiveRules('Product');

        $overall = $this->evaluateSafely($object, [], $activeRules, 'overall');

        $byChannel = [];

        foreach ($this->getRelations($object, $this->channelRelationFieldName) as $channel) {
            $result = $this->evaluateSafely($object, [$channel], $activeRules, 'channel');

            $byChannel[] = [
                'channelId' => $channel->getId(),
                'channelName' => $this->getRelationName($channel),
                ...$result->toArray(),
            ];
        }

        $byCategory = [];

        foreach ($this->getRelations($object, $this->categoryRelationFieldName) as $category) {
            $result = $this->evaluateSafely($object, [$category], $activeRules, 'category');

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
     * Same defensive handling as ProductQualityPostUpdateListener::evaluateScope(): a single
     * misconfigured rule (e.g. a typo'd targetKey) must not take down the whole report for every
     * product. Catches \Throwable, logs it, and returns a result indicating this axis could not
     * be evaluated instead of letting the exception propagate.
     *
     * Deliberate tradeoff, not yet hardened: this also swallows infrastructure failures (e.g. a
     * transient DB error inside a rule checker) as a degraded "0% quality" result rather than
     * letting them bubble as a hard error. Acceptable for this demo bundle's risk profile today;
     * revisit (e.g. let \Doctrine\DBAL\Exception/\PDOException propagate instead) before this
     * feeds anything where a silent false "incomplete" signal would have real consequences.
     *
     * @param list<Concrete>                      $scopeObjects
     * @param list<QualityConfigurationInterface> $activeRules
     */
    private function evaluateSafely(Product $object, array $scopeObjects, array $activeRules, string $scopeType): QualityResult
    {
        try {
            return $this->evaluationService->evaluate($object, $scopeObjects, $activeRules);
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
                channelId: $this->findScopeId($scopeObjects, 'Channel'),
                categoryId: $this->findScopeId($scopeObjects, 'Category'),
                checks: [],
            );
        }
    }

    /**
     * @param list<Concrete> $scopeObjects
     */
    private function findScopeId(array $scopeObjects, string $className): ?int
    {
        foreach ($scopeObjects as $scopeObject) {
            if ($scopeObject->getClassName() === $className) {
                return $scopeObject->getId();
            }
        }

        return null;
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
