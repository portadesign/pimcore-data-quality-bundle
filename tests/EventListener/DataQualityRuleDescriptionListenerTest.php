<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Fieldcollection;
use PHPUnit\Framework\TestCase;
use Portadesign\DataQualityBundle\Contract\ClassificationStoreKeyResolverInterface;
use Portadesign\DataQualityBundle\EventListener\DataQualityRuleDescriptionListener;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeDataQualityConfiguration;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeMutableQualityRule;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeNamedScopeObject;

/**
 * Pins DataQualityRuleDescriptionListener::onPreSave()'s generated description, in particular the
 * "Scope" segment resolveScopeLabel() produces from a rule's dependentObjects. Every rule here
 * uses targetKey: null (keyLabel always "(no target key)") so assertions isolate the scope
 * segment without also exercising ClassDefinition/Classification Store lookups.
 */
final class DataQualityRuleDescriptionListenerTest extends TestCase
{
    public function testRuleWithNoDependentObjectsDescribesGlobalScope(): void
    {
        $rule = new FakeMutableQualityRule(dependentObjects: [], requirementLevel: 'mandatory', weight: 3.0);

        $this->runListener([$rule]);

        self::assertSame('Global / (no target key) / Mandatory / Weight 3', $rule->getDescription());
    }

    public function testRuleWithOneDependentObjectNamedResolvesToItsName(): void
    {
        $category = $this->makeNamedObject('Category', 'wc-kombi', 'wc-kombi-label');
        $rule = new FakeMutableQualityRule(dependentObjects: [$category], requirementLevel: 'recommended', weight: 2.0);

        $this->runListener([$rule]);

        self::assertSame('Category: wc-kombi-label / (no target key) / Recommended / Weight 2', $rule->getDescription());
    }

    public function testRuleWithOneDependentObjectWithoutNameFallsBackToKey(): void
    {
        $channel = $this->makeNamedObject('Channel', 'siko-cz', name: null);
        $rule = new FakeMutableQualityRule(dependentObjects: [$channel], requirementLevel: 'optional', weight: 1.0);

        $this->runListener([$rule]);

        self::assertSame('Channel: siko-cz / (no target key) / Optional / Weight 1', $rule->getDescription());
    }

    public function testRuleWithSeveralDependentObjectsNamesAllOfThemInOrder(): void
    {
        $category = $this->makeNamedObject('Category', 'wc-kombi', 'wc-kombi-label');
        $channel = $this->makeNamedObject('Channel', 'siko-cz', 'siko-cz-label');
        $secondCategory = $this->makeNamedObject('Category', 'sprchove-kouty', 'sprchove-kouty-label');

        $rule = new FakeMutableQualityRule(
            dependentObjects: [$category, $channel, $secondCategory],
            requirementLevel: 'mandatory',
            weight: 1.0,
        );

        $this->runListener([$rule]);

        self::assertSame(
            'Category: wc-kombi-label + Channel: siko-cz-label + Category: sprchove-kouty-label / (no target key) / Mandatory / Weight 1',
            $rule->getDescription(),
        );
    }

    public function testRuleWithSeveralDependentObjectsAppliesPerObjectFallback(): void
    {
        $named = $this->makeNamedObject('Category', 'wc-kombi', 'wc-kombi-label');
        $unnamed = $this->makeNamedObject('Channel', 'siko-cz', name: null);

        $rule = new FakeMutableQualityRule(dependentObjects: [$named, $unnamed], requirementLevel: 'mandatory', weight: 1.0);

        $this->runListener([$rule]);

        self::assertSame(
            'Category: wc-kombi-label + Channel: siko-cz / (no target key) / Mandatory / Weight 1',
            $rule->getDescription(),
        );
    }

    /**
     * @param list<FakeMutableQualityRule> $rules
     */
    private function runListener(array $rules): void
    {
        $keyResolver = $this->createStub(ClassificationStoreKeyResolverInterface::class);
        $keyResolver->method('listActiveKeys')->willReturn([]);

        $listener = new DataQualityRuleDescriptionListener($keyResolver, 1);

        $subject = new FakeDataQualityConfiguration();
        $subject->setFakeRules(new Fieldcollection($rules));

        $listener->onPreSave(new DataObjectEvent($subject));
    }

    private function makeNamedObject(string $className, string $key, ?string $name): FakeNamedScopeObject
    {
        $object = new FakeNamedScopeObject();
        $object->setClassName($className);
        $object->setKey($key);
        $object->setName($name);

        return $object;
    }
}
