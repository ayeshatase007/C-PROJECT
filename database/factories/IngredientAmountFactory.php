<?php

namespace Database\Factories;

use App\Models\Food;
use App\Models\IngredientAmount;
use App\Models\Recipe;
use App\Support\Nutrients;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class IngredientAmountFactory extends Factory
{
    /**
     * {@inheritdoc}
     */
    protected $model = IngredientAmount::class;

    /**
     * {@inheritdoc}
     */
    public function definition(): array
    {
        if ($this->faker->boolean(90)) {
            $ingredient_factory = Food::factory();
            $ingredient_type = Food::class;
            $ingredient_unit = Nutrients::units()->pluck('value')->random(1)->first();
        }
        else {
            $ingredient_factory = Recipe::factory();
            $ingredient_type = Recipe::class;
            $ingredient_unit = 'serving';
        }

        return [
            'ingredient_id' => $ingredient_factory,
            'ingredient_type' => $ingredient_type,
            'amount' => $this->faker->randomFloat(1, 1/3, 5),
            'unit' => $ingredient_unit,
            'detail' => $this->faker->optional(0.8)->realText(),
            'weight' => $this->faker->numberBetween(0, 50),
            'parent_id' => Recipe::factory(),
            'parent_type' => Recipe::class,
        ];
    }

    /**
     * Define a specific parent.
     */
    public function parent(Model $parent): static
    {
        return $this->state(function (array $attributes) use ($parent) {
            return [
                'parent_id' => $parent->id,
                'parent_type' => $parent::class,
            ];
        });
    }

    /**
     * Define a specific ingredient.
     */
    public function ingredient(Model $ingredient): static
    {
        return $this->state(function (array $attributes) use ($ingredient) {
            return [
                'ingredient_id' => $ingredient->id,
                'ingredient_type' => $ingredient::class,
            ];
        });
    }
}
