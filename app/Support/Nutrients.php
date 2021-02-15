<?php

namespace App\Support;

use App\Models\Food;
use App\Models\Recipe;

class Nutrients
{
    public static float $gramsPerOunce = 28.349523125;

    public static array $all = [
        ['value' => 'calories', 'label' => 'calories', 'unit' => null],
        ['value' => 'carbohydrates', 'label' => 'carbohydrates', 'unit' => 'g'],
        ['value' => 'cholesterol', 'label' => 'cholesterol', 'unit' => 'mg'],
        ['value' => 'fat', 'label' => 'fat', 'unit' => 'g'],
        ['value' => 'protein', 'label' => 'protein', 'unit' => 'g'],
        ['value' => 'sodium', 'label' => 'sodium', 'unit' => 'mg'],
    ];

    public static array $units = [
        ['value' => 'cup', 'label' => 'cup'],
        ['value' => 'gram', 'label' => 'grams'],
        ['value' => 'oz', 'label' => 'oz'],
        ['value' => 'serving', 'label' => 'servings'],
        ['value' => 'tbsp', 'label' => 'tbsp.'],
        ['value' => 'tsp', 'label' => 'tsp.'],
    ];

    /**
     * Calculate a nutrient multiplier for a Food.
     *
     * @param \App\Models\Food $food
     * @param float $amount
     * @param string|null $fromUnit
     *
     * @return float
     */
    public static function calculateFoodNutrientMultiplier(
        Food $food,
        float $amount,
        string|null $fromUnit
    ): float {
        if ($fromUnit === 'oz') {
            return $amount * self::$gramsPerOunce / $food->serving_weight;
        }
        elseif ($fromUnit === 'serving') {
            return $amount;
        }
        elseif ($fromUnit === 'gram') {
            return $amount / $food->serving_weight;
        }

        if (
            empty($fromUnit)
            || empty($food->serving_unit)
            || $food->serving_unit === $fromUnit
        ) {
            $multiplier = 1;
        }
        elseif ($fromUnit === 'tsp') {
            $multiplier = match ($food->serving_unit) {
                'tbsp' => 1/3,
                'cup' => 1/48,
                default => throw new \DomainException(),
            };
        }
        elseif ($fromUnit === 'tbsp') {
            $multiplier = match ($food->serving_unit) {
                'tsp' => 3,
                'cup' => 1/16,
                default => throw new \DomainException(),
            };
        }
        elseif ($fromUnit === 'cup') {
            $multiplier = match ($food->serving_unit) {
                'tsp' => 48,
                'tbsp' => 16,
                default => throw new \DomainException(),
            };
        }
        else {
            throw new \DomainException("Unhandled unit combination: {$fromUnit}, {$food->serving_unit} ({$food->name})");
        }

        return $multiplier / $food->serving_size * $amount;
    }

    /**
     * Calculate a nutrient amount for a recipe.
     *
     * @param \App\Models\Recipe $recipe
     * @param string $nutrient
     * @param float $amount
     * @param string $fromUnit
     *
     * @return float
     */
    public static function calculateRecipeNutrientAmount(
        Recipe $recipe,
        string $nutrient,
        float $amount,
        string $fromUnit
    ): float {
        if ($fromUnit === 'oz') {
            return $amount * self::$gramsPerOunce / $recipe->weight * $recipe->{"{$nutrient}Total"}();
        }
        elseif ($fromUnit === 'serving') {
            return $recipe->{"{$nutrient}PerServing"}() * $amount;
        }
        elseif ($fromUnit === 'gram') {
            return $amount / $recipe->weight * $recipe->{"{$nutrient}Total"}();
        }
        else {
            throw new \DomainException("Unsupported recipe unit: {$fromUnit}");
        }
    }
}
