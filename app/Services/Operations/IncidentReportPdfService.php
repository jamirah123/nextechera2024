<?php

namespace App\Services\Operations;

use App\Models\Incident;
use App\Models\Site;
use App\Support\Documents\DompdfRenderer;
use Illuminate\Support\Collection;

class IncidentReportPdfService
{
    public function __construct(private DompdfRenderer $pdf) {}

    public function dailySiteReport(Site $site, string $date): string
    {
        $incidents = Incident::query()
            ->forSiteOnDate($site->id, $date)
            ->with(['assignedGuard:id,employment_id,full_name', 'assignee:id,name', 'reporter:id,name', 'attachments'])
            ->orderBy('occurred_at')
            ->get();

        return $this->pdf->renderView('documents.pdf.occurrence-book', [
            'site' => $site->loadMissing(['client:id,name', 'region:id,name']),
            'date' => $date,
            'incidents' => $incidents,
        ]);
    }

    public function filename(Site $site, string $date): string
    {
        $code = $site->code ?: 'site-'.$site->id;

        return 'occurrence-book-'.$code.'-'.$date.'.pdf';
    }

    /**
     * @param  Collection<int, Incident>  $incidents
     */
    public function exportRows(Collection $incidents): array
    {
        return $incidents->values()->map(fn (Incident $incident, int $index) => [
            $index + 1,
            $incident->reference,
            $incident->occurred_at?->format('Y-m-d H:i'),
            $incident->site?->name,
            $incident->site?->code,
            $incident->incident_type->label(),
            $incident->severity->label(),
            $incident->status->label(),
            $incident->title,
            $incident->assignedGuard?->employment_id,
            $incident->assignedGuard?->full_name,
            $incident->assignee?->name,
            $incident->action_taken,
            $incident->client_notified ? 'Yes' : 'No',
            $incident->reporter?->name,
        ])->all();
    }

    /** @return list<string> */
    public function exportHeaders(): array
    {
        return [
            '#', 'Reference', 'Occurred at', 'Site', 'Site code', 'Type', 'Severity', 'Status',
            'Title', 'Guard ID', 'Guard name', 'Assigned to', 'Action taken', 'Client notified', 'Reported by',
        ];
    }
}
