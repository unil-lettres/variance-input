<?php

namespace App\Models;

use App\Models\Comparison;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const PERMISSION_EDIT = 'edit';
    public const PERMISSION_VERSION_EDITOR = 'version_editor';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'full_name',
        'email',
        'password',
        'is_admin',
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
     * Relationship: Comparisons created by the user.
     */
    public function comparisons()
    {
        return $this->hasMany(Comparison::class, 'created_by');
    }

    /**
     * Helper for non-admin users (researcher role).
     */
    public function isResearcher(): bool
    {
        return ! $this->is_admin;
    }

    public function canEditWork(Work $work): bool
    {
        if ($work->is_legacy) {
            return false;
        }

        if ($this->is_admin) {
            return true;
        }

        return $this->hasPermissionForWork($work, self::PERMISSION_EDIT);
    }

    public function canEditVersion(Version $version): bool
    {
        $version->loadMissing('work');

        if ($version->is_legacy || ! $version->work) {
            return false;
        }

        return $this->canEditWork($version->work);
    }

    public function canUseVersionEditorForWork(Work $work): bool
    {
        if ($this->canEditWork($work)) {
            return true;
        }

        return ! $work->is_legacy
            && $this->hasPermissionForWork($work, self::PERMISSION_VERSION_EDITOR);
    }

    public function canUseVersionEditor(Version $version): bool
    {
        $version->loadMissing('work');

        if ($version->is_legacy || ! $version->work) {
            return false;
        }

        return $this->canUseVersionEditorForWork($version->work);
    }

    public function canManageComparison(Comparison $comparison): bool
    {
        if ($this->is_admin) {
            return true;
        }

        if ($comparison->is_legacy) {
            return false;
        }

        if ((int) $comparison->created_by === (int) $this->id) {
            return true;
        }

        $comparison->loadMissing('sourceVersion.work', 'targetVersion.work');

        foreach ([$comparison->sourceVersion?->work, $comparison->targetVersion?->work] as $work) {
            if ($work && $this->canEditWork($work)) {
                return true;
            }
        }

        return false;
    }

    public function isRestrictedVersionEditor(): bool
    {
        if ($this->is_admin) {
            return false;
        }

        $hasVersionEditorPermission = $this->permissions()
            ->where('permission_type', self::PERMISSION_VERSION_EDITOR)
            ->exists();

        if (! $hasVersionEditorPermission) {
            return false;
        }

        return ! $this->permissions()
            ->where('permission_type', self::PERMISSION_EDIT)
            ->exists();
    }

    public function versionEditorWorks()
    {
        if ($this->is_admin) {
            return Work::query()
                ->with(['author', 'versions'])
                ->where('is_legacy', false)
                ->orderBy('title')
                ->get();
        }

        return Work::query()
            ->with(['author', 'versions'])
            ->where('is_legacy', false)
            ->where(function ($query) {
                $query->whereHas('permissions', function ($inner) {
                    $inner->where('user_id', $this->id)
                        ->where('permission_type', self::PERMISSION_VERSION_EDITOR);
                })->orWhereHas('author.permissions', function ($inner) {
                    $inner->where('user_id', $this->id)
                        ->where('permission_type', self::PERMISSION_VERSION_EDITOR);
                });
            })
            ->orderBy('title')
            ->get();
    }

    private function hasPermissionForWork(Work $work, string $permissionType): bool
    {
        $hasWorkPermission = $this->permissions()
            ->where('work_id', $work->id)
            ->where('permission_type', $permissionType)
            ->exists();

        if ($hasWorkPermission) {
            return true;
        }

        return $this->permissions()
            ->where('author_id', $work->author_id)
            ->where('permission_type', $permissionType)
            ->exists();
    }

    /**
     * Relationship: Editable Works
     *
     * Retrieves all works that the user has permission to edit, either through
     * direct work-level permissions or through author-level permissions that
     * apply to all works by that author. Combines `hasManyThrough` and additional
     * conditions to include both permission levels.
     *
     * Example usage:
     * `$user->editableWorks` will return a collection of `Work` instances
     * the user can edit, based on both work and author-level permissions.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function editableWorks()
    {
        // Step 1: Works the user has direct permissions to edit
        $workPermissions = $this->hasManyThrough(Work::class, Permission::class, 'user_id', 'id', 'id', 'work_id')
                                ->where('permission_type', self::PERMISSION_EDIT);

        // Step 2: Works from authors the user has edit permissions on
        $authorPermissions = Work::whereHas('author.permissions', function ($query) {
            $query->where('user_id', $this->id)
                ->where('permission_type', self::PERMISSION_EDIT);
        });

        // Combine the results from both queries
        return $workPermissions->union($authorPermissions)->get();
    }

    /**
     * List of Works User Can Edit for a Given Author
     *
     * Retrieves all works that the user has permission to edit for a specific
     * author, either through direct work permissions or author-level permissions.
     *
     * @param int $authorId - The ID of the author whose works we want to check.
     * @return \Illuminate\Database\Eloquent\Collection - Collection of editable works.
     */
    public function editableWorksForAuthor($authorId)
    {
        // Step 1: Directly allowed works for the given author
        $directWorkPermissions = $this->hasManyThrough(Work::class, Permission::class, 'user_id', 'id', 'id', 'work_id')
                                    ->where('permission_type', self::PERMISSION_EDIT)
                                    ->where('author_id', $authorId);

        // Step 2: Works by the author where the user has author-level permission
        $authorLevelPermissions = Work::where('author_id', $authorId)
                                    ->whereHas('author.permissions', function ($query) {
                                        $query->where('user_id', $this->id)
                                                ->where('permission_type', self::PERMISSION_EDIT);
                                    });

        // Combine both sets of permissions
        return $directWorkPermissions->union($authorLevelPermissions)->get();
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

    /**
     * Preferred display name for header / UI.
     */
    public function getDisplayNameAttribute(): string
    {
        $fullName = $this->attributes['full_name'] ?? null;

        if (is_string($fullName) && trim($fullName) !== '') {
            return $fullName;
        }

        if (! empty($this->name)) {
            return $this->name;
        }

        $email = $this->attributes['email'] ?? '';

        if (is_string($email) && str_contains($email, '@')) {
            return substr($email, 0, strpos($email, '@'));
        }

        return 'Utilisateur';
    }
}
