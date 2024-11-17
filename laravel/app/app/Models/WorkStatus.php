<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'global_status',
        'desc_status',
        'notice_status',
        'image_status',
        'comparison_status',
    ];

    /**
     * Get the work that owns the status.
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}