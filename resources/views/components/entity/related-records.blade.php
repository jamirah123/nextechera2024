@props(['panels', 'title' => 'Related records'])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-slate-200 bg-white shadow-sm']) }}>
    <div class="border-b border-slate-100 px-3 py-2.5">
        <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
        <p class="mt-0.5 text-sm text-slate-500">Linked deployments, shifts, finance and organisation records.</p>
    </div>

    @if (empty($panels))
        <p class="px-4 py-5 text-sm text-slate-500">No related records to show.</p>
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($panels as $panel)
                <div class="px-3 py-3 sm:px-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900">{{ $panel['title'] }}</h3>
                        @if ($panel['href'] ?? null)
                            <a href="{{ $panel['href'] }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">View all</a>
                        @endif
                    </div>
                    <ul class="mt-2 space-y-2">
                        @foreach ($panel['items'] as $item)
                            <li>
                                @if ($item['href'] ?? null)
                                    <a href="{{ $item['href'] }}" class="flex items-start justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50">
                                @else
                                    <div class="flex items-start justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2">
                                @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $item['label'] }}</p>
                                        @if ($item['meta'] ?? null)
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $item['meta'] }}</p>
                                        @endif
                                    </div>
                                    @if ($item['tone'] ?? null)
                                        <span @class([
                                            'mt-1 h-2 w-2 shrink-0 rounded-full',
                                            'bg-emerald-500' => $item['tone'] === 'emerald',
                                            'bg-amber-500' => $item['tone'] === 'amber',
                                            'bg-rose-500' => $item['tone'] === 'rose',
                                            'bg-brand-600' => $item['tone'] === 'brand',
                                            'bg-slate-400' => ! in_array($item['tone'], ['emerald', 'amber', 'rose', 'brand'], true),
                                        ])></span>
                                    @endif
                                @if ($item['href'] ?? null)
                                    </a>
                                @else
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</section>
