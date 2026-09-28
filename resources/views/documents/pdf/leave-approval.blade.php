<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Leave Approval — {{ $reference }}</title>
    @include('documents.pdf.partials.styles')
</head>
<body>
    @include('documents.pdf.partials.letterhead', [
        'documentTitle' => 'Leave Approval',
        'documentReference' => $reference,
        'documentDate' => $issuedAt,
    ])

    <p class="body-text">
        <strong>{{ $leave->assignedGuard?->full_name }}</strong><br>
        Employment ID: {{ $leave->assignedGuard?->employment_id }}<br>
        @if ($leave->assignedGuard?->rank_designation)
            {{ $leave->assignedGuard->rank_designation }}<br>
        @endif
    </p>

    <p class="body-text">Dear {{ strtok($leave->assignedGuard?->full_name ?? 'Colleague', ' ') }},</p>

    <p class="body-text">
        Your <strong>{{ $leave->typeLabel() }}</strong> leave request has been
        <span class="status-pill">Approved</span>.
        You are authorized to be away from duty during the period below.
    </p>

    <table class="meta-table">
        <tr>
            <td>
                <p class="meta-label">Leave period</p>
                <p class="meta-value">{{ $leave->start_date->format('d M Y') }} – {{ $leave->end_date->format('d M Y') }}</p>
            </td>
            <td>
                <p class="meta-label">Expected return</p>
                <p class="meta-value">{{ $leave->expected_return_date?->format('d M Y') ?? $leave->end_date->copy()->addDay()->format('d M Y') }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="meta-label">Approved by</p>
                <p class="meta-value">{{ $leave->approver?->name ?? 'HR Department' }}</p>
            </td>
            <td>
                <p class="meta-label">Approval date</p>
                <p class="meta-value">{{ $issuedAt }}</p>
            </td>
        </tr>
    </table>

    @if ($leave->reason)
        <p class="section-title">Reason</p>
        <p class="body-text">{{ $leave->reason }}</p>
    @endif

    @if ($leave->notes)
        <p class="section-title">Conditions / notes</p>
        <p class="body-text">{{ $leave->notes }}</p>
    @endif

    <p class="body-text">
        Please ensure all handover arrangements are completed before departure and report to your supervisor
        on your return date. Unauthorized absence after the approved period may attract disciplinary action.
    </p>

    <table class="signature-grid">
        <tr>
            <td>
                <p class="meta-label">For {{ config('psg.company') }} HR</p>
                <div class="signature-line"></div>
                <p class="signature-caption">{{ $leave->approver?->name ?? 'Authorized signatory' }}</p>
            </td>
            <td>
                <p class="meta-label">Employee acknowledgment</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Signature / date</p>
            </td>
        </tr>
    </table>
</body>
</html>
