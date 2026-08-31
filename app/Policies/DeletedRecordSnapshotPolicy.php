<?php

namespace App\Policies;

use App\Models\DeletedRecordSnapshot;
use App\Models\User;
use App\Support\Access\Access;

class DeletedRecordSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'admin.records_restore');
    }

    public function view(User $user, DeletedRecordSnapshot $snapshot): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, DeletedRecordSnapshot $snapshot): bool
    {
        return $this->viewAny($user) && $snapshot->isRestorable();
    }
}
