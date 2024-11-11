<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VersionStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'version_id', 
        'order', 
        'date', 
        'typo_status', 
        'metadata_status', 
        'chapters_status', 
        'facsimile_status', 
        'last_modif'
    ];

    /**
     * Relationship: Version Associated with the Status
     *
     * Defines a `belongsTo` relationship to the `Version` model, linking
     * this status to a specific version. This allows you to access the
     * associated version from the status record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function version()
    {
        return $this->belongsTo(Version::class);
    }
}
