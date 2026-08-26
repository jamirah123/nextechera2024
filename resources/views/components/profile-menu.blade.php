@props(['user'])

<div
    class="relative"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 text-left transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 sm:px-2.5"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
    >
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-950 text-xs font-bold text-white">
            {{ $user->initials() }}
        </span>
        <span class="hidden min-w-0 sm:block">
            <span class="block max-w-[10rem] truncate text-sm font-semibold text-slate-900 lg:max-w-[14rem]">{{ $user->name }}</span>
            <span class="block truncate text-xs text-slate-500">{{ $user->roleLabel() }}</span>
        </span>
        <svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
        role="menu"
    >
        <div class="border-b border-slate-100 bg-slate-50 px-4 py-3">
            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
            <span class="mt-2 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700 ring-1 ring-brand-100">
                {{ $user->roleLabel() }}
            </span>
        </div>

        <div class="p-2">
            <a
                href="{{ route('profile.show') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                role="menuitem"
                @click="open = false"
            >
                <x-icon name="profile" class="h-4 w-4 text-slate-400" />
                View profile
            </a>
            <a
                href="{{ route('profile.edit') }}"
                class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                role="menuitem"
                @click="open = false"
            >
                <x-icon name="settings" class="h-4 w-4 text-slate-400" />
                Account settings
            </a>
        </div>

        <div class="border-t border-slate-100 p-2">
            <form method="POST" action="{{ route('logout') }}" x-data="{ confirming: false }">
                @csrf
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50"
                    role="menuitem"
                    x-show="!confirming"
                    @click="confirming = true"
                >
                    <x-icon name="logout" class="h-4 w-4" />
                    Sign out
                </button>
                <div x-cloak x-show="confirming" class="space-y-2 rounded-xl bg-rose-50 p-3">
                    <p class="text-xs leading-relaxed text-rose-800">Sign out of {{ config('psg.company') }} on this device?</p>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="flex-1 rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700"
                        >
                            Confirm
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                            @click="confirming = false"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
