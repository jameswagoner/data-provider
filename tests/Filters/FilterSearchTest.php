<?php

namespace Illuminatech\DataProvider\Test\Filters;

use Illuminatech\DataProvider\DataProvider;
use Illuminatech\DataProvider\Filters\FilterSearch;
use Illuminatech\DataProvider\Test\Support\Item;
use Illuminatech\DataProvider\Test\TestCase;

class FilterSearchTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedDatabase();
    }

    public function testApply()
    {
        $dataProvider = (new DataProvider(Item::class))->setFilters([
            'search' => new FilterSearch(['name', 'slug']),
        ]);

        $items = $dataProvider->prepare(['filter' => ['search' => 'm-5']])
            ->get();

        $this->assertCount(1, $items);
        $this->assertSame('item-5', $items[0]->slug);

        $items = $dataProvider->prepare(['filter' => ['search' => 'm 5']])
            ->get();

        $this->assertCount(1, $items);
        $this->assertSame('item-5', $items[0]->slug);
    }
}