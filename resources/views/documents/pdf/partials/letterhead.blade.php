@php
    $company = config('psg.company', 'Platinum Security Group');
    $email = config('psg.support_email');
    $phone = config('psg.support_phone');
@endphp

<table class="header-table">
    <tr>
        <td>
            <p class="company-name">{{ $company }}</p>
            <p class="tagline">{{ config('psg.tagline', 'New Age Security and Protection') }}</p>
            @if (filled($email) || filled($phone))
                <p class="contact">
                    @if (filled($email)){{ $email }}@endif
                    @if (filled($email) && filled($phone)) · @endif
                    @if (filled($phone)){{ $phone }}@endif
                </p>
            @endif
        </td>
        <td style="width: 42%;">
            @if (! empty($documentTitle))
                <p class="doc-kicker">Official document</p>
                <p class="doc-title">{{ $documentTitle }}</p>
            @endif
            @if (! empty($documentReference))
                <p class="doc-ref">{{ $documentReference }}</p>
            @endif
            @if (! empty($documentDate))
                <p class="doc-ref">{{ $documentDate }}</p>
            @endif
        </td>
    </tr>
</table>
<div class="rule"></div>
