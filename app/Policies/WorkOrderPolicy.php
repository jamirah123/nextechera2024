<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if (! Access::userCan($user, 'organization.view')) {
            return false;
        }

        if ($workOrder->assigned_to === $user->id) {
            return $this->canAccessRegion($user, $workOrder);
        }

        if (! Access::userCan($user, 'operations.work_orders_manage')) {
            return false;
        }

        return $this->canAccessRegion($user, $workOrder);
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'operations.work_orders_manage');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return Access::userCan($user, 'operations.work_orders_manage') && $this->view($user, $workOrder);
    }

    public function complete(User $user, WorkOrder $workOrder): bool
    {
        if (! $workOrder->status->isOpen()) {
            return false;
        }

        if ($workOrder->assigned_to === $user->id) {
            return $this->canAccessRegion($user, $workOrder);
        }

        return $this->update($user, $workOrder);
    }

    public function cancel(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }

    private function canAccessRegion(User $user, WorkOrder $workOrder): bool
    {
        if (! $user->mustStayInOwnRegion()) {
            return true;
        }

        if ($workOrder->region_id === null) {
            return true;
        }

        return $user->canAccessRegion($workOrder->region_id);
    }
}
