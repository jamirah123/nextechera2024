@props(['logs', 'title' => 'Record history'])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
        <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
        <p class="mt-0.5 text-sm text-slate-500">Immutable audit trail — current state and all past events.</p>
    </div>

    @if ($logs->isEmpty())
        <p class="px-5 py-6 text-sm text-slate-500 sm:px-6">No history events recorded yet.</p>
    @else
        <ol class="divide-y divide-slate-100">
            @foreach ($logs as $log)
                <li class="flex gap-4 px-5 py-4 sm:px-6">
                    <div class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-brand-600 ring-4 ring-brand-50"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-900">{{ $log->summary }}</p>
                            <x-status-badge :tone="$log->severity->tone()" :label="$log->severity->label()" />
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $log->occurredAtLabel() }}
                            · {{ $log->actor_name ?? 'System' }}
                            @if ($log->action) · <span class="font-mono">{{ $log->action }}</span> @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
