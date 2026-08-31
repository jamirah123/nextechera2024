@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')
@section('page-subtitle', 'Configure role-based access across the platform')

@section('content')
<div class="space-y-3">
    <x-page-header title="Roles & permissions" subtitle="Toggle capabilities for each role. Super Admin and Managing Director have fixed access. Changes apply immediately across the application.">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Manage users
            </a>
            <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Platform settings
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <section class="form-card">
        <h2 class="text-sm font-semibold text-slate-900">Clone permissions</h2>
        <p class="mt-1 text-xs text-slate-500">Copy all capabilities from one role to another. Useful when onboarding a new client with a similar org structure.</p>

        <form method="POST" action="{{ route('roles.permissions.clone') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-[12rem]">
                <label for="source_role" class="mb-1 block text-[11px] font-medium text-slate-600">Copy from</label>
                <select id="source_role" name="source_role" required class="block w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs shadow-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('source_role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @error('source_role')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="min-w-[12rem]">
                <label for="target_role" class="mb-1 block text-[11px] font-medium text-slate-600">Apply to</label>
                <select id="target_role" name="target_role" required class="block w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs shadow-sm">
                    @foreach ($roles as $role)
                        @if (! in_array($role, [\App\Enums\UserRole::SuperAdmin, \App\Enums\UserRole::ManagingDirector], true))
                            <option value="{{ $role->value }}" @selected(old('target_role') === $role->value)>{{ $role->label() }}</option>
                        @endif
                    @endforeach
                </select>
                @error('target_role')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Clone permissions
            </button>
        </form>
    </section>

    <form method="POST" action="{{ route('roles.permissions.update') }}">
        @csrf
        @method('PUT')

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-3 py-2.5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Permission matrix</h2>
                    <p class="text-xs text-slate-500">Check a box to grant a capability to a role.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        Save permissions
                    </button>
                    <button
                        type="submit"
                        form="reset-permissions-form"
                        class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        onclick="return confirm('Restore all permissions to the system defaults?');"
                    >
                        Reset defaults
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 min-w-[16rem]">Capability</th>
                            @foreach ($roles as $role)
                                <th class="px-3 py-2 text-center whitespace-nowrap">{{ $role->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php $lastGroup = null; @endphp
                        @foreach ($catalog as $entry)
                            @if ($lastGroup !== $entry['group'])
                                <tr class="bg-slate-50/80">
                                    <td colspan="{{ count($roles) + 1 }}" class="px-5 py-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                        {{ $entry['group'] }}
                                    </td>
                                </tr>
                                @php $lastGroup = $entry['group']; @endphp
                            @endif
                            <tr>
                                <td class="px-3 py-2">
                                    <p class="font-medium text-slate-800">{{ $entry['label'] }}</p>
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ $entry['description'] }}</p>
                                </td>
                                @foreach ($roles as $role)
                                    <td class="px-3 py-2 text-center align-middle">
                                        @if ($role === \App\Enums\UserRole::SuperAdmin)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-50 text-brand-700" title="Always allowed">✓</span>
                                        @elseif ($role === \App\Enums\UserRole::ManagingDirector)
                                            @if (in_array($entry['key'], \App\Enums\UserRole::managingDirectorDeniedPermissions(), true))
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-400" title="Not allowed">—</span>
                                            @else
                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-50 text-violet-700" title="Always allowed">✓</span>
                                            @endif
                                        @else
                                            @php
                                                $checked = in_array($role->value, $grants[$entry['key']] ?? [], true);
                                            @endphp
                                            <label class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-full hover:bg-slate-50">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[{{ $entry['key'] }}][{{ $role->value }}]"
                                                    value="1"
                                                    @checked(old("permissions.{$entry['key']}.{$role->value}", $checked))
                                                    class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                                                >
                                            </label>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>

    <form id="reset-permissions-form" method="POST" action="{{ route('roles.permissions.reset') }}" class="hidden">
        @csrf
    </form>
</div>
@endsection
