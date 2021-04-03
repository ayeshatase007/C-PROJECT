<?php

namespace Tests\Feature\JsonApi;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TagApiTest extends JsonApiTestCase
{
    use RefreshDatabase;

    /**
     * @inheritdoc
     */
    public function factory(): Factory
    {
        return Tag::factory();
    }

    /**
     * @inheritdoc
     */
    public function resourceName(): string
    {
        return 'tags';
    }

    public function testCanUseNameFilter(): void {
        $names = ['sweet', 'salty', 'spicy'];
        foreach ($names as $name) {
            $this->factory()->create(['name' => $name]);
        }

        foreach ($names as $name) {
            $partial = substr($name, rand(0, 2), 3);
            $search_route = route($this->indexRouteName, [
                'filter' => ['name' => $partial]
            ]);
            $response = $this->get($search_route);
            $response->assertOk();
            $response->assertJsonCount(1, 'data');
            $response->assertJsonFragment(['name' => $name]);
        }
    }

}
