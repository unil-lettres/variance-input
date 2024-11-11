<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'folder', 'desc', 'image_url', 'author_id'];

    /**
     * Relationship: Author of the Work
     *
     * Defines a `belongsTo` relationship to the `Author` model, allowing
     * you to retrieve the author associated with this work.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Relationship: Versions of the Work
     *
     * Defines a `hasMany` relationship to the `Version` model, allowing
     * retrieval of all versions related to this work.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function versions()
    {
        return $this->hasMany(Version::class);
    }

    /**
     * Relationship: Status of the Work
     *
     * Defines a `hasOne` relationship to the `WorkStatus` model. This
     * allows access to the status record specific to this work.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function status()
    {
        return $this->hasOne(WorkStatus::class);
    }

    /**
     * Relationship: Permissions on the Work
     *
     * Defines a `hasMany` relationship with the `Permission` model, allowing
     * access to all permissions granted for this work.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
