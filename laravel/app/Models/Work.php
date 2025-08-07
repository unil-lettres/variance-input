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

    // `folder` is set automatically via model events
    protected $fillable = [
        'title',
        'short_title',
        'author_id',
        'desc',
        'image_url',
    ];

    /* -----------------------------------------------------------------
     |  Boot: generate unique slug + create directory
     * ---------------------------------------------------------------- */
    protected static function boot()
    {
        parent::boot();

        // before INSERT → generate folder slug
        static::creating(function (self $work) {
            if (empty($work->folder)) {
                $work->folder = makeUniqueSlug($work->title, 'folder', 'works');
            }
        });

        // after INSERT → create /uploads/<author>/<work>/ directory
        static::created(function (self $work) {
            if (!$work->relationLoaded('author')) {
                $work->load('author:id,folder');
            }
            $path = $work->author->folder . '/' . $work->folder;
            Storage::disk('uploads')->makeDirectory($path);
        });
    }

    /* -----------------------------------------------------------------
     |  Relationships
     * ---------------------------------------------------------------- */
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
}
