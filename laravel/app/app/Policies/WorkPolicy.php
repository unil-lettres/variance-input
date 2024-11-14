<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Work;

class WorkPolicy
{
    public function edit(User $user, Work $work)
    {
        if ($user->is_admin) {
            return true;
        }

        return $user->permissions()
            ->where('work_id', $work->id)
            ->where('permission_type', 'edit')
            ->exists();
    }
}
