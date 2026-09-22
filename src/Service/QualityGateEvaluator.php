<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Service;

use Pimcore\Model\DataObject\Concrete;
use Portadesign\DataQualityBundle\Contract\QualityGateConfigurationInterface;
use Portadesign\DataQualityBundle\Contract\WorkflowTransitionCatalogInterface;
use Portadesign\DataQualityBundle\Dto\GateResult;
use Portadesign\DataQualityBundle\Dto\QualityCheck;
use Portadesign\DataQualityBundle\Dto\QualityResult;
use Portadesign\DataQualityBundle\Resolver\QualityConfigurationResolver;
use Portadesign\DataQualityBundle\Resolver\QualityGateResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class QualityGateEvaluator
{
    private const array LEVELS = [
        'mandatory' => ['mandatory'],
        'mandatory_recommended' => ['mandatory', 'recommended'],
    ];

    public function __construct(
        private readonly QualityGateResolver $gateResolver,
        private readonly QualityConfigurationResolver $rulesResolver,
        private readonly QualityEvaluationService $evaluationService,
        private readonly WorkflowTransitionCatalogInterface $transitionCatalog,
        #[Autowire('%portadesign_data_quality.category_relation_field_name%')]
        private readonly string $categoryRelationFieldName,
    ) {
    }

    public function evaluate(Concrete $object, string $workflow, string $transition): ?GateResult
    {
        $key = $workflow . ':' . $transition;

        foreach ($this->loadGates($object) as $gate) {
            if ($gate->getTransition() === $key) {
                return $this->buildResult($gate, $this->evaluateGateScope($object));
            }
        }

        return null;
    }

    /**
     * @return list<GateResult>
     */
    public function evaluateAll(Concrete $object): array
    {
        $gates = $this->loadGates($object);

        if ($gates === []) {
            return [];
        }

        $quality = $this->evaluateGateScope($object);

        return \array_map(fn (QualityGateConfigurationInterface $gate): GateResult => $this->buildResult($gate, $quality), $gates);
    }

    /**
     * @return list<QualityGateConfigurationInterface>
     */
    private function loadGates(Concrete $object): array
    {
        $className = $object->getClassName();

        if ($className === null || $className === '') {
            return [];
        }

        return \array_values(\array_filter(
            $this->gateResolver->loadActiveGates($className),
            static fn (QualityGateConfigurationInterface $gate): bool => \str_contains((string) $gate->getTransition(), ':'),
        ));
    }

    private function evaluateGateScope(Concrete $object): QualityResult
    {
        return $this->evaluationService->evaluate(
            $object,
            $this->loadCategories($object),
            $this->rulesResolver->loadActiveRules((string) $object->getClassName()),
        );
    }

    private function buildResult(QualityGateConfigurationInterface $gate, QualityResult $quality): GateResult
    {
        [$workflow, $transition] = \explode(':', (string) $gate->getTransition(), 2);
        $requiredLevel = (string) $gate->getRequiredLevel();
        $levels = self::LEVELS[$requiredLevel] ?? self::LEVELS['mandatory'];

        return new GateResult(
            workflow: $workflow,
            transition: $transition,
            label: $this->transitionCatalog->getTransitionLabel($workflow, $transition),
            requiredLevel: $requiredLevel,
            checks: \array_values(\array_filter(
                $quality->checks,
                static fn (QualityCheck $check): bool => \in_array($check->level, $levels, true),
            )),
        );
    }

    /**
     * @return list<Concrete>
     */
    private function loadCategories(Concrete $object): array
    {
        $getter = 'get' . \ucfirst($this->categoryRelationFieldName);

        if (! \method_exists($object, $getter)) {
            return [];
        }

        $categories = [];

        foreach ((array) $object->{$getter}() as $category) {
            if ($category instanceof Concrete) {
                $categories[] = $category;
            }
        }

        return $categories;
    }
}
