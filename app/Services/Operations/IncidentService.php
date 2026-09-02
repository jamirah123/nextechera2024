<?php

namespace App\Services\Operations;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Site;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): Incident
    {
        return DB::transaction(function () use ($data): Incident {
            $incident = Incident::query()->create([
                'reference' => $this->nextReference(),
                'site_id' => $data['site_id'],
                'guard_id' => $data['guard_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'incident_type' => $data['incident_type'],
                'severity' => $data['severity'] ?? IncidentSeverity::Medium->value,
                'status' => IncidentStatus::Reported->value,
                'occurred_at' => $data['occurred_at'],
                'reported_at' => now(),
                'title' => $data['title'],
                'description' => $data['description'],
                'action_taken' => $data['action_taken'] ?? null,
                'follow_up_notes' => $data['follow_up_notes'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'follow_up_due_at' => $data['follow_up_due_at'] ?? null,
                'police_reference' => $data['police_reference'] ?? null,
                'client_notified' => (bool) ($data['client_notified'] ?? false),
                'reported_by' => auth()->id(),
            ]);

            $incident->load('site:id,name');

            $this->audit->log(
                action: 'incident.reported',
                summary: 'Occurrence logged at '.$incident->site?->name.': '.$incident->title,
                category: AuditCategory::Security,
                severity: $this->auditSeverity($incident->severity),
                subject: $incident,
                context: [
                    'incident_type' => $incident->incident_type->value,
                    'site_id' => $incident->site_id,
                ],
            );

            return $incident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFollowUp(Incident $incident, array $data): Incident
    {
        return DB::transaction(function () use ($incident, $data): Incident {
            $incident->update([
                'status' => $data['status'] ?? $incident->status->value,
                'assigned_to' => $data['assigned_to'] ?? null,
                'follow_up_due_at' => $data['follow_up_due_at'] ?? null,
                'follow_up_notes' => $data['follow_up_notes'] ?? $incident->follow_up_notes,
                'action_taken' => $data['action_taken'] ?? $incident->action_taken,
                'client_notified' => array_key_exists('client_notified', $data)
                    ? (bool) $data['client_notified']
                    : $incident->client_notified,
                'police_reference' => $data['police_reference'] ?? $incident->police_reference,
            ]);

            $this->audit->log(
                action: 'incident.updated',
                summary: 'Occurrence '.$incident->reference.' updated ('.$incident->status->label().').',
                category: AuditCategory::Security,
                severity: AuditSeverity::Info,
                subject: $incident->fresh(),
                context: ['status' => $incident->status->value],
            );

            return $incident->fresh();
        });
    }

    public function nextReference(): string
    {
        $date = now()->format('Ymd');
        $count = Incident::query()->whereDate('created_at', now()->toDateString())->count() + 1;

        return 'OB-'.$date.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function auditSeverity(IncidentSeverity $severity): AuditSeverity
    {
        return match ($severity) {
            IncidentSeverity::Critical, IncidentSeverity::High => AuditSeverity::Critical,
            IncidentSeverity::Medium => AuditSeverity::Warning,
            IncidentSeverity::Low => AuditSeverity::Info,
        };
    }
}
