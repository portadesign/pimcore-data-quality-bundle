<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Controller\QualityReportController;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeCoreFieldObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeProduct;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeRuleChecker;
use Psr\Log\NullLogger;

/**
 * Exercises QualityReportController::buildReport() — the response-shape assembly logic (relation
 * traversal, name fallback, overall/byChannel/byCategory assembly) — without a booted Pimcore
 * kernel/DB, matching this bundle's established fixture-based unit-test style. The thin
 * Concrete::getById()/404 guard in report() itself is intentionally left untested here: it's a
 * two-line static lookup with no branching logic of its own to regress.
 */
final class QualityReportControllerTest extends TestCase
{
    public function testBuildReportAssemblesOverallByChannelAndByCategory(): void
    {
        $channel = new FakeCoreFieldObject();
        $channel->setId(470);
        $channel->setKey('siko-cz');

        $category = new FakeCoreFieldObject();
        $category->setId(477);
        $category->setKey('sprchove-kouty');

        $product = new FakeProduct();
        $product->setFakeChannels([$channel]);
        $product->setFakeCategories([$category]);

        // One global (unscoped) rule feeds "overall"; one channel-scoped and one category-scoped
        // rule feed their respective per-axis entries. All satisfied, so every score is 100.
        $rules = [
            new FakeQualityRule(id: 1, requirementLevel: 'mandatory', weight: 1.0),
            new FakeQualityRule(id: 2, dependentObjects: [$channel], requirementLevel: 'mandatory', weight: 1.0),
            new FakeQualityRule(id: 3, dependentObjects: [$category], requirementLevel: 'mandatory', weight: 1.0),
        ];

        $controller = $this->makeController($rules, [$channel, $category]);
        $report = $controller->buildReport($product);

        self::assertSame(100.0, $report['overall']['score']);
        self::assertTrue($report['overall']['mandatoryComplete']);

        self::assertCount(1, $report['byChannel']);
        self::assertSame(470, $report['byChannel'][0]['channelId']);
        self::assertSame('siko-cz', $report['byChannel'][0]['channelName']);
        self::assertSame(100.0, $report['byChannel'][0]['score']);

        self::assertCount(1, $report['byCategory']);
        self::assertSame(477, $report['byCategory'][0]['categoryId']);
        self::assertSame('sprchove-kouty', $report['byCategory'][0]['categoryName']);
        self::assertSame(100.0, $report['byCategory'][0]['score']);
    }

    public function testBuildReportOnProductWithNoRelationsReturnsEmptyAxesAndFullOverall(): void
    {
        $product = new FakeProduct();
        $product->setFakeChannels([]);
        $product->setFakeCategories([]);

        $controller = $this->makeController([], []);
        $report = $controller->buildReport($product);

        self::assertSame(100.0, $report['overall']['score']);
        self::assertSame([], $report['byChannel']);
        self::assertSame([], $report['byCategory']);
    }

    public function testBuildReportFallsBackToKeyWhenRelationHasNoName(): void
    {
        // FakeCoreFieldObject deliberately has no getName() — getRelationName() must fall back to
        // getKey() rather than fatal or return an empty label.
        $channel = new FakeCoreFieldObject();
        $channel->setId(999);
        $channel->setKey('unnamed-channel');

        $product = new FakeProduct();
        $product->setFakeChannels([$channel]);
        $product->setFakeCategories([]);

        $controller = $this->makeController([], [$channel]);
        $report = $controller->buildReport($product);

        self::assertSame('unnamed-channel', $report['byChannel'][0]['channelName']);
    }

    /**
     * @param list<FakeQualityRule> $rules
     * @param list<FakeCoreFieldObject> $knownScopes channel/category objects referenced by $rules,
     *                                                needed so the stubbed resolver's filter() can
     *                                                do real id-based scope matching
     */
    private function makeController(array $rules, array $knownScopes): QualityReportController
    {
        $resolver = $this->createStub(QualityConfigurationResolver::class);
        $resolver->method('loadActiveRules')->willReturn($rules);
        $resolver->method('filter')->willReturnCallback(
            static function (array $rules, array $scopeObjects) {
                $scopeIds = array_map(static fn (FakeCoreFieldObject $scopeObject): ?int => $scopeObject->getId(), $scopeObjects);

                return array_values(array_filter(
                    $rules,
                    static function (FakeQualityRule $rule) use ($scopeIds): bool {
                        $dependentObjects = $rule->getDependentObjects();

                        if ($dependentObjects === []) {
                            return true;
                        }

                        $dependentIds = array_map(static fn (FakeCoreFieldObject $dependentObject): ?int => $dependentObject->getId(), $dependentObjects);

                        return array_intersect($dependentIds, $scopeIds) !== [];
                    }
                ));
            }
        );

        $checker = new FakeRuleChecker('coreField', array_fill_keys(
            array_map(static fn (FakeQualityRule $rule): string => (string) $rule->getId(), $rules),
            true
        ));

        $evaluationService = new QualityEvaluationService([$checker], $resolver);

        return new QualityReportController(
            $evaluationService,
            $resolver,
            new NullLogger(),
            'channels',
            'categories',
        );
    }
}
