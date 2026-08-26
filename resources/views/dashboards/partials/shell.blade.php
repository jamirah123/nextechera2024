{{-- Shared dashboard sections --}}
@php
    /** @var \App\Models\User $user */
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-steel-950 via-brand-950 to-brand-800 p-5 text-white shadow-sm sm:p-7">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-300">{{ $eyebrow ?? 'Operations Dashboard' }}</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ $greeting ?? 'Welcome back' }}, {{ $user->name }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-300">
                {{ $intro ?? $user->role?->description() }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold ring-1 ring-white/15">
                {{ $user->roleLabel() }}
            </span>
            <span class="inline-flex items-center rounded-full bg-emerald-400/15 px-3 py-1.5 text-xs font-semibold text-emerald-200 ring-1 ring-emerald-300/20">
                Account active
            </span>
        </div>
    </div>
</section>

<section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 sm:gap-4">
    @foreach ($kpis as $kpi)
        <x-kpi-card
            :label="$kpi['label']"
            :value="$kpi['value']"
            :hint="$kpi['hint']"
            :tone="$kpi['tone']"
        />
    @endforeach
</section>

<section class="mt-6 sm:mt-8">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Your modules</h2>
            <p class="text-sm text-slate-500">Role-based tools for {{ $user->roleLabel() }} workflows.</p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 sm:gap-4">
        @foreach ($modules as $module)
            <x-module-card
                :title="$module['title']"
                :description="$module['description']"
                :icon="$module['icon']"
                :href="$module['href']"
                :badge="$module['badge'] ?? null"
                :tone="$module['tone']"
            />
        @endforeach
    </div>
</section>
