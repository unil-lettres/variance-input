<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'folder', 'work_id'];

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
}
