<?php

namespace App\Support\Navigation;

use App\Enums\DesertionHrStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Desertion;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use Throwable;

/**
 * Sparse attention badges for sidebar links (only when action is needed).
 */
class NavigationBadges
{
    /** @return array<string, int> keyed by absolute href */
    public static function for(User $user): array
    {
        $badges = [];

        try {
            if (Access::userCan($user, 'hr.leaves_approve') || Access::userCan($user, 'hr.leaves_manage')) {
                $pending = Leave::query()->where('status', LeaveStatus::Pending)->count();
                if ($pending > 0) {
                    $badges[route('leaves.index')] = $pending;
                }
            }

            if (Access::userCan($user, 'finance.view')) {
                $overdue = Invoice::query()->where('status', InvoiceStatus::Overdue)->count();
                if ($overdue > 0) {
                    $badges[route('invoices.index')] = $overdue;
                }
            }

            if (Access::userCan($user, 'organization.view') || Access::userCan($user, 'operations.work_orders_manage')) {
                $open = WorkOrder::query()
                    ->whereIn('status', [
                        WorkOrderStatus::Open->value,
                        WorkOrderStatus::Assigned->value,
                        WorkOrderStatus::InProgress->value,
                    ])
                    ->count();
                if ($open > 0) {
                    $badges[route('work-orders.index')] = $open;
                }
            }

            if (Access::userCan($user, 'organization.view') || Access::userCan($user, 'hr.desertions_manage')) {
                $openDesertions = Desertion::query()
                    ->whereIn('hr_status', [
                        DesertionHrStatus::Reported->value,
                        DesertionHrStatus::Investigating->value,
                        DesertionHrStatus::Confirmed->value,
                    ])
                    ->count();
                if ($openDesertions > 0) {
                    $badges[route('desertions.index')] = $openDesertions;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $badges;
    }
}
