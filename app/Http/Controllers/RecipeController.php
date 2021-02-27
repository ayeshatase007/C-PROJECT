<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\IngredientAmount;
use App\Models\Recipe;
use App\Models\RecipeStep;
use App\Rules\ArrayNotEmpty;
use App\Rules\StringIsDecimalOrFraction;
use App\Rules\UsesIngredientTrait;
use App\Support\Number;
use App\Support\Nutrients;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecipeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('recipes.index')->with('tags', Recipe::getTagTotals());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return $this->edit(new Recipe());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Throwable
     */
    public function store(Request $request): RedirectResponse
    {
        return $this->update($request, new Recipe());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Recipe  $recipe
     * @return \Illuminate\Contracts\View\View
     */
    public function show(Recipe $recipe): View
    {
        return view('recipes.show')->with('recipe', $recipe);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Recipe $recipe
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(Recipe $recipe): View
    {
        // Pre-populate relationships from form data or current recipe.
        $ingredients = [];
        if ($old = old('ingredients')) {
            foreach ($old['id'] as $key => $ingredient_id) {
                if (empty($ingredient_id)) {
                    continue;
                }
                $ingredients[] = [
                    'original_key' => $old['original_key'][$key],
                    'amount' => $old['amount'][$key],
                    'unit' => $old['unit'][$key],
                    'ingredient_id' => $ingredient_id,
                    'ingredient_type' => $old['type'][$key],
                    'ingredient_name' => $old['name'][$key],
                    'detail' => $old['detail'][$key],
                ];
            }
        }
        else {
            foreach ($recipe->ingredientAmounts as $key => $ingredientAmount) {
                $ingredients[] = [
                    'original_key' => $key,
                    'amount' => $ingredientAmount->amount_formatted,
                    'unit' => $ingredientAmount->unit,
                    'ingredient_id' => $ingredientAmount->ingredient_id,
                    'ingredient_type' => $ingredientAmount->ingredient_type,
                    'ingredient_name' => $ingredientAmount->ingredient->name,
                    'detail' => $ingredientAmount->detail,
                ];
            }
        }

        $steps = [];
        if ($old = old('steps')) {
            foreach ($old['step'] as $key => $step) {
                if (empty($step)) {
                    continue;
                }
                $steps[] = [
                    'original_key' => $old['original_key'][$key],
                    'step_default' => $step,
                ];
            }
        }
        else {
            foreach ($recipe->steps as $key => $step) {
                $steps[] = [
                    'original_key' => $key,
                    'step_default' => $step->step,
                ];
            }
        }

        // Convert string tags (from old form data) to a Collection.
        $recipe_tags = old('tags', $recipe->tags->pluck('name'));
        if (is_string($recipe_tags)) {
            $recipe_tags = new Collection(explode(',', $recipe_tags));
        }

        return view('recipes.edit')
            ->with('recipe', $recipe)
            ->with('recipe_tags', $recipe_tags)
            ->with('ingredients', $ingredients)
            ->with('steps', $steps)
            ->with('ingredients_units', Nutrients::$units);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Recipe $recipe
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Throwable
     */
    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $input = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'source' => 'nullable|string',
            'servings' => 'required|numeric',
            'weight' => 'nullable|numeric',
            'ingredients.amount' => ['required', 'array', new ArrayNotEmpty],
            'ingredients.amount.*' => ['required_with:ingredients.id.*', 'nullable', new StringIsDecimalOrFraction],
            'ingredients.unit' => ['required', 'array'],
            'ingredients.unit.*' => ['required_with:ingredients.id.*'],
            'ingredients.detail' => ['required', 'array'],
            'ingredients.detail.*' => 'nullable|string',
            'ingredients.id' => ['required', 'array', new ArrayNotEmpty],
            'ingredients.id.*' => 'required_with:ingredients.amount.*|nullable',
            'ingredients.type' => ['required', 'array', new ArrayNotEmpty],
            'ingredients.type.*' => ['required_with:ingredients.id.*', 'nullable', new UsesIngredientTrait()],
            'ingredients.original_key' => 'nullable|array',
            'steps.step' => ['required', 'array', new ArrayNotEmpty],
            'steps.step.*' => 'nullable|string',
            'steps.original_key' => 'nullable|array',
        ]);

        // Validate that no ingredients are recursive.
        // TODO: refactor as custom validator.
        foreach (array_filter($input['ingredients']['id']) as $key => $id) {
            if ($input['ingredients']['type'][$key] == Recipe::class && $id == $recipe->id) {
                return back()->withInput()->withErrors('To understand recursion, you must understand recursion. Remove this recipe from this recipe.');
            }
        }

        $recipe->fill([
            'name' => Str::lower($input['name']),
            'description' => $input['description'],
            'source' => $input['source'],
            'servings' => (int) $input['servings'],
            'weight' => $input['weight'],
        ]);

        try {
            DB::transaction(function () use ($recipe, $input) {
                $recipe->saveOrFail();

                // Delete any removed ingredients.
                $removed = array_diff($recipe->ingredientAmounts->keys()->all(), $input['ingredients']['original_key']);
                foreach ($removed as $removed_key) {
                    $recipe->ingredientAmounts[$removed_key]->delete();
                }

                // Add/update current ingredients.
                $ingredient_amounts = [];
                $weight = 0;
                foreach (array_filter($input['ingredients']['id']) as $key => $ingredient_id) {
                    if (!is_null($input['ingredients']['original_key'][$key])) {
                        $ingredient_amounts[$key] = $recipe->ingredientAmounts[$input['ingredients']['original_key'][$key]];
                    }
                    else {
                        $ingredient_amounts[$key] = new IngredientAmount();
                    }
                    $ingredient_amounts[$key]->fill([
                        'amount' => Number::floatFromString($input['ingredients']['amount'][$key]),
                        'unit' => $input['ingredients']['unit'][$key],
                        'detail' => $input['ingredients']['detail'][$key],
                        'weight' => $weight++,
                    ]);
                    $ingredient_amounts[$key]->ingredient()
                        ->associate($input['ingredients']['type'][$key]::where('id', $ingredient_id)->first());
                }
                $recipe->ingredientAmounts()->saveMany($ingredient_amounts);

                $steps = [];
                $number = 1;

                // Delete any removed steps.
                $removed = array_diff($recipe->steps->keys()->all(), $input['steps']['original_key']);
                foreach ($removed as $removed_key) {
                    $recipe->steps[$removed_key]->delete();
                }

                foreach (array_filter($input['steps']['step']) as $key => $step) {
                    if (!is_null($input['steps']['original_key'][$key])) {
                        $steps[$key] = $recipe->steps[$input['steps']['original_key'][$key]];
                    }
                    else {
                        $steps[$key] = new RecipeStep();
                    }
                    $steps[$key]->fill(['number' => $number++, 'step' => $step]);
                }
                $recipe->steps()->saveMany($steps);
            });
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors($e->getMessage());
        }

        // Sync tags.
        if ($tags = $request->get('tags')) {
            $recipe->syncTags(explode(',', $tags));
        }

        session()->flash('message', "Recipe {$recipe->name} updated!");
        return redirect()->route('recipes.show', $recipe);
    }

    /**
     * Confirm removal of specified resource.
     */
    public function delete(Recipe $recipe): View
    {
        return view('recipes.delete')->with('recipe', $recipe);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        // Remove the recipe from any recipes.
        foreach ($recipe->ingredientAmountRelationships as $ia) {
            $ia->delete();
        }
        $recipe->delete();
        return redirect(route('recipes.index'))
            ->with('message', "Recipe {$recipe->name} deleted!");
    }

}
