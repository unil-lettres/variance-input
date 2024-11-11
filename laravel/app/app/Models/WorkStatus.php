<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id', 
        'global_status', 
        'desc_status', 
        'notice_status', 
        'image_status', 
        'comparison_status'
    ];

    /**
     * Relationship: Work Associated with the Status
     *
     * Defines a `belongsTo` relationship to the `Work` model, linking
     * this status to a specific work. This allows you to access the
     * associated work from the status record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function work()
    {
        return $this->belongsTo(Work::class);
    }
}
