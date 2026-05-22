<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Work extends Model
{
    use HasFactory;

    /**
     * Mass‑assignable columns. `folder` is set automatically in model events.
     */
    protected $fillable = [
        'title',
        'short_title',
        'catalog_group',
        'author_id',
        'desc',
        'image_url',
        'pdf_url',
        'folder',
        'is_legacy',
    ];

    protected $casts = [
        'is_legacy' => 'boolean',
    ];

    /* ---------------------------------------------------------------------
     |  Boot callbacks:        create slug ▸ mkdir ▸ rmdir on delete        |
     * -------------------------------------------------------------------*/
    protected static function boot()
    {
        parent::boot();

        /** 1️⃣  Before INSERT – generate unique folder slug */
        static::creating(function (self $work) {
            if (empty($work->folder)) {
                $work->folder = makeUniqueSlug($work->title, 'folder', 'works');
            }
        });

        /** 2️⃣  After INSERT – create uploads/<author>/<work>/ directory */
        static::created(function (self $work) {
            $work->loadMissing('author:id,folder');
            $path = $work->author->folder . '/' . $work->folder;
            Storage::disk('uploads')->makeDirectory($path);
        });

        /** 3️⃣  After DELETE – remove that directory */
        static::deleted(function (self $work) {
            $work->loadMissing('author:id,folder');
            $path = $work->author->folder . '/' . ($work->folder ?? '');
            Storage::disk('uploads')->deleteDirectory($path);
        });
    }

    /* -----------------------------------------------------------------
     |  Relationships                                                  |
     * ----------------------------------------------------------------*/
    public function workStatus(): HasOne
    {
        return $this->hasOne(WorkStatus::class, 'work_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
