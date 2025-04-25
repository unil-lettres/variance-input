<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Work extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'short_title',
        'author_id',
        'folder',
        'desc',
        'image_url',
    ];

    /**
     * Get the associated work status.
     */
    public function workStatus(): HasOne
    {
        return $this->hasOne(WorkStatus::class, 'work_id');
    }

    /**
     * Get all versions related to this work.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(Version::class);
    }

    /**
     * Get the author that owns the work.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
