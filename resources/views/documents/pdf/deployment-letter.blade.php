<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Deployment Letter — {{ $reference }}</title>
    @include('documents.pdf.partials.styles')
</head>
<body>
    @include('documents.pdf.partials.letterhead', [
        'documentTitle' => 'Deployment Letter',
        'documentReference' => $reference,
        'documentDate' => $issuedAt,
    ])

    <p class="body-text">To whom it may concern,</p>

    <p class="body-text">
        This letter confirms that <strong>{{ $deployment->assignedGuard?->full_name }}</strong>
        (Employment ID: <strong>{{ $deployment->assignedGuard?->employment_id }}</strong>@if ($deployment->assignedGuard?->national_id),
        National ID: <strong>{{ $deployment->assignedGuard->national_id }}</strong>@endif)
        is officially deployed by <strong>{{ config('psg.company') }}</strong> as a security officer.
    </p>

    <table class="meta-table">
        <tr>
            <td>
                <p class="meta-label">Deployment site</p>
                <p class="meta-value">{{ $deployment->site?->name }}</p>
                @if ($deployment->site?->code)
                    <p class="meta-hint">{{ $deployment->site->code }}</p>
                @endif
            </td>
            <td>
                <p class="meta-label">Shift / status</p>
                <p class="meta-value">{{ $deployment->shift_type->label() }}</p>
                <p class="meta-hint">{{ $deployment->status->label() }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="meta-label">Effective from</p>
                <p class="meta-value">{{ $deployment->start_date?->format('d M Y') ?? '—' }}</p>
            </td>
            <td>
                <p class="meta-label">Region / supervisor</p>
                <p class="meta-value">{{ $deployment->region?->name ?? '—' }}</p>
                <p class="meta-hint">{{ $deployment->supervisor?->name ?? 'Supervisor not assigned' }}</p>
            </td>
        </tr>
    </table>

    @if ($deployment->notes)
        <p class="section-title">Special instructions</p>
        <p class="body-text">{{ $deployment->notes }}</p>
    @endif

    <p class="body-text">
        The officer is expected to report to the site supervisor and comply with all company policies,
        client instructions, and statutory requirements while on duty.
    </p>

    <table class="signature-grid">
        <tr>
            <td>
                <p class="meta-label">For {{ config('psg.company') }}</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Authorized signatory</p>
            </td>
            <td>
                <p class="meta-label">Acknowledged by officer</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Signature / date</p>
            </td>
        </tr>
    </table>
</body>
</html>
