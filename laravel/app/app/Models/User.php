<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relationship: Authors Created by the User
     *
     * Defines a one-to-many relationship where this user is the creator
     * of multiple authors. Use this to retrieve all authors created by
     * a specific user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function createdAuthors()
    {
        return $this->hasMany(Author::class, 'created_by');
    }

    /**
     * Relationship: User Permissions
     *
     * Defines a one-to-many relationship with the `Permission` model.
     * This allows you to retrieve all permissions assigned to the user.
     * For example, `$user->permissions` will return a collection of
     * permissions records associated with this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Relationship: Editable Works
     *
     * Defines a relationship to retrieve all works the user has permission
     * to edit, through the `Permission` model. This uses a `hasManyThrough`
     * relationship to connect `User` to `Work` through `Permission`.
     *
     * Example usage:
     * `$user->editableWorks` will return a collection of `Work` instances
     * the user can edit.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function editableWorks()
    {
        return $this->hasManyThrough(Work::class, Permission::class, 'user_id', 'id', 'id', 'work_id')
                    ->where('permission_type', 'edit');
    }

    /**
     * Relationship: Editable Authors
     *
     * Defines a custom query to retrieve all authors the user can edit,
     * either directly (through a permission associated with the author)
     * or indirectly (through permissions on the author's works).
     *
     * Example usage:
     * `$user->editableAuthors` will return a collection of `Author`
     * instances for which the user has edit rights.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function editableAuthors()
    {
        return Author::whereHas('permissions', function ($query) {
            $query->where('user_id', $this->id);
        })->orWhereHas('works.permissions', function ($query) {
            $query->where('user_id', $this->id);
        });
    }
}
