<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chapter extends Model
{
    use HasFactory;

    // Define any fields you want to be mass-assignable
    protected $fillable = ['title', 'content', 'order', 'work_id', 'version_id'];

    /**
     * Relationship: Work Associated with the Chapter
     *
     * Defines a `belongsTo` relationship to the `Work` model, linking this
     * chapter to a specific work, if chapters are organized under works.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function work()
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * Relationship: Version Associated with the Chapter
     *
     * Defines a `belongsTo` relationship to the `Version` model, allowing
     * this chapter to be linked to a specific version if chapters vary by version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function version()
    {
        return $this->belongsTo(Version::class);
    }
}
