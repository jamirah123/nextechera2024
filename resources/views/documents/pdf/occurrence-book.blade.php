<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Occurrence Book — {{ $site->name }} — {{ $date }}</title>
    @include('documents.pdf.partials.styles')
</head>
<body>
    @include('documents.pdf.partials.letterhead', [
        'documentTitle' => 'Daily Occurrence Book',
        'documentReference' => $site->code ?? ('Site #'.$site->id),
        'documentDate' => \Illuminate\Support\Carbon::parse($date)->format('d M Y'),
    ])

    <table class="meta-table">
        <tr>
            <td>
                <p class="meta-label">Site</p>
                <p class="meta-value">{{ $site->name }}</p>
                <p class="meta-hint">{{ $site->client?->name }}</p>
            </td>
            <td>
                <p class="meta-label">Region</p>
                <p class="meta-value">{{ $site->region?->name ?? '—' }}</p>
                <p class="meta-hint">{{ $incidents->count() }} occurrence(s) logged</p>
            </td>
        </tr>
    </table>

    @if ($incidents->isEmpty())
        <p class="body-text">No occurrences were recorded for this site on the selected date.</p>
    @else
        @foreach ($incidents as $incident)
            <div style="margin-bottom: 16px; page-break-inside: avoid;">
                <p class="section-title" style="margin-top: 0;">
                    {{ $incident->occurred_at?->format('H:i') }} · {{ $incident->reference }} · {{ $incident->incident_type->label() }}
                </p>
                <p class="body-text"><strong>{{ $incident->title }}</strong> ({{ $incident->severity->label() }} / {{ $incident->status->label() }})</p>
                <p class="body-text">{{ $incident->description }}</p>
                @if ($incident->assignedGuard)
                    <p class="body-text"><strong>Officer:</strong> {{ $incident->assignedGuard->full_name }} ({{ $incident->assignedGuard->employment_id }})</p>
                @endif
                @if ($incident->action_taken)
                    <p class="body-text"><strong>Action taken:</strong> {{ $incident->action_taken }}</p>
                @endif
                @if ($incident->assignee)
                    <p class="body-text"><strong>Follow-up assigned:</strong> {{ $incident->assignee->name }}</p>
                @endif
                @if ($incident->attachments->isNotEmpty())
                    <p class="body-text"><strong>Attachments:</strong> {{ $incident->attachments->count() }} file(s) on record</p>
                @endif
            </div>
        @endforeach
    @endif

    <table class="signature-grid">
        <tr>
            <td>
                <p class="meta-label">Prepared by {{ config('psg.company') }}</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Duty supervisor signature / date</p>
            </td>
            <td>
                <p class="meta-label">Client acknowledgment</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Client representative signature / date</p>
            </td>
        </tr>
    </table>
</body>
</html>
