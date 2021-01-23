<div class="flex flex-row space-x-4 mb-4">
    <x-inputs.input type="hidden" name="ingredients[original_key][]" :value="$original_key ?? null" />
    <x-inputs.input type="text"
                    name="ingredients[amount][]"
                    size="5"
                    :value="$amount ?? null" />
    <x-inputs.select name="ingredients[unit][]"
                     :options="$ingredients_units"
                     :selectedValue="$unit ?? null">
        <option value=""></option>
    </x-inputs.select>
    <x-ingredient-picker :default-id="$ingredient_id ?? null"
                         :default-name="$ingredient_name ?? null" />
    <x-inputs.input type="text"
                    class="block"
                    name="ingredients[detail][]"
                    :value="$detail ?? null" />
    <x-inputs.icon-button type="button" color="red" x-on:click="$event.target.parentNode.remove();">
        <svg class="h-8 w-8 pointer-events-none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
    </x-inputs.icon-button>
</div>
