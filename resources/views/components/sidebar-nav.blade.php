@props(['navigation' => []])

<nav class="flex flex-1 flex-col gap-1 px-3 py-4">
    @foreach ($navigation as $item)
        @if (! empty($item['children']))
            <div x-data="{ open: {{ ($item['active'] ?? false) ? 'true' : 'false' }} }" class="space-y-1">
                <button
                    type="button"
                    @class([
                        'flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                        'bg-white/10 text-white ring-1 ring-white/10' => $item['active'] ?? false,
                        'text-slate-300 hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                    ])
                    @click="open = !open"
                >
                    <span class="inline-flex items-center gap-3">
                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 opacity-80" />
                        {{ $item['label'] }}
                    </span>
                    <svg class="h-4 w-4 transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div x-cloak x-show="open" class="ml-4 space-y-1 border-l border-white/10 pl-3">
                    @foreach ($item['children'] as $child)
                        <a
                            href="{{ $child['href'] }}"
                            @class([
                                'block rounded-lg px-3 py-2 text-sm transition',
                                'bg-white/10 font-medium text-white' => $child['active'] ?? false,
                                'text-slate-400 hover:bg-white/5 hover:text-white' => ! ($child['active'] ?? false),
                            ])
                        >
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <a
                href="{{ $item['href'] }}"
                @class([
                    'inline-flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                    'bg-white/10 text-white ring-1 ring-white/10' => $item['active'] ?? false,
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                ])
            >
                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 opacity-80" />
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</nav>
