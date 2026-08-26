@extends('layouts.app')

@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')
@section('page-subtitle', 'Reference matrix for role-based access')

@section('content')
<div class="space-y-6">
    <x-page-header title="Roles & permissions" subtitle="Capabilities are enforced in policies. This matrix documents what each role can do today.">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Manage users
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($roles as $role)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <x-status-badge :tone="$role->tone()" :label="$role->label()" />
                <p class="mt-3 text-sm text-slate-600">{{ $role->description() }}</p>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ (int) ($counts[$role->value] ?? 0) }} account{{ (int) ($counts[$role->value] ?? 0) === 1 ? '' : 's' }}
                </p>
            </div>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Capability</th>
                        @foreach ($roles as $role)
                            <th class="px-3 py-3 text-center whitespace-nowrap">{{ $role->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $lastGroup = null; @endphp
                    @foreach ($matrix as $row)
                        @if ($lastGroup !== $row['group'])
                            <tr class="bg-slate-50/80">
                                <td colspan="{{ count($roles) + 1 }}" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $row['group'] }}
                                </td>
                            </tr>
                            @php $lastGroup = $row['group']; @endphp
                        @endif
                        <tr>
                            <td class="px-5 py-3.5 font-medium text-slate-800">{{ $row['capability'] }}</td>
                            @foreach ($roles as $role)
                                <td class="px-3 py-3.5 text-center">
                                    @if (in_array($role->value, $row['roles'], true))
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-700" title="Allowed">✓</span>
                                    @else
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-50 text-slate-300" title="Not allowed">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
