@props([
    'currentUrl',
    'allUrl',
    'scope' => 'current',
    'currentLabel' => 'Current',
    'allLabel' => 'All history',
])

<div {{ $attributes->merge(['class' => 'no-print inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1']) }}>
    <a
        href="{{ $currentUrl }}"
        @class([
            'rounded-lg px-3 py-2 text-sm font-semibold transition',
            'bg-white text-brand-800 shadow-sm' => $scope !== 'all',
            'text-slate-600 hover:text-slate-900' => $scope === 'all',
        ])
    >{{ $currentLabel }}</a>
    <a
        href="{{ $allUrl }}"
        @class([
            'rounded-lg px-3 py-2 text-sm font-semibold transition',
            'bg-white text-brand-800 shadow-sm' => $scope === 'all',
            'text-slate-600 hover:text-slate-900' => $scope !== 'all',
        ])
    >{{ $allLabel }}</a>
</div>
