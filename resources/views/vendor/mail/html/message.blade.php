<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 16px;">
<tr>
<td style="height: 4px; background: linear-gradient(90deg, #1E5D48 0%, #1E5D48 70%, #8B1E1E 100%); border-radius: 999px; font-size: 0; line-height: 0;">&nbsp;</td>
</tr>
</table>
@if (config('psg.logo_url'))
<span style="display: block; text-align: center;">
<img src="{{ config('psg.logo_url') }}" alt="{{ config('psg.company') }}" style="height: 56px; width: auto; margin-bottom: 10px;">
</span>
@endif
<span style="display: block; text-align: center; font-size: 19px; font-weight: 700; color: #0f172a; letter-spacing: -0.01em;">
{{ config('psg.company', config('app.name')) }}
</span>
@if (filled(config('psg.tagline')))
<span style="display: block; text-align: center; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #8B1E1E; margin-top: 6px;">
{{ config('psg.tagline') }}
</span>
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('psg.company', config('app.name')) }}.
@if (filled(config('psg.email_footer')))
{{ config('psg.email_footer') }}
@else
{{ __('All rights reserved.') }}
@endif
@if (filled(config('psg.support_email')) || filled(config('psg.support_phone')))
@php
    $contacts = array_filter([
        filled(config('psg.support_email')) ? config('psg.support_email') : null,
        filled(config('psg.support_phone')) ? config('psg.support_phone') : null,
    ]);
@endphp

{{ implode(' · ', $contacts) }}
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
