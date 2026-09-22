<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\OptionProvider;

use PHPUnit\Framework\TestCase;
use Pimcore\Model\DataObject\ClassDefinition\Data\Select;
use Portadesign\DataQualityBundle\OptionProvider\QualityGateTransitionOptionsProvider;
use Portadesign\DataQualityBundle\Tests\Fixture\FakeWorkflowTransitionCatalog;

final class QualityGateTransitionOptionsProviderTest extends TestCase
{
    public function testOptionsAreWorkflowTransitionPairsOfTheTargetClass(): void
    {
        $catalog = new FakeWorkflowTransitionCatalog([
            ['workflow' => 'product', 'workflowLabel' => 'Product', 'transition' => 'publish', 'label' => 'Publish'],
        ]);
        $owner = new class () {
            public function getTargetClass(): string
            {
                return 'Product';
            }
        };

        $options = (new QualityGateTransitionOptionsProvider($catalog))->getOptions(['object' => $owner], new Select());

        self::assertSame([['key' => 'Product › Publish', 'value' => 'product:publish']], $options);
        self::assertSame(['Product'], $catalog->requestedClassNames);
    }

    public function testListsAllWorkflowsWithoutTargetClass(): void
    {
        $catalog = new FakeWorkflowTransitionCatalog();

        (new QualityGateTransitionOptionsProvider($catalog))->getOptions([], new Select());

        self::assertSame([null], $catalog->requestedClassNames);
    }
}
