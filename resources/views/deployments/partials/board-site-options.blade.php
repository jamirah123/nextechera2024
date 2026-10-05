@php
    /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\Site>> $sitesByRegion */
    $regionName = $regionName ?? ($guard?->region?->name ?? 'this region');
    $regionId = (int) ($regionId ?? $guard?->region_id ?? 0);
    $guardSites = $sitesByRegion->get($regionId, collect());
@endphp
<option value="">Choose site in {{ $regionName }}…</option>
@forelse ($guardSites as $site)
    <option value="{{ $site->id }}" data-region-id="{{ $site->region_id }}">{{ $site->name }} · {{ $site->code }}</option>
@empty
    <option value="" disabled>No active sites in {{ $regionName }}</option>
@endforelse
