<?php

namespace Illuminatech\DataProvider\Test;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminatech\DataProvider\DataProvider;
use Illuminatech\DataProvider\Exceptions\InvalidQueryException;
use Illuminatech\DataProvider\Filters\FilterCallback;
use Illuminatech\DataProvider\Filters\FilterExact;
use Illuminatech\DataProvider\Filters\FilterSearch;
use Illuminatech\DataProvider\Sort;
use Illuminatech\DataProvider\Test\Support\Item;

class DataProviderTest extends TestCase
{
    public function testNormalizeFilters()
    {
        $dataProvider = new DataProvider(Item::class);

        $dataProvider->setFilters([
            'id',
            'alias' => 'name',
            'object' => new FilterExact('name'),
            'search' => ['name', 'description'],
            'callback' => function ($source, $name, $value) {
                return $source;
            },
        ]);

        $filters = $dataProvider->getFilters();

        $this->assertTrue($filters['id'] instanceof FilterExact);
        $this->assertTrue($filters['object'] instanceof FilterExact);
        $this->assertTrue($filters['callback'] instanceof FilterCallback);
        $this->assertTrue($filters['alias'] instanceof FilterExact);
        $this->assertTrue($filters['search'] instanceof FilterSearch);
    }

    /**
     * @depends testNormalizeFilters
     */
    public function testApplyFilter()
    {
        $dataProvider = (new DataProvider(Item::class))
            ->setFilters([
                'id' => new FilterExact('id'),
            ]);

        $items = $dataProvider->get(['filter' => ['id' => 5]]);
        $this->assertCount(1, $items);
        $this->assertSame(5, $items[0]->id);
    }

    /**
     * @depends testApplyFilter
     */
    public function testApplyFilterEmpty()
    {
        $dataProvider = (new DataProvider(Item::class))
            ->setFilters([
                'id' => new FilterExact('id'),
            ]);

        $items = $dataProvider->get(['filter' => ['id' => null]]);
        $this->assertCount(Item::query()->count(), $items);

        $items = $dataProvider->get(['filter' => ['id' => '']]);
        $this->assertCount(Item::query()->count(), $items);

        $items = $dataProvider->get(['filter' => ['id' => []]]);
        $this->assertCount(Item::query()->count(), $items);
    }

    /**
     * @depends testNormalizeFilters
     */
    public function testNotSupportedFilter()
    {
        $dataProvider = new DataProvider(Item::class);

        $dataProvider->setFilters([
            'search' => new FilterExact('name'),
        ]);

        $this->expectException(InvalidQueryException::class);

        $dataProvider->prepare([
            'filter' => [
                'fake' => 'some'
            ],
        ]);
    }

    /**
     * @depends testNormalizeFilters
     */
    public function testFilterInGlobalParamSpace()
    {
        $dataProvider = (new DataProvider(Item::class, [
            'filter' => [
                'keyword' => null,
            ],
        ]))
        ->setFilters([
            'search' => new FilterExact('name'),
        ]);

        $query = $dataProvider->prepare(['search' => 'unexisting-name']);
        $this->assertSame(0, $query->count());

        $query = $dataProvider->prepare(['unexisting' => 'some']); // no exception here
        $this->assertSame(Item::query()->count(), $query->count());
    }

    public function testSetupSort()
    {
        $dataProvider = new DataProvider(Item::class);

        $dataProvider->sort(['id', 'name']);

        $sort = $dataProvider->getSort();

        $this->assertTrue($sort instanceof Sort);

        $this->assertArrayHasKey('id', $sort->getAttributes());
        $this->assertArrayHasKey('name', $sort->getAttributes());
    }

    /**
     * @depends testSetupSort
     */
    public function testSort()
    {
        $items = (new DataProvider(Item::class))
            ->sort(['id', 'name'])
            ->prepare(['sort' => '-id'])
            ->get();

        $this->assertSame(20, $items[0]['id']);
        $this->assertSame(19, $items[1]['id']);
    }

    /**
     * @depends testSort
     */
    public function testGetConfigFromContainer()
    {
        $sortKeyword = 'sort-from-config';

        $this->app->instance('config', new Repository([
            'data_provider' => [
                'sort' => [
                    'keyword' => $sortKeyword,
                ],
            ],
        ]));

        $items = (new DataProvider(Item::class))
            ->sort(['id', 'name'])
            ->prepare([$sortKeyword => '-id'])
            ->get();

        $this->assertSame(20, $items[0]['id']);
    }

    public function testPaginate()
    {
        $items = (new DataProvider(Item::class))
            ->paginate([
                'per-page' => 2,
                'page' => 2,
            ]);

        $this->assertTrue($items instanceof LengthAwarePaginator);
        $this->assertCount(2, $items->items());
    }

    /**
     * @depends testNormalizeFilters
     */
    public function testPreserveSourceState()
    {
        $source = Item::query();

        $preparedSource = (new DataProvider($source))
            ->filters([
                'id'
            ])
            ->prepare([
                'filter' => [
                    'id' => 1,
                ],
            ]);

        $this->assertNotEquals($source->count(), $preparedSource->count());
    }

    public function testGet()
    {
        $items = (new DataProvider(Item::class))->get([]);

        $this->assertTrue($items instanceof Collection);
        $this->assertEquals(Item::query()->count(), $items->count());

        $items = (new DataProvider($this->getConnection()->table('items')))->get([]);
        $this->assertEquals($this->getConnection()->table('items')->count(), $items->count());
    }

    public function testInclude()
    {
        $item = (new DataProvider(Item::class))
            ->includes(['category'])
            ->prepare([
                'include' => 'category',
            ])
            ->first();

        $this->assertTrue($item->relationLoaded('category'));
    }

    /**
     * @depends testPaginate
     */
    public function testStaticNew()
    {
        $items = DataProvider::new(Item::class, [
            'pagination' => [
                'per_page' => [
                    'default' => 2,
                ]
            ],
        ])
            ->paginate([]);

        $this->assertCount(2, $items->items());
        $this->assertTrue($items->items()[0] instanceof Item);
    }
}
