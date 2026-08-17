<?php

declare(strict_types=1);

namespace Portadesign\DataQualityBundle\Tests\Fixture;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;

/**
 * Stand-in for a generated Product Data Object: overrides getChannels()/getCategories() so tests
 * don't have to touch getClass()/ClassDefinition (which needs a booted Pimcore kernel/DB), the same
 * way FakeCoreFieldObject stands in for a generated core-field class.
 */
final class FakeProduct extends Product
{
    /**
     * Named distinctly from Product's own protected $channels/$categories properties — reusing
     * those names with a narrower (private) visibility is a fatal "access level must be protected
     * or weaker" error.
     *
     * @var list<Concrete>
     */
    private array $fakeChannels = [];

    /**
     * @var list<Concrete>
     */
    private array $fakeCategories = [];

    /**
     * @param list<Concrete> $channels
     */
    public function setFakeChannels(array $channels): void
    {
        $this->fakeChannels = $channels;
    }

    /**
     * @param list<Concrete> $categories
     */
    public function setFakeCategories(array $categories): void
    {
        $this->fakeCategories = $categories;
    }

    public function getChannels(): array
    {
        return $this->fakeChannels;
    }

    public function getCategories(): array
    {
        return $this->fakeCategories;
    }
}
