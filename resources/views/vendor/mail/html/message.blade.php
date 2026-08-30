<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if (config('psg.logo_url'))
<span style="display: block; text-align: center;">
<img src="{{ config('psg.logo_url') }}" alt="{{ config('psg.company') }}" style="height: 52px; width: auto; margin-bottom: 8px;">
</span>
@endif
<span style="display: block; text-align: center; font-size: 18px; font-weight: 700; color: #0f172a;">
{{ config('psg.company', config('app.name')) }}
</span>
@if (filled(config('psg.tagline')))
<span style="display: block; text-align: center; font-size: 12px; color: #64748b; margin-top: 4px;">
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
