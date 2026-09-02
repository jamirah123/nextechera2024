<?php

namespace App\Services\Documents;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Models\Deployment;
use App\Models\DeploymentTransfer;
use App\Models\Guard;
use App\Models\Leave;
use App\Support\Documents\DompdfRenderer;
use InvalidArgumentException;

class LetterPdfService
{
    public function __construct(private DompdfRenderer $pdf)
    {
    }

    public function deployment(Deployment $deployment): string
    {
        $deployment->loadMissing([
            'assignedGuard:id,employment_id,full_name,national_id,rank_designation',
            'site:id,name,code,address',
            'region:id,name',
            'supervisor:id,name',
        ]);

        return $this->pdf->renderView('documents.pdf.deployment-letter', [
            'deployment' => $deployment,
            'reference' => 'DEP-'.str_pad((string) $deployment->id, 5, '0', STR_PAD_LEFT),
            'issuedAt' => now()->format('d M Y'),
        ]);
    }

    public function transfer(DeploymentTransfer $transfer): string
    {
        $transfer->loadMissing([
            'guardRecord:id,employment_id,full_name,national_id',
            'fromSite:id,name,code',
            'toSite:id,name,code',
            'transferrer:id,name',
        ]);

        return $this->pdf->renderView('documents.pdf.transfer-letter', [
            'transfer' => $transfer,
            'reference' => 'TRF-'.str_pad((string) $transfer->id, 5, '0', STR_PAD_LEFT),
            'issuedAt' => ($transfer->effective_at ?? now())->format('d M Y'),
        ]);
    }

    public function leaveApproval(Leave $leave): string
    {
        if (! in_array($leave->status, [LeaveStatus::Approved, LeaveStatus::Completed], true)) {
            throw new InvalidArgumentException('Leave approval letter is only available for approved leave.');
        }

        $leave->loadMissing([
            'assignedGuard:id,employment_id,full_name,national_id,rank_designation',
            'approver:id,name',
        ]);

        return $this->pdf->renderView('documents.pdf.leave-approval', [
            'leave' => $leave,
            'reference' => 'LVE-'.str_pad((string) $leave->id, 5, '0', STR_PAD_LEFT),
            'issuedAt' => ($leave->approved_at ?? now())->format('d M Y'),
        ]);
    }

    public function termination(Guard $guard): string
    {
        if (! in_array($guard->employment_status, [
            EmploymentStatus::Terminated,
            EmploymentStatus::Resigned,
            EmploymentStatus::Retired,
        ], true)) {
            throw new InvalidArgumentException('Termination letter is only available for ended employment.');
        }

        $guard->loadMissing(['region:id,name', 'currentSite:id,name']);

        $effectiveDate = $guard->employment_end_date?->format('d M Y') ?? now()->format('d M Y');

        return $this->pdf->renderView('documents.pdf.termination-letter', [
            'guard' => $guard,
            'reference' => 'TRM-'.$guard->employment_id,
            'issuedAt' => now()->format('d M Y'),
            'effectiveDate' => $effectiveDate,
        ]);
    }
}
