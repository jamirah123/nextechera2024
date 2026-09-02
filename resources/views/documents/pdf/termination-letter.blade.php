<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Employment Termination — {{ $reference }}</title>
    @include('documents.pdf.partials.styles')
</head>
<body>
    @include('documents.pdf.partials.letterhead', [
        'documentTitle' => 'Employment Termination',
        'documentReference' => $reference,
        'documentDate' => $issuedAt,
    ])

    <p class="body-text">
        <strong>{{ $guard->full_name }}</strong><br>
        Employment ID: {{ $guard->employment_id }}<br>
        @if ($guard->national_id)
            National ID: {{ $guard->national_id }}<br>
        @endif
        @if ($guard->address)
            {{ $guard->address }}
        @endif
    </p>

    <p class="body-text">Dear {{ strtok($guard->full_name, ' ') }},</p>

    <p class="body-text">
        This letter serves as formal notice that your employment with
        <strong>{{ config('psg.company') }}</strong> will end effective
        <strong>{{ $effectiveDate }}</strong> on grounds of
        <strong>{{ $guard->employment_status->label() }}</strong>.
    </p>

    <table class="meta-table">
        <tr>
            <td>
                <p class="meta-label">Employment status</p>
                <p class="meta-value">{{ $guard->employment_status->label() }}</p>
            </td>
            <td>
                <p class="meta-label">Effective end date</p>
                <p class="meta-value">{{ $effectiveDate }}</p>
            </td>
        </tr>
        <tr>
            <td>
                <p class="meta-label">Date employed</p>
                <p class="meta-value">{{ $guard->date_employed?->format('d M Y') ?? '—' }}</p>
            </td>
            <td>
                <p class="meta-label">Last known site</p>
                <p class="meta-value">{{ $guard->currentSite?->name ?? '—' }}</p>
            </td>
        </tr>
    </table>

    @if ($guard->notes)
        <p class="section-title">Additional notes</p>
        <p class="body-text">{{ $guard->notes }}</p>
    @endif

    <p class="body-text">
        You are required to return all company property including uniform, identification, equipment, and
        any client keys in your possession. Final settlement of any outstanding dues will be processed in
        accordance with company policy and applicable labour laws.
    </p>

    <p class="body-text">
        We thank you for your service and wish you success in your future endeavours.
    </p>

    <table class="signature-grid">
        <tr>
            <td>
                <p class="meta-label">For {{ config('psg.company') }} HR</p>
                <div class="signature-line"></div>
                <p class="signature-caption">Authorized signatory</p>
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
