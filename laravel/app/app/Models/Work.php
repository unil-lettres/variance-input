<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\WorkStatus;

class Work extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'folder', 'desc', 'image_url', 'author_id'];

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
        return $this->hasOne(WorkStatus::class, 'work_id', 'id');
    }
}
