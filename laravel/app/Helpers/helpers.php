<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

if (! function_exists('makeUniqueSlug')) {
    /**
     * Return a filesystem-safe, unique slug for a given table / column.
     */
    function makeUniqueSlug(string $base, string $column, string $table): string
    {
        $slug     = Str::slug($base, '_');   // «Le Crime…» → le_crime_de_sylvestre_bonnard
        $original = $slug;
        $i        = 1;

        while (DB::table($table)->where($column, $slug)->exists()) {
            $slug = $original . '_' . ++$i;  // …_2, …_3, …
        }

        return $slug;
    }
}
