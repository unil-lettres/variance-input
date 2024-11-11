<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'author_id', 'work_id', 'permission_type'];

    /**
     * Relationship: User Who Holds the Permission
     *
     * Defines a `belongsTo` relationship to the `User` model, representing
     * the user to whom this permission is granted.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Author Associated with the Permission
     *
     * Defines a `belongsTo` relationship to the `Author` model, allowing you
     * to retrieve the author this permission is for, if applicable.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * Relationship: Work Associated with the Permission
     *
     * Defines a `belongsTo` relationship to the `Work` model, allowing you
     * to retrieve the work this permission is for, if applicable.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function work()
    {
        return $this->belongsTo(Work::class);
    }
}
