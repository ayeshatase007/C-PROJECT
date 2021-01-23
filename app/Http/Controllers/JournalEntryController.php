<?php
/**
 * @noinspection PhpDocSignatureInspection
 */

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\JournalEntry;
use App\Models\Recipe;
use App\Rules\ArrayNotEmpty;
use App\Rules\StringIsDecimalOrFraction;
use App\Support\Number;
use App\Support\Nutrients;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class JournalEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $date = $request->date ?? Carbon::now()->toDateString();
        return view('journal-entries.index')
            ->with('entries', JournalEntry::where([
                'user_id' => Auth::user()->id,
                'date' => $date,
            ])->get())
            ->with('date', Carbon::createFromFormat('Y-m-d', $date))
            ->with('nutrients', ['calories', 'fat', 'cholesterol', 'carbohydrates', 'sodium', 'protein']);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $foods = Food::all(['id', 'name', 'detail', 'brand'])
            ->sortBy('name')
            ->collect()
            ->map(function ($food) {
                return [
                    'value' => $food->id,
                    'label' => "{$food->name}"
                        . ($food->detail ? ", {$food->detail}" : "")
                        . ($food->brand ? " ({$food->brand})" : ""),
                ];
            });
        $recipes = Recipe::all(['id', 'name'])
            ->sortBy('name')
            ->collect()
            ->map(function ($recipe) {
                return ['value' => $recipe->id, 'label' => $recipe->name];
            });

        $items = [];
        if ($old = old('items')) {
            foreach ($old['amount'] as $key => $amount) {
                if (empty($amount) && empty($old['unit'][$key]) && empty($old['food'][$key]) && empty($old['recipe'][$key])) {
                    continue;
                }
                $items[] = [
                    'amount' => $amount,
                    'unit' => $old['unit'][$key],
                    'food' => $old['food'][$key],
                    'recipe' => $old['recipe'][$key],
                ];
            }
        }

        return view('journal-entries.create')
            ->with('items', $items)
            ->with('foods', $foods)
            ->with('recipes', $recipes)
            ->with('meals', [
                ['value' => 'breakfast', 'label' => 'Breakfast'],
                ['value' => 'lunch', 'label' => 'Lunch'],
                ['value' => 'dinner', 'label' => 'Dinner'],
                ['value' => 'snacks', 'label' => 'Snacks'],
            ])
            ->with('units', [
                ['value' => 'tsp', 'label' => 'tsp.'],
                ['value' => 'tbsp', 'label' => 'tbsp.'],
                ['value' => 'cup', 'label' => 'cup'],
                ['value' => 'oz', 'label' => 'oz'],
                ['value' => 'servings', 'label' => 'servings'],
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'date' => 'required|date',
            'meal' => 'required|string',
            'items.amount' => ['required', 'array', new ArrayNotEmpty],
            'items.amount.*' => ['required_with:foods.*,recipes.*', 'nullable', new StringIsDecimalOrFraction],
            'items.unit' => 'required|array',
            'items.unit.*' => 'nullable|string',
            'items.food' => 'required|array',
            'items.food.*' => 'nullable|exists:App\Models\Food,id',
            'items.recipe' => 'required|array',
            'items.recipe.*' => 'nullable|exists:App\Models\Recipe,id',
        ]);

        // Validate that at least one recipe or food is selected.
        // TODO: refactor as custom validator.
        $foods_selected = array_filter($input['items']['food']);
        $recipes_selected = array_filter($input['items']['recipe']);
        if (empty($recipes_selected) && empty($foods_selected)) {
            return back()->withInput()->withErrors('At least one food or recipe is required.');
        }
        elseif (!empty(array_intersect_key($foods_selected, $recipes_selected))) {
            return back()->withInput()->withErrors('Select only one food or recipe per line.');
        }

        // Validate only "serving" unit used for recipes.
        // TODO: refactor as custom validator.
        foreach ($recipes_selected as $key => $id) {
            if ($input['items']['unit'][$key] !== 'servings') {
                return back()->withInput()->withErrors('Recipes must use the "servings" unit.');
            }
        }

        $summary = [];
        $nutrients = array_fill_keys(Nutrients::$all, 0);

        if (!empty($foods_selected)) {
            $foods = Food::findMany($foods_selected)->keyBy('id');
            foreach ($foods_selected as $key => $id) {
                $food = $foods->get($id);
                $nutrient_multiplier = Nutrients::calculateFoodNutrientMultiplier(
                    $food,
                    Number::floatFromString($input['items']['amount'][$key]),
                    $input['items']['unit'][$key],
                );
                foreach ($nutrients as $nutrient => $amount) {
                    $nutrients[$nutrient] += $food->{$nutrient} * $nutrient_multiplier;
                }
                $summary[] = "{$input['items']['amount'][$key]} {$input['items']['unit'][$key]} {$food->name}";
            }
        }

        if (!empty($recipes_selected)) {
            $recipes = Recipe::findMany($recipes_selected)->keyBy('id');
            foreach ($recipes_selected as $key => $id) {
                $recipe = $recipes->get($id);
                foreach ($nutrients as $nutrient => $amount) {
                    $nutrients[$nutrient] += $recipe->{"{$nutrient}PerServing"}() * Number::floatFromString($input['items']['amount'][$key]);
                }
                $summary[] = "{$input['items']['amount'][$key]} {$input['items']['unit'][$key]} {$recipe->name}";
            }
        }

        $entry = new JournalEntry([
            'summary' => implode(', ', $summary),
            'date' => $input['date'],
            'meal' => $input['meal'],
        ] + $nutrients);
        $entry->user()->associate(Auth::user());
        if ($entry->save()) {
            if (isset($foods)) {
                $entry->foods()->saveMany($foods);
            }
            if (isset($recipes)) {
                $entry->recipes()->saveMany($recipes);
            }
        }

        session()->flash('message', "Journal entry added!");
        return redirect()->route(
            'journal-entries.index',
            ['date' => $entry->date->format('Y-m-d')]
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\JournalEntry  $journalEntry
     * @return \Illuminate\Http\Response
     */
    public function show(JournalEntry $journalEntry)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\JournalEntry  $journalEntry
     * @return \Illuminate\Http\Response
     */
    public function edit(JournalEntry $journalEntry)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\JournalEntry  $journalEntry
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, JournalEntry $journalEntry)
    {
        //
    }

    /**
     * Confirm removal of the specified resource.
     */
    public function delete(JournalEntry $journalEntry): View
    {
        return view('journal-entries.delete')->with('entry', $journalEntry);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $journalEntry->delete();
        session()->flash('message', 'Journal entry deleted!');
        return redirect(route('journal-entries.index', [
            'date' => $journalEntry->date->toDateString()
        ]));
    }
}
