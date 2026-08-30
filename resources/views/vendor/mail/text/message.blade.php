<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('psg.company', config('app.name')) }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
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
                @lang('All rights reserved.')
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
