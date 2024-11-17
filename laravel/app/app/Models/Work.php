<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Work extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
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
}