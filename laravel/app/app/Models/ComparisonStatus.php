<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComparisonStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'comparisons_source_id', 
        'comparisons_target_id', 
        'order', 
        'medite', 
        'status', 
        'validation', 
        'publication'
    ];

    /**
     * Relationship: Comparison Associated with the Status
     *
     * Defines a `belongsTo` relationship to the `Comparison` model,
     * linking this status to a specific comparison. This allows you
     * to access the associated comparison from the status record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function comparison()
    {
        return $this->belongsTo(Comparison::class);
    }
}
