<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\OptionProvider;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\DynamicOptionsProvider\SelectOptionsProviderInterface;
use Portadesign\DataQualityBundle\Contract\WorkflowTransitionCatalogInterface;

final class QualityGateTransitionOptionsProvider implements SelectOptionsProviderInterface
{
    public function __construct(
        private readonly WorkflowTransitionCatalogInterface $transitionCatalog,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array{key: string, value: string}>
     */
    public function getOptions(array $context, Data $fieldDefinition): array
    {
        return \array_map(
            static fn (array $transition): array => [
                'key' => \sprintf('%s › %s', $transition['workflowLabel'], $transition['label']),
                'value' => \sprintf('%s:%s', $transition['workflow'], $transition['transition']),
            ],
            $this->transitionCatalog->listTransitions($this->resolveTargetClass($context)),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function hasStaticOptions(array $context, Data $fieldDefinition): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function getDefaultValue(array $context, Data $fieldDefinition): string|array|null
    {
        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveTargetClass(array $context): ?string
    {
        $owner = $context['object'] ?? null;

        if (! \is_object($owner) || ! \method_exists($owner, 'getTargetClass')) {
            return null;
        }

        $targetClass = $owner->getTargetClass();

        return \is_string($targetClass) && $targetClass !== '' ? $targetClass : null;
    }
}
