<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Support;

use PHPUnit\Framework\TestCase;
use Pimcore\Workflow\Manager;
use Portadesign\DataQualityBundle\Support\PimcoreWorkflowTransitionCatalog;

final class PimcoreWorkflowTransitionCatalogTest extends TestCase
{
    public function testSupportCheckFailureIsTreatedAsUnsupportedRatherThanThrowing(): void
    {
        $manager = $this->createStub(Manager::class);
        $manager->method('getAllWorkflows')->willReturn(['product']);
        $manager->method('getWorkflowIfExists')->willThrowException(new \Error('uninitialized typed property'));

        $catalog = new PimcoreWorkflowTransitionCatalog($manager);

        self::assertSame([], $catalog->listTransitions('Product'));
    }
}
