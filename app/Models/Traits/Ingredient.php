<?php

namespace App\Models\Traits;

use Illuminate\Support\Collection;

trait Ingredient
{
    /**
     * Gets search results for a term.
     */
    public static function search(string $term, int $limit = 10): Collection {
        return (new static)::where('name', 'like', "%{$term}%")->limit($limit)->get();
    }
}
