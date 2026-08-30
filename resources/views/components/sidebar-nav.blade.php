@props(['navigation' => []])

<nav class="flex flex-1 flex-col gap-0.5 px-2 py-2">
    @foreach ($navigation as $item)
        @if (! empty($item['children']))
            <div x-data="{ open: {{ ($item['active'] ?? false) ? 'true' : 'false' }} }" class="space-y-0.5">
                <button
                    type="button"
                    @class([
                        'flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-xs font-medium transition',
                        'bg-white/10 text-white ring-1 ring-white/10' => $item['active'] ?? false,
                        'text-slate-300 hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                    ])
                    @click="open = !open"
                >
                    <span class="inline-flex items-center gap-2">
                        <x-icon :name="$item['icon']" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                        {{ $item['label'] }}
                    </span>
                    <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div x-cloak x-show="open" class="ml-3 space-y-0.5 border-l border-white/10 pl-2">
                    @foreach ($item['children'] as $child)
                        <a
                            href="{{ $child['href'] }}"
                            @class([
                                'block rounded-md px-2.5 py-1.5 text-xs transition',
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
                    'inline-flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium transition',
                    'bg-white/10 text-white ring-1 ring-white/10' => $item['active'] ?? false,
                    'text-slate-300 hover:bg-white/5 hover:text-white' => ! ($item['active'] ?? false),
                ])
            >
                <x-icon :name="$item['icon']" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</nav>
