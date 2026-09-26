@props([
    'groups' => [],
    'user' => null,
])

@php
    $user = $user ?? auth()->user();
    $activeGroupKeys = collect($groups)
        ->filter(fn (array $group) => collect($group['items'])->contains(fn (array $item) => ($item['active'] ?? false)
            || collect($item['children'] ?? [])->contains(fn (array $child) => $child['active'] ?? false)))
        ->pluck('key')
        ->values()
        ->all();
@endphp

<nav
    class="flex h-full min-h-0 flex-col"
    x-data="sidebarNav({
        activeGroups: @js($activeGroupKeys),
        storageKey: 'psg.sidebar.groups',
        haystacks: @js(collect($groups)->flatMap(fn ($g) => collect($g['items'])->map(function ($item) {
            return strtolower($item['label'].' '.collect($item['children'] ?? [])->pluck('label')->implode(' '));
        }))->values()->all()),
    })"
    @keydown.escape.window="accountOpen = false"
>
    {{-- Module search (expanded only) --}}
    <div class="shrink-0 px-2.5 pb-2" x-show="! $store.sidebar.collapsed" x-cloak>
        <label class="relative block">
            <span class="sr-only">Search modules</span>
            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
            <input
                type="search"
                x-model="query"
                placeholder="Search modules…"
                class="w-full rounded-lg border border-white/10 bg-white/5 py-1.5 pl-8 pr-2.5 text-xs text-white placeholder:text-slate-500 outline-none transition focus:border-brand-400/40 focus:bg-white/10"
            >
        </label>
    </div>

    {{-- Scrollable groups --}}
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 pb-2">
        <div class="space-y-3">
            @foreach ($groups as $group)
                @php
                    $groupItemsJson = collect($group['items'])->map(function (array $item) {
                        $labels = [$item['label']];
                        foreach ($item['children'] ?? [] as $child) {
                            $labels[] = $child['label'];
                        }

                        return [
                            'label' => $item['label'],
                            'haystack' => strtolower(implode(' ', $labels)),
                        ];
                    })->values()->all();
                @endphp
                <div
                    x-show="groupVisible(@js($groupItemsJson))"
                    class="space-y-0.5"
                >
                    <button
                        type="button"
                        class="flex w-full items-center gap-1 rounded-md px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-500 transition hover:text-slate-300"
                        x-show="! $store.sidebar.collapsed"
                        @click="toggleGroup(@js($group['key']), ! isGroupOpen(@js($group['key'])))"
                    >
                        <span>{{ $group['label'] }}</span>
                        <svg class="h-3 w-3 transition" :class="isGroupOpen(@js($group['key'])) ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    <div
                        x-show="$store.sidebar.collapsed || isGroupOpen(@js($group['key']))"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-0.5"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="space-y-0.5"
                    >
                        @foreach ($group['items'] as $item)
                            @php
                                $itemHaystack = strtolower($item['label'].' '.collect($item['children'] ?? [])->pluck('label')->implode(' '));
                            @endphp

                            @if (! empty($item['children']))
                                <div
                                    x-show="itemMatches(@js($itemHaystack))"
                                    x-data="{ childOpen: {{ ($item['active'] ?? false) ? 'true' : 'false' }} }"
                                    class="space-y-0.5"
                                >
                                    <button
                                        type="button"
                                        @class([
                                            'group relative flex w-full items-center gap-2.5 rounded-lg text-xs font-medium transition',
                                            'bg-white/10 text-white' => $item['active'] ?? false,
                                            'text-slate-300 hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                                        ])
                                        :class="$store.sidebar.collapsed ? 'justify-center px-0 py-2' : 'px-2.5 py-2'"
                                        @click="$store.sidebar.collapsed ? $store.sidebar.expand() : (childOpen = ! childOpen)"
                                        :title="$store.sidebar.collapsed ? @js($item['label']) : null"
                                    >
                                        @if ($item['active'] ?? false)
                                            <span class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-brand-400" aria-hidden="true"></span>
                                        @endif
                                        <x-icon :name="$item['icon']" @class([
                                            'h-4 w-4 shrink-0',
                                            'text-brand-300' => $item['active'] ?? false,
                                            'opacity-80' => ! ($item['active'] ?? false),
                                        ]) />
                                        <span class="min-w-0 flex-1 truncate text-left" x-show="! $store.sidebar.collapsed" x-cloak>{{ $item['label'] }}</span>
                                        <svg class="h-3.5 w-3.5 shrink-0 transition" x-show="! $store.sidebar.collapsed" :class="childOpen ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    <div
                                        x-show="! $store.sidebar.collapsed && childOpen"
                                        x-transition
                                        class="ml-3 space-y-0.5 border-l border-white/10 pl-2"
                                    >
                                        @foreach ($item['children'] as $child)
                                            <a
                                                href="{{ $child['href'] }}"
                                                x-show="itemMatches(@js(strtolower($child['label'])))"
                                                @class([
                                                    'flex items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-xs transition',
                                                    'bg-white/10 font-semibold text-white' => $child['active'] ?? false,
                                                    'text-slate-400 hover:bg-white/5 hover:text-white' => ! ($child['active'] ?? false),
                                                ])
                                                @click="$dispatch('sidebar-navigate')"
                                            >
                                                <span class="truncate">{{ $child['label'] }}</span>
                                                @if (! empty($child['badge']))
                                                    <span class="rounded-full bg-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold tabular-nums text-rose-200 ring-1 ring-rose-400/20">{{ $child['badge'] > 99 ? '99+' : $child['badge'] }}</span>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a
                                    href="{{ $item['href'] }}"
                                    x-show="itemMatches(@js($itemHaystack))"
                                    @class([
                                        'group relative flex items-center gap-2.5 rounded-lg text-xs font-medium transition',
                                        'bg-white/10 font-semibold text-white' => $item['active'] ?? false,
                                        'text-slate-300 hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                                    ])
                                    :class="$store.sidebar.collapsed ? 'justify-center px-0 py-2' : 'px-2.5 py-2'"
                                    :title="$store.sidebar.collapsed ? @js($item['label']) : null"
                                    @click="$dispatch('sidebar-navigate')"
                                >
                                    @if ($item['active'] ?? false)
                                        <span class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-brand-400" aria-hidden="true"></span>
                                    @endif
                                    <x-icon :name="$item['icon']" @class([
                                        'h-4 w-4 shrink-0',
                                        'text-brand-300' => $item['active'] ?? false,
                                        'opacity-80' => ! ($item['active'] ?? false),
                                    ]) />
                                    <span class="min-w-0 flex-1 truncate" x-show="! $store.sidebar.collapsed" x-cloak>{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span
                                            class="rounded-full bg-rose-500/20 px-1.5 py-0.5 text-[9px] font-bold tabular-nums text-rose-200 ring-1 ring-rose-400/20"
                                            x-show="! $store.sidebar.collapsed"
                                            x-cloak
                                        >{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                        <span
                                            class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-rose-400"
                                            x-show="$store.sidebar.collapsed"
                                            x-cloak
                                            aria-hidden="true"
                                        ></span>
                                    @endif
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <p
            x-cloak
            x-show="query.trim() !== '' && ! anyVisible()"
            class="px-2 py-4 text-center text-[11px] text-slate-500"
        >
            No modules match “<span x-text="query.trim()"></span>”
        </p>
    </div>

    {{-- Account footer --}}
    @if ($user)
        <div class="relative shrink-0 border-t border-white/10 p-2">
            <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left transition hover:bg-white/5"
                :class="$store.sidebar.collapsed ? 'justify-center' : ''"
                @click="accountOpen = ! accountOpen"
                :title="$store.sidebar.collapsed ? @js($user->name) : null"
                :aria-expanded="accountOpen.toString()"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-[11px] font-bold text-white">
                    {{ $user->initials() }}
                </span>
                <span class="min-w-0 flex-1" x-show="! $store.sidebar.collapsed" x-cloak>
                    <span class="block truncate text-xs font-semibold text-white">{{ $user->name }}</span>
                    <span class="block truncate text-[10px] text-slate-400">{{ $user->roleLabel() }}</span>
                </span>
                <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" x-show="! $store.sidebar.collapsed" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                </svg>
            </button>

            <div
                x-cloak
                x-show="accountOpen"
                x-transition.origin.bottom.left
                class="absolute bottom-[calc(100%+0.35rem)] left-2 right-2 z-50 overflow-hidden rounded-xl border border-white/10 bg-steel-900 shadow-2xl"
                @click.outside="accountOpen = false"
            >
                <div class="border-b border-white/10 px-3 py-2.5">
                    <p class="truncate text-xs font-semibold text-white">{{ $user->name }}</p>
                    <p class="truncate text-[10px] text-slate-400">{{ $user->email }}</p>
                </div>
                <div class="p-1.5">
                    <a href="{{ route('profile.show') }}" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs text-slate-300 hover:bg-white/5 hover:text-white" @click="accountOpen = false; $dispatch('sidebar-navigate')">
                        <x-icon name="profile" class="h-3.5 w-3.5" /> Profile
                    </a>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs text-slate-300 hover:bg-white/5 hover:text-white" @click="accountOpen = false; $dispatch('sidebar-navigate')">
                        <x-icon name="settings" class="h-3.5 w-3.5" /> Account settings
                    </a>
                </div>
                <div class="border-t border-white/10 p-1.5">
                    <form method="POST" action="{{ route('logout') }}" x-data="{ confirming: false }">
                        @csrf
                        <button type="button" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-rose-300 hover:bg-rose-500/10" x-show="!confirming" @click="confirming = true">
                            <x-icon name="logout" class="h-3.5 w-3.5" /> Sign out
                        </button>
                        <div x-cloak x-show="confirming" class="space-y-2 rounded-lg bg-rose-500/10 p-2.5">
                            <p class="text-[11px] text-rose-100">End this session?</p>
                            <div class="flex gap-1.5">
                                <button type="submit" class="flex-1 rounded-md bg-rose-600 px-2 py-1.5 text-[11px] font-semibold text-white hover:bg-rose-500">Confirm</button>
                                <button type="button" class="flex-1 rounded-md bg-white/5 px-2 py-1.5 text-[11px] font-semibold text-slate-200 hover:bg-white/10" @click="confirming = false">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</nav>
