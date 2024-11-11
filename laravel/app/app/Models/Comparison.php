<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comparison extends Model
{
    use HasFactory;

    protected $fillable = ['folder', 'number', 'prefix_label', 'source_id', 'target_id'];

    /**
     * Relationship: Source Version of the Comparison
     *
     * Defines a `belongsTo` relationship to the `Version` model, associating
     * this comparison with its source version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceVersion()
    {
        return $this->belongsTo(Version::class, 'source_id');
    }

    /**
     * Relationship: Target Version of the Comparison
     *
     * Defines a `belongsTo` relationship to the `Version` model, associating
     * this comparison with its target version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function targetVersion()
    {
        return $this->belongsTo(Version::class, 'target_id');
    }

    /**
     * Relationship: Status of the Comparison
     *
     * Defines a `hasOne` relationship to the `ComparisonStatus` model. This
     * allows access to the status record specific to this comparison.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function status()
    {
        return $this->hasOne(ComparisonStatus::class);
    }
}
