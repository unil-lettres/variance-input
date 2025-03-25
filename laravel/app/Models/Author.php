<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'folder', 'order'];

    /**
     * Relationship: Works of the Author
     *
     * Defines a `hasMany` relationship to the `Work` model, allowing you
     * to retrieve all works associated with this author.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function works()
    {
        return $this->hasMany(Work::class);
    }

    /**
     * Relationship: Status of the Author
     *
     * Defines a `hasOne` relationship to the `WorkStatus` model. This
     * can be used to access the status record specific to this author.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function status()
    {
        return $this->hasOne(WorkStatus::class);
    }

    /**
     * Relationship: Permissions on the Author
     *
     * Defines a `hasMany` relationship with the `Permission` model, allowing
     * access to all permissions granted for this author.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
