@if (! empty($charts))
    <section
        id="dashboard-statistics"
        class="mt-3 grid gap-3 lg:grid-cols-2"
        x-data="dashboardCharts()"
        x-init="mount(@js($charts))"
    >
        @foreach ($charts as $chart)
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $chart['title'] }}</h2>
                    <p class="text-xs text-slate-500">{{ $chart['subtitle'] }}</p>
                </div>
                <div class="h-56 p-3">
                    <canvas id="chart-{{ $chart['id'] }}" aria-label="{{ $chart['title'] }}"></canvas>
                </div>
            </div>
        @endforeach
    </section>
@endif
