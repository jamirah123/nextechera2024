{{-- Compact KPI row only — navigation lives in the sidebar --}}
<section class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($kpis as $kpi)
        <x-kpi-card
            :label="$kpi['label']"
            :value="$kpi['value']"
            :hint="$kpi['hint']"
            :tone="$kpi['tone']"
        />
    @endforeach
</section>
