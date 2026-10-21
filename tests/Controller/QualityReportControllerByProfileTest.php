<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Controller\QualityReportController;
use Portadesign\DataQualityBundle\Dto\ProfileReadiness;
use Portadesign\DataQualityBundle\Dto\QualityProfile;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Resolver\QualityProfileResolver;
use Portadesign\DataQualityBundle\Resolver\ScopedQualityRule;
use Portadesign\DataQualityBundle\Service\ProfileReadinessService;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Tests\Fixture\CreatesQualityGateEvaluator;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeNamedScopeObject;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeProduct;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityGate;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeRuleChecker;
use Psr\Log\NullLogger;

final class QualityReportControllerByProfileTest extends TestCase
{
    use CreatesQualityGateEvaluator;

    public function testReportListsReadinessPerCategoryAndChannelAndAnnotatesGatedChecks(): void
    {
        $category = $this->scopeObject(477, 'Shower enclosures');
        $siko = $this->scopeObject(470, 'siko.cz');
        $leroyMerlin = $this->scopeObject(471, 'Leroy Merlin');
        $product = new FakeProduct();
        $product->setFakeCategories([$category]);
        $product->setFakeChannels([$siko, $leroyMerlin]);

        $categoryWide = new QualityProfile(8, 'Shower enclosures', [$category], [], ['categoryRule']);
        $sikoProfile = new QualityProfile(9, 'Shower enclosures x siko.cz', [$category, $siko], [], ['sikoRule']);
        $rules = [
            new ScopedQualityRule(new FakeQualityRule(id: 'categoryRule', targetKey: null), $categoryWide->scopeObjects),
            new ScopedQualityRule(new FakeQualityRule(id: 'sikoRule', targetKey: null), $sikoProfile->scopeObjects),
        ];

        $report = $this->controller($rules, [$categoryWide, $sikoProfile], ['categoryRule' => false, 'sikoRule' => true], new FakeQualityGate('product:publish'))->buildReport($product);

        self::assertSame(
            [
                ['categoryId' => 477, 'channelId' => 470, 'status' => ProfileReadiness::STATUS_INCOMPLETE, 'profileId' => 9, 'profileName' => 'Shower enclosures x siko.cz + Shower enclosures'],
                ['categoryId' => 477, 'channelId' => 471, 'status' => ProfileReadiness::STATUS_INCOMPLETE, 'profileId' => 8, 'profileName' => 'Shower enclosures'],
            ],
            \array_map(
                static fn (array $entry): array => \array_intersect_key($entry, \array_flip(['categoryId', 'channelId', 'status', 'profileId', 'profileName'])),
                $report['byProfile'],
            ),
        );
        self::assertSame(
            [['workflow' => 'product', 'transition' => 'publish', 'label' => 'Publish', 'blocking' => true]],
            \array_column($report['byProfile'][1]['checks'], 'gates', 'ruleId')['categoryRule'],
        );
    }

    public function testReportOfProductWithoutChannelsHasNoProfileReadiness(): void
    {
        $product = new FakeProduct();
        $product->setFakeCategories([$this->scopeObject(477, 'Shower enclosures')]);
        $product->setFakeChannels([]);

        self::assertSame([], $this->controller([], [], [], null)->buildReport($product)['byProfile']);
    }

    /**
     * @param list<ScopedQualityRule> $rules
     * @param list<QualityProfile>    $profiles
     * @param array<string, bool>     $results
     */
    private function controller(array $rules, array $profiles, array $results, ?FakeQualityGate $gate): QualityReportController
    {
        $resolver = new class($rules) extends QualityConfigurationResolver {
            /**
             * @param list<ScopedQualityRule> $rules
             */
            public function __construct(private readonly array $rules)
            {
            }

            public function loadActiveRules(string $targetClassName): array
            {
                return $this->rules;
            }
        };

        $keyResolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $keyResolver->method('listActiveKeys')->willReturn([]);

        $evaluationService = new QualityEvaluationService([new FakeRuleChecker(static fn (): bool => true, $results)], $resolver, $keyResolver, 1);

        $profileResolver = $this->createStub(QualityProfileResolver::class);
        $profileResolver->method('loadProfiles')->willReturnCallback(
            static fn (string $targetClassName): array => $targetClassName === 'Product' ? $profiles : [],
        );

        return new QualityReportController(
            $evaluationService,
            $resolver,
            new NullLogger(),
            'channels',
            'categories',
            $this->createGateEvaluator($gate !== null ? [$gate] : [], $rules, $results),
            $profileResolver,
            new ProfileReadinessService($evaluationService, new NullLogger()),
        );
    }

    private function scopeObject(int $id, string $name): FakeNamedScopeObject
    {
        $object = new FakeNamedScopeObject();
        $object->setId($id);
        $object->setName($name);

        return $object;
    }
}
