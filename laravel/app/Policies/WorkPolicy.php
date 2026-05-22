<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Work;

class WorkPolicy
{
    public function edit(User $user, Work $work)
    {
        return $user->canEditWork($work);
    }
}
