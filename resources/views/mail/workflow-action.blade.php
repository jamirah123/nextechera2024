<x-mail::message>
# {{ $headline }}

{{ $summary }}

@if ($actorName)
**Action by:** {{ $actorName }}
@endif

@if ($details !== [])
@foreach ($details as $detail)
- {{ $detail }}
@endforeach
@endif

@if ($actionUrl)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
