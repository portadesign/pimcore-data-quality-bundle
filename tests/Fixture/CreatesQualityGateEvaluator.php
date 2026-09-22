<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\Contract\QualityConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\QualityGateConfigurationInterface;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Resolver\QualityGateResolver;
use Portadesign\DataQualityBundle\Service\QualityEvaluationService;
use Portadesign\DataQualityBundle\Service\QualityGateEvaluator;

/**
 * Real QualityEvaluationService and real QualityConfigurationResolver::filter(); only the DB-backed
 * loaders are stubbed. Rules must use targetKey: null, otherwise label resolution hits ClassDefinition.
 */
trait CreatesQualityGateEvaluator
{
    /**
     * @param list<QualityGateConfigurationInterface> $gates
     * @param list<QualityConfigurationInterface>     $rules
     * @param array<string, bool>                     $results
     */
    private function createGateEvaluator(array $gates, array $rules, array $results, bool $checkerSupports = true): QualityGateEvaluator
    {
        $gateResolver = $this->createStub(QualityGateResolver::class);
        $gateResolver->method('loadActiveGates')->willReturn($gates);

        $rulesResolver = $this->createPartialMock(QualityConfigurationResolver::class, ['loadActiveRules']);
        $rulesResolver->method('loadActiveRules')->willReturn($rules);

        $keyResolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $keyResolver->method('listActiveKeys')->willReturn([]);

        $checker = new FakeRuleChecker(static fn (): bool => $checkerSupports, $results);

        return new QualityGateEvaluator(
            $gateResolver,
            $rulesResolver,
            new QualityEvaluationService([$checker], $rulesResolver, $keyResolver, 1),
            new FakeWorkflowTransitionCatalog([
                ['workflow' => 'product', 'workflowLabel' => 'Product', 'transition' => 'markReady', 'label' => 'Mark Ready'],
                ['workflow' => 'product', 'workflowLabel' => 'Product', 'transition' => 'publish', 'label' => 'Publish'],
            ]),
            'categories',
        );
    }
}
