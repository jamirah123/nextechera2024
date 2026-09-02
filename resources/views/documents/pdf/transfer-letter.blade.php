<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Transfer Letter — {{ $reference }}</title>
    @include('documents.pdf.partials.styles')
</head>
<body>
    @include('documents.pdf.partials.letterhead', [
        'documentTitle' => 'Transfer Letter',
        'documentReference' => $reference,
        'documentDate' => $issuedAt,
    ])

    <p class="body-text">To whom it may concern,</p>

    <p class="body-text">
        This letter confirms the transfer of <strong>{{ $transfer->guardRecord?->full_name }}</strong>
        (Employment ID: <strong>{{ $transfer->guardRecord?->employment_id }}</strong>)
        effective <strong>{{ $issuedAt }}</strong>.
    </p>

    <table class="meta-table">
        <tr>
            <td>
                <p class="meta-label">From site</p>
                <p class="meta-value">{{ $transfer->fromSite?->name ?? '—' }}</p>
            </td>
            <td>
                <p class="meta-label">To site</p>
                <p class="meta-value">{{ $transfer->toSite?->name ?? '—' }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="meta-label">Reason</p>
                <p class="meta-value">{{ $transfer->reason ?: 'Operational requirement' }}</p>
            </td>
            <td>
                <p class="meta-label">Authorized by</p>
                <p class="meta-value">{{ $transfer->transferrer?->name ?? config('psg.company') }}</p>
            </td>
        </tr>
    </table>

    @if ($transfer->notes)
        <p class="section-title">Notes</p>
        <p class="body-text">{{ $transfer->notes }}</p>
    @endif

    <p class="body-text">
        The officer should report to the new site supervisor on the effective date and continue to observe
        all company standards and client requirements.
    </p>

    <table class="signature-grid">
        <tr>
            <td>
                <p class="meta-label">For {{ config('psg.company') }}</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Authorized signatory</p>
            </td>
            <td>
                <p class="meta-label">Officer acknowledgment</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Signature / date</p>
            </td>
        </tr>
    </table>
</body>
</html>
