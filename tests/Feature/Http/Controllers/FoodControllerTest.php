<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\FoodController;
use App\Models\Food;
use Database\Factories\FoodFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FoodControllerTest extends HttpControllerTestCase
{
    use RefreshDatabase;

    /**
     * @inheritdoc
     */
    public function class(): string
    {
        return FoodController::class;
    }

    /**
     * @inheritdoc
     */
    public function factory(): FoodFactory
    {
        return Food::factory();
    }

    /**
     * @inheritdoc
     */
    public function routeKey(): string
    {
        return 'food';
    }

    public function testCanAddFoodWithoutNutrients(): void
    {
        /** @var \App\Models\Food $food */
        $food = $this->factory()->make([
            'calories' => NULL,
            'fat' => NULL,
            'cholesterol' => NULL,
            'sodium' => NULL,
            'carbohydrates' => NULL,
            'protein' => NULL,
        ]);
        $store_url = action([FoodController::class, 'store']);
        $response = $this->followingRedirects()->post($store_url, $food->toArray());
        $response->assertOk();
        $response->assertSee("Food {$food->name} updated!");
    }

}
