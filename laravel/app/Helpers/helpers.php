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

if (! function_exists('admin_base_prefix')) {
    /**
     * Return the admin base path prefix with a leading slash (e.g. "/admin" or "").
     */
    function admin_base_prefix(): string
    {
        $base = trim(config('app.admin_base_path'), '/');

        return $base === '' ? '' : '/' . $base;
    }
}

if (! function_exists('admin_path')) {
    /**
     * Build the public path (including the admin prefix) for the given segment.
     */
    function admin_path(string $path = ''): string
    {
        $prefix = admin_base_prefix();
        $clean = trim($path, '/');

        if ($prefix === '') {
            return $clean === '' ? '/' : '/' . $clean;
        }

        if ($clean === '') {
            return $prefix;
        }

        return rtrim($prefix, '/') . '/' . $clean;
    }
}

if (! function_exists('admin_url')) {
    /**
     * Build a fully-qualified URL scoped to the admin base path.
     */
    function admin_url(string $path = ''): string
    {
        $relative = admin_path($path);

        return url($relative);
    }
}

if (! function_exists('admin_asset')) {
    /**
     * Build a public asset URL that includes the admin prefix.
     */
    function admin_asset(string $path): string
    {
        $clean = ltrim($path, '/');
        $base = admin_path();

        return url(rtrim($base, '/') . '/' . $clean);
    }
}

if (! function_exists('legacy_url')) {
    /**
     * Build a URL pointing to the public (legacy) site root or a subpath.
     */
    function legacy_url(string $path = ''): string
    {
        $root = rtrim(config('app.url'), '/');
        $clean = trim($path, '/');

        if ($clean === '') {
            return $root . '/';
        }

        return $root . '/' . $clean;
    }
}
