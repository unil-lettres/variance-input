<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;   // ⬅ import for directory creation

class Author extends Model
{
    use HasFactory;

    protected $fillable = ['name'];        // ‘folder’ is now filled automatically

    /* -----------------------------------------------------------------
     |  Boot: fill `folder` + create directory
     * ---------------------------------------------------------------- */
    protected static function boot()
    {
        parent::boot();

        // 1. before INSERT → set slug
        static::creating(function (self $author) {
            if (empty($author->folder)) {
                $author->folder = makeUniqueSlug($author->name, 'folder', 'authors');
            }
        });

        // 2. after INSERT succeeds → create folder on disk
        static::created(function (self $author) {
            Storage::disk('uploads')
                   ->makeDirectory($author->folder);
        });
    }

    /* -----------------------------------------------------------------
     |  Relationships (unchanged)
     * ---------------------------------------------------------------- */
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
