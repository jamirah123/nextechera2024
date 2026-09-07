@php
    /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, \App\Models\Site>> $sitesByRegion */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Region> $regions */
    /** @var \App\Models\Guard|null $guard */
    $guard = $guard ?? null;
    $showAllSites = empty($filters['region_id'] ?? null);
    $placeholder = $showAllSites
        ? 'Choose site…'
        : 'Choose site in '.($guard?->region?->name ?? 'region').'…';
@endphp
<option value="">{{ $placeholder }}</option>
@if ($showAllSites)
    @foreach ($regions as $region)
        @php $regionSites = $sitesByRegion->get((int) $region->id, collect()); @endphp
        @if ($regionSites->isNotEmpty())
            <optgroup label="{{ $region->name }} ({{ $regionSites->count() }})">
                @foreach ($regionSites as $site)
                    <option
                        value="{{ $site->id }}"
                        data-region-id="{{ $site->region_id }}"
                        @disabled($guard && (int) $guard->region_id !== (int) $site->region_id)
                    >
                        {{ $site->name }} · {{ $site->code }}
                        @if ($guard && (int) $guard->region_id !== (int) $site->region_id)
                            — other region
                        @endif
                    </option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
@else
    @php
        $guardSites = $guard
            ? $sitesByRegion->get((int) $guard->region_id, collect())
            : $sitesByRegion->get((int) ($filters['region_id'] ?? 0), collect());
    @endphp
    @forelse ($guardSites as $site)
        <option value="{{ $site->id }}" data-region-id="{{ $site->region_id }}">{{ $site->name }} · {{ $site->code }}</option>
    @empty
        <option value="" disabled>No active sites in {{ $guard?->region?->name ?? 'this region' }}</option>
    @endforelse
@endif
