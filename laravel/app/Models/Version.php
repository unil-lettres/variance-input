<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Version extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'folder',
        'work_id',
        'pagination_done',
        'pagination_done_at',
        'pagination_done_by',
        'ignored_pages',
    ];

    protected $casts = [
        'pagination_done'    => 'boolean',
        'pagination_done_at' => 'datetime',
        'ignored_pages'      => 'collection',
    ];

    /**
     * Relationship: Work of the Version
     *
     * Defines a `belongsTo` relationship to the `Work` model, linking this
     * version to a specific work.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function work()
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * Relationship: Comparisons where this is the Source Version
     *
     * Defines a `hasMany` relationship to the `Comparison` model for cases
     * where this version is used as the source.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comparisonsAsSource()
    {
        return $this->hasMany(Comparison::class, 'source_id');
    }

    /**
     * Relationship: Comparisons where this is the Target Version
     *
     * Defines a `hasMany` relationship to the `Comparison` model for cases
     * where this version is used as the target.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comparisonsAsTarget()
    {
        return $this->hasMany(Comparison::class, 'target_id');
    }

    /**
     * Relationship: Status of the Version
     *
     * Defines a `hasOne` relationship to the `VersionStatus` model, allowing
     * access to the status record for this version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function status()
    {
        return $this->hasOne(VersionStatus::class);
    }

    public function paginationDoneBy()
    {
        return $this->belongsTo(User::class, 'pagination_done_by');
    }

    public function getXMLFilePath()
    {
        return storage_path("app/public/uploads/versions/{$this->folder}.xml");
    }
    
    public function getFileSizeAttribute()
{
    $relative = str_replace('storage/', '', $this->folder);
    return Storage::disk('public')->size($relative) ?? 0;
}

    public function getFileSizeFormattedAttribute()
    {
        $size = $this->file_size;
        if ($size >= 1073741824) {
            return round($size / 1073741824, 2) . ' Go';
        } elseif ($size >= 1048576) {
            return round($size / 1048576, 2) . ' Mo';
        } elseif ($size >= 1024) {
            return round($size / 1024, 2) . ' Ko';
        } else {
            return $size . ' octets';
        }
    }

    public function collectManifestEntries(): array
    {
        $disk = Storage::disk('public');
        $prefix = "uploads/{$this->work->author->folder}/{$this->work->folder}/{$this->folder}";
        if (!$disk->exists($prefix)) {
            return [];
        }

        $files = collect($disk->files($prefix))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => preg_match('/\.(jpe?g|png)$/i', $name))
            ->reject(fn ($name) => str_contains(strtolower($name), '_thumb'))
            ->sort(fn ($a, $b) => strnatcasecmp($a, $b))
            ->values();

        return $files->map(function ($file) use ($disk, $prefix) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $ext  = pathinfo($file, PATHINFO_EXTENSION);
            $thumbName = $base . '_thumb.' . $ext;

            $big   = "/uploads/{$this->work->author->folder}/{$this->work->folder}/{$this->folder}/{$file}";
            $small = $disk->exists("{$prefix}/{$thumbName}")
                ? "/uploads/{$this->work->author->folder}/{$this->work->folder}/{$this->folder}/{$thumbName}"
                : $big;

            return [
                'small' => $small,
                'big'   => $big,
            ];
        })->toArray();
    }

    /**
     * Get the list of ignored page filenames.
     *
     * @return Collection<string>
     */
    public function getIgnoredPages(): Collection
    {
        return $this->ignored_pages ?? collect([]);
    }

    /**
     * Toggle the ignored status of a page (by filename).
     *
     * @param string $filename The filename of the image to toggle
     * @return bool The new ignored status (true = ignored, false = not ignored)
     */
    public function toggleIgnoredPage(string $filename): bool
    {
        $ignoredPages = $this->getIgnoredPages();
        $wasIgnored = $ignoredPages->contains($filename);

        $this->ignored_pages = $wasIgnored
            ? $ignoredPages->reject(fn ($p) => $p === $filename)->values()->all()
            : $ignoredPages->push($filename)->all();
        $this->save();

        return !$wasIgnored;
    }
}
