<x-app-layout>
    @php($title = ($recipe->exists ? "Edit {$recipe->name}" : 'Add Recipe'))
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ $title }}</h1>
    </x-slot>
    <form x-data x-ref="root" method="POST" enctype="multipart/form-data" action="{{ ($recipe->exists ? route('recipes.update', $recipe) : route('recipes.store')) }}">
        @if ($recipe->exists)@method('put')@endif
        @csrf
        <div class="flex flex-col space-y-4 md:flex-row md:space-x-4 md:space-y-0">
            <!-- Name -->
            <div class="flex-auto">
                <x-inputs.label for="name" value="Name" />

                <x-inputs.input name="name"
                                type="text"
                                class="block mt-1 w-full"
                                :value="old('name', $recipe->name)"
                                required />
            </div>
        </div>
        <div class="flex flex-col space-y-4 mt-4 md:flex-row md:space-x-4 md:space-y-0">
            <!-- Servings -->
            <div class="flex-auto">
                <x-inputs.label for="servings" value="Servings" />

                <x-inputs.input name="servings"
                                type="number"
                                class="block mt-1 w-full"
                                :value="old('servings', $recipe->servings)"
                                required />
            </div>

            <!-- Weight -->
            <div class="flex-auto">
                <x-inputs.label for="weight" value="Weight (g)" />

                <x-inputs.input name="weight"
                                type="number"
                                step="any"
                                class="block mt-1 w-full"
                                :value="old('weight', $recipe->weight)" />
            </div>

            <!-- Volume -->
            <div class="flex-auto">
                <x-inputs.label for="volume" value="Volume (cups)" />

                <x-inputs.input name="volume"
                                type="text"
                                class="block mt-1 w-full"
                                :value="old('volume', $recipe->volume_formatted)" />
            </div>

            <!-- Prep Time -->
            <div class="flex-auto">
                <x-inputs.label for="time_prep" value="Prep time (min.)" />

                <x-inputs.input name="time_prep"
                                type="number"
                                step="1"
                                min="0"
                                class="block mt-1 w-full"
                                :value="old('time_prep', $recipe->time_prep)"/>
            </div>

            <!-- Cooke Time -->
            <div class="flex-auto">
                <x-inputs.label for="time_cook" value="Cook time (min.)" />

                <x-inputs.input name="time_cook"
                                type="number"
                                step="1"
                                min="0"
                                class="block mt-1 w-full"
                                :value="old('time_cook', $recipe->time_cook)"/>
            </div>
        </div>
        <div class="flex flex-col space-y-4 mt-4">
            <!-- Image -->
            <div class="flex flex-col space-y-4 md:flex-row md:space-x-4 md:space-y-0">
                <x-inputs.image :model="$recipe" />
            </div>

            <!-- Description -->
            <div>
                <x-inputs.label for="description" value="Description" />

                <x-inputs.input name="description"
                                type="hidden"
                                :value="old('description', $recipe->description)" />

                <div class="quill-editor text-lg"></div>

                <x-inputs.input name="description_delta"
                                type="hidden"
                                :value="old('description_delta', $recipe->description_delta)" />
            </div>

            <!-- Source -->
            <div>
                <x-inputs.label for="source" value="Source" />

                <x-inputs.input name="source"
                                type="text"
                                class="block mt-1 w-full"
                                inputmode="url"
                                :value="old('source', $recipe->source)" />
            </div>

            <!-- Tags -->
            <x-tagger :defaultTags="$recipe_tags"/>
        </div>

        <!-- Ingredients -->
        <h3 class="mt-6 mb-2 font-extrabold text-lg">Ingredients</h3>
        <div x-data x-ref="ingredients" class="ingredients space-y-4">
            @forelse($ingredients_list->sortBy('weight') as $item)
                @if($item['type'] === 'ingredient')
                    @include('recipes.partials.ingredient-input', $item)
                @elseif($item['type'] === 'separator')
                    @include('recipes.partials.separator-input', $item)
                @endif
            @empty
                @include('recipes.partials.ingredient-input', ['weight' => 0])
            @endforelse
            <div class="templates hidden">
                <div class="ingredient-template">
                    @include('recipes.partials.ingredient-input')
                </div>
                <div class="separator-template">
                    @include('recipes.partials.separator-input')
                </div>
            </div>
            <x-inputs.button type="button"
                             class="bg-emerald-800 hover:bg-emerald-700 active:bg-emerald-900 focus:border-emerald-900 ring-emerald-300"
                             x-on:click="addNodeFromTemplate($refs.ingredients, 'ingredient');">
                Add Ingredient
            </x-inputs.button>
            <x-inputs.button type="button"
                             class="bg-blue-800 hover:bg-blue-700 active:bg-blue-900 focus:border-blue-900 ring-blue-300"
                             x-on:click="addNodeFromTemplate($refs.ingredients, 'separator');">
                Add Separator
            </x-inputs.button>
        </div>

        <!-- Steps -->
        <h3 class="mt-6 mb-2 font-extrabold text-lg">Steps</h3>
        <div x-data x-ref="steps" class="steps">
            @forelse($steps as $step)
                @include('recipes.partials.step-input', $step)
            @empty
                @include('recipes.partials.step-input')
            @endforelse
            <div class="templates hidden">
                <div class="step-template">
                    @include('recipes.partials.step-input')
                </div>
            </div>
            <x-inputs.button type="button"
                             class="bg-emerald-800 hover:bg-emerald-700 active:bg-emerald-900 focus:border-emerald-900 ring-emerald-300"
                             x-on:click="addNodeFromTemplate($refs.steps, 'step');">
                Add Step
            </x-inputs.button>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-inputs.button x-on:click="prepareForm($refs.root);" class="ml-3">
                {{ ($recipe->exists ? 'Save' : 'Add') }}
            </x-inputs.button>
        </div>
    </form>
    @if(config('scout.driver') === 'algolia')
        <x-search-by-algolia />
    @endif

    @once
        @push('styles')
            <link rel="stylesheet" href="{{ asset('css/recipes/edit.css') }}">
        @endpush
    @endonce

    @once
        @push('scripts')
            <script src="{{ asset('js/draggable.js') }}"></script>
            <script src="{{ asset('js/quill.js') }}"></script>
            <script type="text/javascript">

                // Enforce inline (style-base) alignment.
                const AlignStyle = Quill.default.import('attributors/style/align');
                Quill.default.register(AlignStyle, true);

                // Activate Quill editor.
                const description = new Quill.default('.quill-editor', {
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, 4, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'script': 'sub'}, { 'script': 'super' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            ['blockquote', 'code-block'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'align': [] }],
                            ['clean']
                        ]
                    },
                    theme: 'snow'
                });
                try {
                    description.setContents(JSON.parse(document.querySelector('input[name="description_delta"]').value));
                } catch (e) {
                    console.error(e)
                }

                // Activate ingredient sortable.
                const ingredientsSortable = new Draggable.Sortable(document.querySelector('.ingredients'), {
                    draggable: '.draggable',
                    handle: '.draggable-handle',
                    mirror: {
                        appendTo: '.ingredients',
                        constrainDimensions: true,
                    },
                })

                // Recalculate weight (order) of all ingredients.
                ingredientsSortable.on('drag:stopped', (e) => {
                    Array.from(e.sourceContainer.children)
                        .filter(el => el.classList.contains('draggable'))
                        .forEach((el, index) => {
                            el.querySelector('input[name$="[weight][]"]').value = index;
                        });
                })

                // Activate step draggables.
                new Draggable.Sortable(document.querySelector('.steps'), {
                    draggable: '.draggable',
                    handle: '.draggable-handle',
                    mirror: {
                        appendTo: '.steps',
                        constrainDimensions: true,
                    },
                })
            </script>
            <script type="text/javascript">
                /**
                 * Adds a node to ingredients or steps based on a template.
                 *
                 * @param {object} $el Parent element.
                 * @param {string} type Template type -- "ingredient", "separator", or "step".
                 */
                let addNodeFromTemplate = ($el, type) => {
                    // Create clone of relevant template.
                    const templates = $el.querySelector(`:scope .templates`);
                    const template = templates.querySelector(`:scope .${type}-template`);
                    const newNode = template.cloneNode(true).firstElementChild;

                    // Set weight based on previous sibling.
                    const weightField = newNode.querySelector('input[name$="[weight][]"]');
                    if (templates.previousElementSibling) {
                        const lastWeight = templates.previousElementSibling.querySelector('input[name$="[weight][]"]');
                        if (lastWeight && lastWeight.value) {
                            weightField.value = Number.parseInt(lastWeight.value) + 1;
                        }
                    }
                    else {
                        weightField.value = 0;
                    }


                    // Insert new node before templates.
                    $el.insertBefore(newNode, templates);
                }

                /**
                 * Prepare form values for submit.
                 *
                 * @param {object} $el Form element.
                 */
                let prepareForm = ($el) => {
                    // Remove any hidden templates before form submit.
                    $el.querySelectorAll(':scope .templates').forEach(e => e.remove());

                    // Add description values to hidden fields.
                    $el.querySelector('input[name="description_delta"]').value = JSON.stringify(description.getContents());
                    $el.querySelector('input[name="description"]').value = description.root.innerHTML
                        // Remove extraneous spaces from rendered result.
                        .replaceAll('<p><br></p>', '');
                }
            </script>
        @endpush
    @endonce
</x-app-layout>
