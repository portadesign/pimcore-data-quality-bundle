<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;

/**
 * Stand-in for a Data Object class that has no Classification Store ("attributes") field at all —
 * used to exercise ClassificationStoreKeyPresenceChecker's "wrong class" failure mode.
 */
final class FakeObjectWithoutAttributes extends Concrete
{
}
