<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Author extends Model
{
    use HasFactory;

    /** Mass‑assignable; `folder` is set automatically via events */
    protected $fillable = ['name', 'folder', 'order', 'is_legacy'];

    protected $casts = [
        'is_legacy' => 'boolean',
    ];

    /* ---------------------------------------------------------------------
     |  Boot callbacks:     slug ▸ mkdir ▸ rmdir on delete                  |
     * -------------------------------------------------------------------*/
    protected static function boot()
    {
        parent::boot();

        /** 1️⃣  Before INSERT – generate unique slug for `folder` */
        static::creating(function (self $author) {
            if (empty($author->folder)) {
                $author->folder = makeUniqueSlug($author->name, 'folder', 'authors');
            }
        });

        /** 2️⃣  After INSERT – create uploads/<author>/ directory */
        static::created(function (self $author) {
            Storage::disk('uploads')->makeDirectory($author->folder);
        });

        /** 3️⃣  After DELETE – remove that directory (works are already gone) */
        static::deleted(function (self $author) {
            Storage::disk('uploads')->deleteDirectory($author->folder);
        });
    }

    /* -----------------------------------------------------------------
     |  Relationships                                                   |
     * ----------------------------------------------------------------*/
    public function works()
    {
        return $this->hasMany(Work::class);
    }

    public function status()
    {
        return $this->hasOne(WorkStatus::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
