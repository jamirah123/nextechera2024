@php
    /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\Site>> $sitesByRegion */
    /** @var \App\Models\Guard|null $guard */
    $guard = $guard ?? null;
    $regionName = $guard?->region?->name ?? 'this region';
    $guardSites = $guard
        ? $sitesByRegion->get((int) $guard->region_id, collect())
        : collect();
@endphp
<option value="">Choose site in {{ $regionName }}…</option>
@forelse ($guardSites as $site)
    <option value="{{ $site->id }}" data-region-id="{{ $site->region_id }}">{{ $site->name }} · {{ $site->code }}</option>
@empty
    <option value="" disabled>No active sites in {{ $regionName }}</option>
@endforelse
