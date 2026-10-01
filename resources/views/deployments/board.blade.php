@extends('layouts.app')

@section('title', 'Site posting board')
@section('page-title', 'Site posting board')
@section('page-subtitle', 'Post company guards to client sites — Day/Night cover opens a duty; Rotating stays until transfer or end')

@section('content')
<div
    class="space-y-3"
    x-data="{
        selected: 0,
        defaults: {
            site_id: '',
            shift_type: @js(\App\Enums\DeploymentShiftType::Day->value),
            duty_type: @js(\App\Enums\ShiftType::Normal->value),
        },
        activeChecks() {
            const isDesktop = window.matchMedia('(min-width: 1024px)').matches;
            const selector = isDesktop ? '[data-board-viewport=desktop]' : '[data-board-viewport=mobile]';
            const viewport = this.$root.querySelector(selector);
            return viewport ? viewport.querySelectorAll('[data-row-check]') : this.$root.querySelectorAll('[data-row-check]');
        },
        sync() {
            if (window.psgBoardSyncVisible) window.psgBoardSyncVisible();
            this.selected = window.psgBoardCount ? window.psgBoardCount() : 0;
            if (window.psgPaintBoardAll) window.psgPaintBoardAll();
            if (window.psgBoardRenderPanel) window.psgBoardRenderPanel();
            this.paintSummary();
        },
        toggleAll(checked) {
            this.activeChecks().forEach((el) => { el.checked = checked; });
            this.sync();
        },
        applyDefaultSite() {
            if (! this.defaults.site_id) return;
            const siteId = String(this.defaults.site_id);
            this.$root.querySelectorAll('[data-row-site]').forEach((el) => {
                const option = Array.from(el.options).find((o) => String(o.value) === siteId);
                if (! option || option.disabled) return;
                const siteRegion = option.getAttribute('data-region-id');
                const guardRegion = el.getAttribute('data-region-id');
                if (siteRegion && guardRegion && String(siteRegion) !== String(guardRegion)) return;
                el.value = siteId;
            });
            this.focusSite = '';
            this.sync();
        },
        applyDefaultType() {
            this.$root.querySelectorAll('[data-row-type]').forEach((el) => { el.value = this.defaults.shift_type; });
            this.$root.querySelectorAll('[data-row-duty]').forEach((el) => { el.value = this.defaults.duty_type; });
            this.focusShift = '';
            this.sync();
        },
        historical: @js($isHistorical),
        overstaffOpen: @js(session()->has('overstaffing_warnings')),
        overstaffWarnings: @js(array_values(session('overstaffing_warnings', []))),
        focusSite: '',
        focusShift: '',
        summary: { visible: false, blocks: [], notes: [] },
        refreshAll() {
            if (window.psgPaintBoardAll) window.psgPaintBoardAll();
            this.paintSummary();
        },
        paintSummary() {
            const groups = window.psgBoardGroups ? window.psgBoardGroups() : [];
            if (this.defaults.site_id && window.psgBoardFigures) {
                const shift = this.focusShift || this.defaults.shift_type || 'day';
                const siteId = String(this.focusSite || this.defaults.site_id);
                if (! groups.some((group) => group.siteId === siteId)) {
                    const extra = window.psgBoardFigures(siteId, shift);
                    if (extra) groups.unshift(extra);
                }
            }
            if (! groups.length) {
                this.summary = { visible: false, blocks: [], notes: [] };
                return;
            }
            const notes = [];
            const blocks = groups.map((group) => {
                const lines = [];
                group.lines.forEach((line) => {
                    if (! line.required) {
                        lines.push(line.label + ' — Required: not set');
                        return;
                    }
                    let text = line.label + ' — Required: ' + line.required
                        + ' | Selected/Deployed: ' + line.selected + '/' + line.required
                        + ' | Left: ' + line.left;
                    if (line.additional > 0) text += ' | Additional: ' + line.additional;
                    lines.push(text);
                    lines.push('Normal ' + line.normal + ' · OT ' + line.ot + ' · New ' + line.newlySelected + ' · Operational ' + line.operational + '/' + line.required + ' · Deficit ' + line.deficit);
                    if (line.fulfilled && line.additional === 0) {
                        notes.push(group.name + ' ' + line.label + ' manpower requirement fulfilled.');
                    }
                    if (line.additional > 0 && line.normalNew > 0 && (line.normal + line.normalNew) > line.required) {
                        notes.push(this.historical
                            ? group.name + ' ' + line.label + ' selection is additional coverage for this duty date.'
                            : 'Manpower requirement already fulfilled for ' + group.name + ' ' + line.label + '. A Normal posting above it will be skipped. Choose Overtime for additional cover.');
                    }
                    if (line.newlySelected > 0 && line.additional > 0) {
                        notes.push(group.name + ' ' + line.label + ' would be ' + line.selected + '/' + line.required + ' — ' + line.additional + ' above the requirement. Deploying will ask you to confirm.');
                    }
                });
                const requiredLines = group.lines.filter((line) => line.required > 0);
                const tone = group.lines.some((line) => line.additional > 0)
                    ? 'extra'
                    : (requiredLines.length > 0 && requiredLines.every((line) => line.fulfilled) ? 'fulfilled' : '');
                return {
                    title: group.name,
                    lines,
                    tone,
                };
            });
            this.summary = { visible: true, blocks, notes };
        },
        onBoardChange(event) {
            const el = event.target.closest?.('[data-row-site], [data-row-type], [data-row-duty], [data-row-check]') || event.target;
            if (! el.matches?.('[data-row-site], [data-row-type], [data-row-duty], [data-row-check]')) return;
            const row = el.closest('tr, article');
            if (row) {
                const site = row.querySelector('[data-row-site]');
                if (site?.value) {
                    this.focusSite = site.value;
                    this.focusShift = row.querySelector('[data-row-type]')?.value || 'day';
                }
            }
            this.sync();
        }
    }"
    x-init="window.psgBoardApplyVisible && window.psgBoardApplyVisible(); sync(); this.$watch('defaults.site_id', () => { this.focusSite = ''; this.paintSummary(); }); this.$watch('defaults.shift_type', () => { this.focusShift = ''; this.paintSummary(); }); window.addEventListener('resize', () => { window.psgBoardApplyVisible && window.psgBoardApplyVisible(); this.sync(); })"
    @change="onBoardChange($event)"
    data-posting-board
    @if (session('status') && ! session('deployment_errors')) data-clear-board-selection="1" @endif
>
    <x-page-header
        title="Site posting board"
        subtitle="A guard stays on this board while the day or night shift on the selected date is still open."
        :back="route('deployments.index')"
    />

    <section class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
        @foreach ([
            ['On the board', number_format($stats['awaiting']), 'text-amber-700'],
            ['Active', number_format($stats['active']), 'text-emerald-700'],
            ['Day', number_format($stats['day']), 'text-amber-800'],
            ['Night', number_format($stats['night']), 'text-indigo-700'],
        ] as [$label, $value, $tone])
            <div class="flex min-h-[3.75rem] flex-col justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="truncate text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</p>
                <p class="mt-0.5 text-base font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    @if ($regions->count() > 1)
        <section class="form-card">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Available by region</p>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                <a
                    href="{{ route('deployments.board', array_filter(['q' => $filters['q'] ?? null, 'start_date' => $filters['start_date'] ?? null])) }}"
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ empty($filters['region_id']) ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' }}"
                >
                    All · {{ number_format($stats['awaiting']) }}
                </a>
                @foreach ($regions as $region)
                    @php $count = (int) ($regionCounts[$region->id] ?? 0); @endphp
                    <a
                        href="{{ route('deployments.board', array_filter(['region_id' => $region->id, 'q' => $filters['q'] ?? null, 'start_date' => $filters['start_date'] ?? null])) }}"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold {{ (string) ($filters['region_id'] ?? '') === (string) $region->id ? 'bg-brand-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600' }}"
                    >
                        {{ $region->name }} · {{ number_format($count) }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="filter-bar rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <form method="GET" action="{{ route('deployments.board') }}" x-data x-ref="filterForm" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:items-end">
            <x-form-field label="Duty date" name="start_date" type="date" :value="$filters['start_date'] ?? now()->toDateString()" x-on:change="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Search guard" name="q" type="search" :value="$filters['q'] ?? ''" placeholder="Name or ID" x-on:input.debounce.400ms="$refs.filterForm.requestSubmit()" />
            <x-form-field label="Region" name="region_id" type="select" x-on:change="$refs.filterForm.requestSubmit()">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }} ({{ number_format($regionCounts[$region->id] ?? 0) }})</option>
                @endforeach
            </x-form-field>
            <a href="{{ route('deployments.board') }}" onclick="window.psgBoardClear && window.psgBoardClear()" class="rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Reset filters</a>
        </form>
    </section>

    @if ($isHistorical)
        <p class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
            Historical duty date <strong>{{ \Illuminate\Support\Carbon::parse($dutyDate)->format('d M Y') }}</strong>:
            showing guards with no site posting covering that day. Duty-day status is <strong>Available</strong>
            (their current “Today” status may still be On Duty). Recording a past posting will not change today’s operational status.
        </p>
    @endif

    @if (session('deployment_errors'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">Some rows were skipped</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                @foreach (session('deployment_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <script type="application/json" id="board-manpower">@json($boardManpower)</script>
    <script>
        window.psgBoardManpowerData = function () {
            try {
                return JSON.parse(document.getElementById('board-manpower').textContent || '{}');
            } catch (error) {
                return {};
            }
        };
        window.psgBoardActiveViewport = function () {
            const root = document.querySelector('[data-posting-board]');
            if (! root) return null;
            const desktop = window.matchMedia('(min-width: 1024px)').matches;
            return root.querySelector(desktop ? '[data-board-viewport=desktop]' : '[data-board-viewport=mobile]');
        };
        window.psgBoardSelectionKey = 'psg.posting-board.selection';
        window.psgBoardLoad = function () {
            const root = document.querySelector('[data-posting-board]');
            if (root && root.dataset.clearBoardSelection === '1') {
                sessionStorage.removeItem(window.psgBoardSelectionKey);
                delete root.dataset.clearBoardSelection;
            }
            try {
                const parsed = JSON.parse(sessionStorage.getItem(window.psgBoardSelectionKey) || '{}');
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (error) {
                return {};
            }
        };
        window.psgBoardSave = function (store) {
            try {
                sessionStorage.setItem(window.psgBoardSelectionKey, JSON.stringify(store));
            } catch (error) {
                return;
            }
        };
        window.psgBoardCount = function () {
            return Object.keys(window.psgBoardLoad()).length;
        };
        window.psgBoardCapture = function (row, previous) {
            const check = row.querySelector('[data-row-check]');
            const site = row.querySelector('[data-row-site]');
            const siteOption = site?.selectedOptions?.[0];
            return {
                id: String(check.value),
                name: row.querySelector('[data-guard-name]')?.textContent?.trim() || previous?.name || 'Guard',
                employmentId: row.querySelector('[data-guard-code]')?.textContent?.trim() || previous?.employmentId || '',
                siteId: site?.value || previous?.siteId || '',
                siteName: siteOption && siteOption.value ? siteOption.textContent.trim() : (previous?.siteName || ''),
                shiftType: row.querySelector('[data-row-type]')?.value || previous?.shiftType || 'day',
                dutyType: row.querySelector('[data-row-duty]')?.value || previous?.dutyType || 'normal',
            };
        };
        window.psgBoardSyncRow = function (row) {
            const check = row?.querySelector('[data-row-check]');
            if (! check) return;
            const store = window.psgBoardLoad();
            const id = String(check.value);
            if (check.checked) {
                store[id] = window.psgBoardCapture(row, store[id]);
            } else {
                delete store[id];
            }
            window.psgBoardSave(store);
        };
        window.psgBoardSyncVisible = function () {
            const viewport = window.psgBoardActiveViewport();
            const store = window.psgBoardLoad();
            if (! viewport) return store;
            viewport.querySelectorAll('[data-row-check]').forEach((check) => {
                const row = check.closest('tr, article');
                const id = String(check.value);
                if (! row) return;
                if (check.checked) {
                    store[id] = window.psgBoardCapture(row, store[id]);
                } else {
                    delete store[id];
                }
            });
            window.psgBoardSave(store);
            return store;
        };
        window.psgBoardApplyVisible = function () {
            const store = window.psgBoardLoad();
            document.querySelectorAll('[data-row-check]').forEach((check) => {
                const saved = store[String(check.value)];
                const row = check.closest('tr, article');
                check.checked = Boolean(saved);
                if (! saved || ! row) return;
                const site = row.querySelector('[data-row-site]');
                const type = row.querySelector('[data-row-type]');
                const duty = row.querySelector('[data-row-duty]');
                if (site && saved.siteId && Array.from(site.options).some((option) => option.value === String(saved.siteId) && ! option.disabled)) {
                    site.value = String(saved.siteId);
                }
                if (type && saved.shiftType) type.value = saved.shiftType;
                if (duty && saved.dutyType) duty.value = saved.dutyType;
            });
        };
        window.psgBoardRenderPanel = function () {
            const panel = document.getElementById('board-selected-panel');
            const count = document.getElementById('board-selected-count');
            const list = document.getElementById('board-selected-list');
            if (! panel || ! count || ! list) return;
            const guards = Object.values(window.psgBoardLoad()).sort((a, b) => String(a.name).localeCompare(String(b.name)));
            panel.hidden = guards.length === 0;
            count.textContent = String(guards.length);
            list.replaceChildren();
            guards.forEach((guard) => {
                const item = document.createElement('li');
                item.className = 'inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-800';
                const label = document.createElement('span');
                label.textContent = guard.name + (guard.employmentId ? ' · ' + guard.employmentId : '');
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'px-1 text-sm leading-none text-slate-500 hover:text-slate-900';
                remove.textContent = '×';
                remove.setAttribute('aria-label', 'Remove ' + guard.name);
                remove.addEventListener('click', function () {
                    window.psgBoardRemove(guard.id);
                });
                item.append(label, remove);
                list.append(item);
            });
        };
        window.psgBoardRemove = function (id) {
            const store = window.psgBoardLoad();
            delete store[String(id)];
            window.psgBoardSave(store);
            document.querySelectorAll('[data-row-check]').forEach((check) => {
                if (String(check.value) === String(id)) check.checked = false;
            });
            window.psgPaintBoardAll();
            window.psgBoardRenderPanel();
            const root = document.querySelector('[data-posting-board]');
            if (root && window.Alpine) {
                const state = window.Alpine.$data(root);
                state.selected = window.psgBoardCount();
                state.paintSummary?.();
            }
        };
        window.psgBoardClear = function () {
            window.psgBoardSave({});
            document.querySelectorAll('[data-row-check]').forEach((check) => { check.checked = false; });
            window.psgPaintBoardAll?.();
            window.psgBoardRenderPanel?.();
            const root = document.querySelector('[data-posting-board]');
            if (root && window.Alpine) {
                const state = window.Alpine.$data(root);
                state.selected = 0;
                state.paintSummary?.();
            }
        };
        window.psgBoardSelectionCounts = function () {
            const counts = {};
            Object.values(window.psgBoardLoad()).forEach((guard) => {
                if (! guard.siteId) return;
                const key = String(guard.siteId) + ':' + (guard.shiftType || 'day');
                const bucket = counts[key] ??= { total: 0, normal: 0, ot: 0 };
                bucket.total += 1;
                if (guard.dutyType === 'overtime') bucket.ot += 1;
                else bucket.normal += 1;
            });
            return counts;
        };
        window.psgBoardPeriodKeys = function (shift) {
            if (shift === 'rotating') return ['day', 'night'];
            if (shift === 'night') return ['night'];
            return ['day'];
        };
        window.psgBoardPeriodSelection = function (siteId, period) {
            const counts = window.psgBoardSelectionCache || window.psgBoardSelectionCounts();
            const direct = counts[String(siteId) + ':' + period] || { total: 0, normal: 0, ot: 0 };
            const rotating = counts[String(siteId) + ':rotating'] || { total: 0, normal: 0, ot: 0 };
            return {
                total: (direct.total || 0) + (rotating.total || 0),
                normal: (direct.normal || 0) + (rotating.normal || 0),
            };
        };
        window.psgBoardFigures = function (siteId, shift) {
            const record = window.psgBoardManpowerData()[String(siteId)];
            if (! record) return null;
            return {
                siteId: String(siteId),
                shift: shift || 'day',
                name: record.name,
                code: record.code,
                lines: ['day', 'night'].map((key) => {
                    const period = record[key] || {};
                    const required = period.required || 0;
                    const normal = period.normal || 0;
                    const ot = period.ot || 0;
                    const cover = period.cover || 0;
                    const deployed = normal + ot + cover;
                    const picked = window.psgBoardPeriodSelection(siteId, key);
                    const newlySelected = picked.total || 0;
                    const taken = deployed + newlySelected;
                    return {
                        key,
                        label: key === 'night' ? 'Night' : 'Day',
                        required,
                        selected: taken,
                        newlySelected,
                        left: Math.max(0, required - taken),
                        additional: required > 0 ? Math.max(0, taken - required) : 0,
                        fulfilled: required > 0 && taken >= required,
                        normal,
                        ot,
                        cover,
                        normalNew: picked.normal || 0,
                        operational: required > 0 ? Math.min(required, taken) : taken,
                        deficit: period.deficit || 0,
                    };
                }),
            };
        };
        window.psgBoardGroups = function () {
            const groups = [];
            const seen = {};
            const add = (siteId) => {
                if (! siteId) return;
                const key = String(siteId);
                if (seen[key]) return;
                seen[key] = true;
                const figures = window.psgBoardFigures(siteId);
                if (figures) groups.push(figures);
            };
            const viewport = window.psgBoardActiveViewport();
            viewport?.querySelectorAll('tr, article').forEach((row) => {
                if (! row.querySelector('[data-manpower-indicator]')) return;
                add(row.querySelector('[data-row-site]')?.value);
            });
            Object.values(window.psgBoardLoad()).forEach((guard) => add(guard.siteId));
            return groups;
        };
        window.psgBoardOverstaffing = function () {
            const warnings = [];
            const counts = {};
            Object.values(window.psgBoardLoad()).forEach((guard) => {
                if (! guard.siteId) return;
                const periods = guard.shiftType === 'rotating'
                    ? ['day', 'night']
                    : [guard.shiftType === 'night' ? 'night' : 'day'];
                periods.forEach((period) => {
                    const key = String(guard.siteId) + ':' + period;
                    counts[key] = (counts[key] || 0) + 1;
                });
            });
            const data = window.psgBoardManpowerData();
            Object.entries(counts).forEach(([key, selected]) => {
                const split = key.indexOf(':');
                const siteId = key.slice(0, split);
                const period = key.slice(split + 1);
                const record = data[siteId];
                const row = record ? (record[period] || {}) : null;
                if (! row) return;
                const required = row.required || 0;
                if (required <= 0) return;
                const deployed = (row.normal || 0) + (row.ot || 0) + (row.cover || 0);
                const projected = deployed + selected;
                const excess = Math.max(0, projected - required);
                if (excess <= 0) return;
                warnings.push({
                    site: record.name || 'Site',
                    period: period === 'night' ? 'Night' : 'Day',
                    required,
                    deployed,
                    selected,
                    projected,
                    excess,
                });
            });
            return warnings;
        };
        window.psgBoardConfirmOverstaff = function () {
            const form = document.querySelector('[data-posting-board] form');
            if (! form) return;
            let input = form.querySelector('input[name="acknowledge_overstaffing"]');
            if (! input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'acknowledge_overstaffing';
                form.appendChild(input);
            }
            input.value = '1';
            form.requestSubmit();
        };
        window.psgPaintBoardIndicator = function (row) {
            const indicator = row?.querySelector('[data-manpower-indicator]');
            if (! indicator) return;
            const siteId = row.querySelector('[data-row-site]')?.value || '';
            const shift = row.querySelector('[data-row-type]')?.value || 'day';
            const figures = siteId ? window.psgBoardFigures(siteId, shift) : null;
            indicator.style.whiteSpace = 'normal';
            indicator.style.borderRadius = '0.25rem';
            if (! figures) {
                indicator.textContent = '—';
                indicator.title = 'Choose a site to see its manpower requirement for this duty date.';
                indicator.style.color = '';
                indicator.style.backgroundColor = '';
                indicator.style.padding = '';
                return;
            }
            const extra = figures.lines.some((line) => line.additional > 0);
            const requiredLines = figures.lines.filter((line) => line.required > 0);
            const fulfilled = ! extra && requiredLines.length > 0 && requiredLines.every((line) => line.fulfilled);
            indicator.style.color = extra ? '#b45309' : (fulfilled ? '#047857' : '');
            indicator.style.backgroundColor = extra ? '#fffbeb' : (fulfilled ? '#ecfdf5' : '');
            indicator.style.padding = (extra || fulfilled) ? '0.125rem 0.25rem' : '';
            indicator.textContent = figures.lines.map((line) => {
                if (! line.required) return line.label + ' — Required: not set';
                let text = line.label + ' — Required: ' + line.required
                    + ' | Selected/Deployed: ' + line.selected + '/' + line.required
                    + ' | Left: ' + line.left;
                if (line.additional > 0) text += ' | Additional: ' + line.additional;
                return text;
            }).join(' · ');
            indicator.title = figures.lines.map((line) => {
                return line.label
                    + ' required ' + line.required
                    + ', selected ' + line.selected
                    + ', left ' + line.left
                    + '. Normal ' + line.normal
                    + '. OT ' + line.ot
                    + '. New ' + line.newlySelected
                    + '. Operational ' + line.operational + '/' + line.required
                    + '. Deficit ' + line.deficit
                    + (line.additional > 0 ? '. Additional ' + line.additional : '')
                    + (line.fulfilled && line.additional === 0 ? '. Fulfilled' : '');
            }).join(' ');
        };
        window.psgPaintBoardAll = function () {
            const root = document.querySelector('[data-posting-board]');
            if (! root) return;
            window.psgBoardSelectionCache = window.psgBoardSelectionCounts();
            root.querySelectorAll('[data-manpower-indicator]').forEach((indicator) => {
                const row = indicator.closest('tr, article');
                if (row) window.psgPaintBoardIndicator(row);
            });
            window.psgBoardSelectionCache = null;
        };
        window.psgPaintBoardRow = function (select) {
            const row = select.closest('tr, article');
            if (row) window.psgBoardSyncRow(row);
            window.psgBoardApplyVisible();
            window.psgPaintBoardAll();
            window.psgBoardRenderPanel();
            const root = select.closest('[data-posting-board]');
            if (! root || ! window.Alpine) return;
            const state = window.Alpine.$data(root);
            if (row) {
                const site = row.querySelector('[data-row-site]');
                const type = row.querySelector('[data-row-type]');
                if (site?.value) {
                    state.focusSite = site.value;
                    state.focusShift = type?.value || 'day';
                }
            }
            state.selected = window.psgBoardCount();
            state.paintSummary?.();
        };
    </script>

    <form method="POST" action="{{ route('deployments.board.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="start_date" value="{{ old('start_date', $dutyDate) }}">

            <div class="rounded-lg border border-emerald-200 bg-white p-3 shadow-sm dark:border-emerald-800 dark:bg-slate-800">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Duty date
                            <input type="date" value="{{ $dutyDate }}" disabled class="mt-0.5 block h-[1.875rem] w-full rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium leading-tight text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                        </label>
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default site
                            <x-board-select x-model="defaults.site_id" class="mt-0.5">
                                <option value="">Choose a site…</option>
                                @foreach ($regions as $region)
                                    @php $regionSites = $sitesByRegion->get((int) $region->id, collect()); @endphp
                                    @if ($regionSites->isNotEmpty())
                                        <optgroup label="{{ $region->name }} ({{ $regionSites->count() }})">
                                            @foreach ($regionSites as $site)
                                                <option value="{{ $site->id }}" data-region-id="{{ $site->region_id }}">
                                                    {{ $site->name }} · {{ $site->code }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default posting type
                            <x-board-select x-model="defaults.shift_type" class="mt-0.5">
                                @foreach ($shiftTypes as $type)
                                    <option value="{{ $type->value }}">
                                        @if ($type === \App\Enums\DeploymentShiftType::Day)
                                            Day posting
                                        @elseif ($type === \App\Enums\DeploymentShiftType::Night)
                                            Night posting
                                        @else
                                            Rotating posting
                                        @endif
                                    </option>
                                @endforeach
                            </x-board-select>
                        </label>
                        <label class="block min-w-0 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Default duty (Normal / OT)
                            <x-board-select x-model="defaults.duty_type" class="mt-0.5">
                                <option value="{{ \App\Enums\ShiftType::Normal->value }}">Normal</option>
                                <option value="{{ \App\Enums\ShiftType::Overtime->value }}">Overtime</option>
                            </x-board-select>
                        </label>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="applyDefaultSite(); applyDefaultType()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-700">Apply to page</button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="selected === 0"
                        >
                            Deploy selected
                            <span class="rounded-md bg-white/20 px-1.5 py-0.5 text-xs" x-text="selected"></span>
                        </button>
                    </div>
                </div>
                <div
                    x-show="summary.visible"
                    x-cloak
                    class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-600 dark:bg-slate-900"
                >
                    <template x-for="(block, blockIndex) in summary.blocks" :key="'block-' + blockIndex">
                        <div class="mt-2 first:mt-0">
                            <p class="text-xs font-semibold text-slate-900 dark:text-slate-100" :style="block.tone === 'fulfilled' ? 'color:#047857' : (block.tone === 'extra' ? 'color:#b45309' : '')" x-text="block.title"></p>
                            <template x-for="(line, lineIndex) in block.lines" :key="'line-' + blockIndex + '-' + lineIndex">
                                <p class="mt-0.5 text-[11px] font-medium tabular-nums text-slate-700 dark:text-slate-200" x-text="line"></p>
                            </template>
                        </div>
                    </template>
                    <template x-for="(note, index) in summary.notes" :key="'note-' + index">
                        <p class="mt-1 text-[11px] font-semibold text-amber-800 dark:text-amber-200" x-text="note"></p>
                    </template>
                </div>
                <div id="board-selected-panel" hidden class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-600 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">Selected: <span id="board-selected-count">0</span></p>
                        <button type="button" onclick="window.psgBoardClear()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">Clear selection</button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">Guards stay selected when search, filters, or another page hides them.</p>
                    <ul id="board-selected-list" class="mt-2 flex flex-wrap gap-2"></ul>
                </div>
            </div>

            @if ($guards->isEmpty())
                <x-empty-state
                    title="{{ $isHistorical ? 'No guards free on this duty date' : 'No guards to post' }}"
                    :description="$isHistorical
                        ? 'Every eligible guard already had a posting covering '.$dutyDate.'. Pick another date or review overlapping historical postings. Selected guards are still kept for this deployment.'
                        : 'Every eligible guard is already deployed for both the day and night shifts on this date. Selected guards are still kept for this deployment.'"
                    icon="map"
                />
            @else
            {{-- Mobile cards --}}
            <div class="grid gap-2 lg:hidden" data-board-viewport="mobile">
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600" @change="toggleAll($event.target.checked)">
                    Select all on this page
                </label>

                @foreach ($guards as $guard)
                    <article class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                        <div class="flex items-start gap-2">
                            <input
                                type="checkbox"
                                name="selected[]"
                                value="{{ $guard->id }}"
                                data-row-check
                                class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600"
                                onchange="window.psgPaintBoardRow(this)"
                                @change="sync()"
                            >
                            <div class="min-w-0 flex-1">
                                <p data-guard-name class="text-xs font-medium text-slate-900 dark:text-slate-100">{{ $guard->full_name }}</p>
                                <p data-guard-code class="mt-0.5 text-[10px] text-slate-500">{{ $guard->employment_id }}</p>
                                <p class="mt-1.5 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $guard->region?->name }}</p>
                                <div class="mt-0.5 text-[10px] text-slate-600 dark:text-slate-300">
                                    @include('deployments.partials.board-shift-availability', ['guard' => $guard])
                                </div>
                                @if ($isHistorical)
                                    <p class="text-[9px] text-slate-400">Today: {{ $guard->operational_status->label() }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-2.5 space-y-2 border-t border-slate-100 pt-2.5 dark:border-slate-700">
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Assign to site
                                <x-board-select
                                    name="rows[{{ $guard->id }}][site_id]"
                                    data-row-site
                                    data-region-id="{{ $guard->region_id }}"
                                    onchange="window.psgPaintBoardRow(this)"
                                    class="mt-1 w-full"
                                >
                                    @include('deployments.partials.board-site-options', ['guard' => $guard])
                                </x-board-select>
                            </label>
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Manpower</p>
                                <p data-manpower-indicator class="mt-1 text-xs font-semibold tabular-nums text-slate-800 dark:text-slate-100" title="Choose a site to see its manpower requirement for this duty date.">—</p>
                            </div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Posting type
                                <x-board-select
                                    name="rows[{{ $guard->id }}][shift_type]"
                                    data-row-type
                                    onchange="window.psgPaintBoardRow(this)"
                                    class="mt-1 w-full"
                                >
                                    @foreach ($shiftTypes as $type)
                                        <option value="{{ $type->value }}" @selected($type === \App\Enums\DeploymentShiftType::Day)>
                                            @if ($type === \App\Enums\DeploymentShiftType::Day)
                                                Day posting
                                            @elseif ($type === \App\Enums\DeploymentShiftType::Night)
                                                Night posting
                                            @else
                                                Rotating
                                            @endif
                                        </option>
                                    @endforeach
                                </x-board-select>
                            </label>
                            <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Duty type
                                <x-board-select
                                    name="rows[{{ $guard->id }}][duty_type]"
                                    data-row-duty
                                    onchange="window.psgPaintBoardRow(this)"
                                    class="mt-1 w-full"
                                >
                                    <option value="{{ \App\Enums\ShiftType::Normal->value }}" selected>Normal</option>
                                    <option value="{{ \App\Enums\ShiftType::Overtime->value }}">Overtime</option>
                                </x-board-select>
                            </label>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 lg:block" data-board-viewport="desktop">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-xs dark:divide-slate-700">
                        <thead class="bg-slate-50/90 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                            <tr>
                                <th class="w-10 px-2.5 py-2">
                                    <input type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600" title="Select all on this page" @change="toggleAll($event.target.checked)">
                                </th>
                                <th class="px-2.5 py-2">Guard</th>
                                <th class="px-2.5 py-2">Region</th>
                                <th class="px-2.5 py-2">{{ $isHistorical ? 'Duty-day status' : 'Status' }}</th>
                                <th class="px-2.5 py-2">Assign to site</th>
                                <th class="px-2.5 py-2">Manpower</th>
                                <th class="px-2.5 py-2">Posting type</th>
                                <th class="px-2.5 py-2">Duty type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($guards as $guard)
                                <tr class="transition hover:bg-emerald-50/40 dark:hover:bg-emerald-950/30">
                                    <td class="px-2.5 py-1.5 align-middle">
                                        <input
                                            type="checkbox"
                                            value="{{ $guard->id }}"
                                            data-row-check
                                            data-desktop-check
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-brand-500/30 dark:border-slate-600"
                                            onchange="window.psgPaintBoardRow(this)"
                                            @change="sync()"
                                        >
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <p data-guard-name class="text-xs font-medium text-slate-900 dark:text-slate-100">{{ $guard->full_name }}</p>
                                        <p data-guard-code class="text-[10px] text-slate-500">{{ $guard->employment_id }}</p>
                                    </td>
                                    <td class="px-2.5 py-1.5 text-slate-600 dark:text-slate-300">{{ $guard->region?->name }}</td>
                                    <td class="px-2.5 py-1.5 text-[10px] text-slate-600 dark:text-slate-300">
                                        @include('deployments.partials.board-shift-availability', ['guard' => $guard])
                                        @if ($isHistorical)
                                            <p class="text-[9px] text-slate-400">Today: {{ $guard->operational_status->label() }}</p>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][site_id]"
                                            data-row-site
                                            data-region-id="{{ $guard->region_id }}"
                                            onchange="window.psgPaintBoardRow(this)"
                                            :compact="true"
                                            class="min-w-[14rem]"
                                        >
                                            @include('deployments.partials.board-site-options', ['guard' => $guard])
                                        </x-board-select>
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <span data-manpower-indicator class="block text-[10px] font-semibold tabular-nums leading-snug text-slate-700 dark:text-slate-200" title="Choose a site to see its manpower requirement for this duty date.">—</span>
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][shift_type]"
                                            data-row-type
                                            onchange="window.psgPaintBoardRow(this)"
                                            :compact="true"
                                        >
                                            @foreach ($shiftTypes as $type)
                                                <option value="{{ $type->value }}" @selected($type === \App\Enums\DeploymentShiftType::Day)>
                                                    @if ($type === \App\Enums\DeploymentShiftType::Day)
                                                        Day posting
                                                    @elseif ($type === \App\Enums\DeploymentShiftType::Night)
                                                        Night posting
                                                    @else
                                                        Rotating
                                                    @endif
                                                </option>
                                            @endforeach
                                        </x-board-select>
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <x-board-select
                                            name="rows[{{ $guard->id }}][duty_type]"
                                            data-row-duty
                                            onchange="window.psgPaintBoardRow(this)"
                                            :compact="true"
                                        >
                                            <option value="{{ \App\Enums\ShiftType::Normal->value }}" selected>Normal</option>
                                            <option value="{{ \App\Enums\ShiftType::Overtime->value }}">Overtime</option>
                                        </x-board-select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing {{ $guards->firstItem() ?? 0 }}–{{ $guards->lastItem() ?? 0 }} of {{ number_format($guards->total()) }} guards with a shift still open
                    @if (empty($filters['region_id']))
                        across all regions
                    @endif
                </p>
                <x-table-pagination :paginator="$guards" :summary="false" />
            </div>
            @endif

            <script>
                document.currentScript.closest('form').addEventListener('submit', function (event) {
                    const form = event.currentTarget;
                    if (window.psgBoardSyncVisible) window.psgBoardSyncVisible();
                    const acknowledged = form.querySelector('input[name="acknowledge_overstaffing"]')?.value === '1';
                    const warnings = window.psgBoardOverstaffing ? window.psgBoardOverstaffing() : [];
                    if (warnings.length && ! acknowledged) {
                        event.preventDefault();
                        const root = form.closest('[data-posting-board]');
                        if (root && window.Alpine) {
                            const state = window.Alpine.$data(root);
                            state.overstaffWarnings = warnings;
                            state.overstaffOpen = true;
                        }
                        return;
                    }
                    const guards = Object.values(window.psgBoardLoad ? window.psgBoardLoad() : {});

                    form.querySelectorAll('input[data-synced-selected]').forEach((el) => el.remove());
                    form.querySelectorAll('[data-row-check], [data-row-site], [data-row-type], [data-row-duty]').forEach((el) => {
                        el.disabled = true;
                    });

                    const append = (name, value) => {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = name;
                        hidden.value = value;
                        hidden.setAttribute('data-synced-selected', '1');
                        form.appendChild(hidden);
                    };

                    guards.forEach((guard) => {
                        append('selected[]', guard.id);
                        append('rows[' + guard.id + '][site_id]', guard.siteId || '');
                        append('rows[' + guard.id + '][shift_type]', guard.shiftType || 'day');
                        append('rows[' + guard.id + '][duty_type]', guard.dutyType || 'normal');
                    });

                    if (guards.length === 0) {
                        event.preventDefault();
                    }
                });
            </script>
        </form>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="overstaffOpen"
            class="fixed inset-0 z-[100] flex items-end justify-center p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="overstaff-title"
            @keydown.escape.window="overstaffOpen = false"
        >
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="overstaffOpen = false"></div>
            <div class="relative max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-lg border border-amber-200 bg-white shadow-2xl dark:border-amber-900 dark:bg-slate-900" @click.stop>
                <div class="border-b border-amber-100 px-4 py-3 dark:border-amber-900/60">
                    <h3 id="overstaff-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">⚠ Overstaffing warning</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        Duty date {{ \Illuminate\Support\Carbon::parse($dutyDate)->format('d M Y') }}.
                        The site would have more guards on shift than its requirement.
                    </p>
                </div>
                <div class="space-y-3 px-4 py-3">
                    <template x-for="(warning, index) in overstaffWarnings" :key="'overstaff-' + index">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                            <p class="font-semibold" x-text="warning.site + ' — ' + warning.period + ' shift'"></p>
                            <dl class="mt-2 space-y-0.5 text-[13px] tabular-nums">
                                <div class="flex justify-between gap-3"><dt>Required manpower</dt><dd x-text="warning.required + (warning.required === 1 ? ' guard' : ' guards')"></dd></div>
                                <div class="flex justify-between gap-3"><dt>Currently deployed</dt><dd x-text="warning.deployed + (warning.deployed === 1 ? ' guard' : ' guards')"></dd></div>
                                <div class="flex justify-between gap-3"><dt>Selected</dt><dd x-text="warning.selected + (warning.selected === 1 ? ' guard' : ' guards')"></dd></div>
                            </dl>
                            <p class="mt-2 font-semibold">After this deployment: <span x-text="warning.projected + ' / ' + warning.required"></span> guards</p>
                            <p class="mt-1" x-text="'This deployment will exceed the site\'s required manpower by ' + warning.excess + (warning.excess === 1 ? ' guard.' : ' guards.')"></p>
                        </div>
                    </template>
                    <p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                        Extra guards are not part of the site requirement. Go back to remove a selection, or deploy only when this extra cover is intentional.
                    </p>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 sm:flex-row sm:justify-end dark:border-slate-800 dark:bg-slate-950/50">
                    <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200" @click="overstaffOpen = false">Go back</button>
                    <button type="button" class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800" onclick="window.psgBoardConfirmOverstaff()">Deploy extra cover</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
