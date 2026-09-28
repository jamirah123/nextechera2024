<?php

namespace App\Policies;

use App\Models\Guard;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Access\SupervisorPayAccess;

class GuardPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'guards.view');
    }

    public function view(User $user, Guard $guard): bool
    {
        return Access::userCan($user, 'guards.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'guards.manage');
    }

    public function update(User $user, Guard $guard): bool
    {
        return Access::userCan($user, 'guards.manage');
    }

    public function viewSalary(User $user, Guard $guard): bool
    {
        return $this->view($user, $guard) && SupervisorPayAccess::canViewGuardPay($user, $guard);
    }

    public function manageSalary(User $user, Guard $guard): bool
    {
        return Access::userCan($user, 'hr.salary_manage');
    }

    public function promote(User $user, Guard $guard): bool
    {
        return Access::userCan($user, 'hr.promotions_manage');
    }

    public function correctEmploymentId(User $user, Guard $guard): bool
    {
        return Access::userCan($user, 'employees.correct_employment_id');
    }

    public function deleteAny(User $user): bool
    {
        return Access::userCan($user, 'guards.manage');
    }

    public function delete(User $user, Guard $guard): bool
    {
        return Access::userCan($user, 'guards.manage');
    }
}
