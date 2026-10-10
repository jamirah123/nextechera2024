@extends('layouts.app')

@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-subtitle', $unreadCount === 1 ? '1 unread' : ($unreadCount > 99 ? '99+ unread' : $unreadCount.' unread'))

@section('content')
<div class="space-y-3">
    <x-page-header title="Notifications" subtitle="Alerts for your role. Dismissed items stay in history and are not deleted." size="sm">
        <x-slot:actions>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark-read') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Mark all as read</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <form method="GET" action="{{ route('notifications.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <x-form-field label="Search" name="q" :value="$filters['q'] ?? ''" placeholder="Title or reference" />
            <x-form-field label="From" name="from" type="date" :value="$filters['from'] ?? ''" />
            <x-form-field label="To" name="to" type="date" :value="$filters['to'] ?? ''" />
            <x-form-field label="Category" name="group" type="select">
                <option value="">All categories</option>
                @foreach ([
                    'deployments' => 'Deployments',
                    'manpower' => 'Manpower',
                    'operations' => 'Operations',
                    'hr' => 'HR & leave',
                    'payroll' => 'Payroll',
                    'finance' => 'Finance',
                    'system' => 'System & security',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['group'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Priority" name="priority" type="select">
                <option value="">All priorities</option>
                @foreach (['normal' => 'Normal', 'important' => 'Important', 'urgent' => 'Urgent'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </x-form-field>
            <x-form-field label="Status" name="read" type="select">
                <option value="">All</option>
                <option value="unread" @selected(($filters['read'] ?? '') === 'unread')>Unread</option>
                <option value="read" @selected(($filters['read'] ?? '') === 'read')>Read</option>
            </x-form-field>
            <div class="flex items-end gap-2 sm:col-span-2">
                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('notifications.index') }}" class="btn btn-secondary">Reset</a>
                @if (($filters['status'] ?? '') === 'dismissed')
                    <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-brand-700">Show active</a>
                @else
                    <a href="{{ route('notifications.index', ['status' => 'dismissed']) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Dismissed</a>
                @endif
            </div>
        </form>
    </section>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @if ($notifications->isEmpty())
                <div class="px-6 py-14 text-center">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                        <x-icon name="bell" class="h-5 w-5" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100">You're all caught up</p>
                    <p class="mt-1 text-xs text-slate-500">No notifications match this view.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($notifications as $item)
                        <li class="px-3 py-2.5 {{ $item['is_unread'] ? 'bg-slate-50 dark:bg-slate-800/60' : '' }}">
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" title="{{ $item['group'] }}">
                                    <x-icon :name="$item['icon'] ?? 'bell'" class="h-3.5 w-3.5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $item['category_badge_class'] }}">
                                            @if ($item['is_unread'])
                                                <span class="h-1.5 w-1.5 rounded-full {{ $item['priority'] === 'urgent' ? 'bg-rose-600' : ($item['priority'] === 'important' ? 'bg-amber-600' : 'bg-brand-600') }}"></span>
                                            @endif
                                            {{ $item['group'] }}
                                        </span>
                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $item['priority_label'] }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $item['time_ago'] }}</span>
                                    </div>
                                    <p class="mt-1 text-xs font-semibold text-slate-900 dark:text-slate-100">{{ $item['title'] }}</p>
                                    @if ($item['body'])
                                        <p class="mt-0.5 text-[11px] leading-4 text-slate-600 dark:text-slate-300">{{ $item['body'] }}</p>
                                    @endif
                                    @if (! empty($item['reference']))
                                        <p class="mt-0.5 text-[10px] font-medium text-slate-400">{{ $item['reference'] }}</p>
                                    @endif
                                    <p class="mt-1 text-[10px] text-slate-500">{{ $item['actor'] }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    @if ($item['url'])
                                        <form method="POST" action="{{ $item['state_url'] }}">
                                            @csrf
                                            <input type="hidden" name="action" value="open">
                                            <button type="submit" class="text-[11px] font-semibold text-brand-700 hover:text-brand-800">{{ $item['action_label'] ?? 'Open' }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ $item['state_url'] }}">
                                        @csrf
                                        <input type="hidden" name="action" value="{{ $item['is_unread'] ? 'read' : 'unread' }}">
                                        <button type="submit" class="text-[11px] font-semibold text-slate-500 hover:text-slate-800">{{ $item['is_unread'] ? 'Mark read' : 'Mark unread' }}</button>
                                    </form>
                                    @if (($filters['status'] ?? '') !== 'dismissed')
                                        <form method="POST" action="{{ $item['state_url'] }}">
                                            @csrf
                                            <input type="hidden" name="action" value="dismiss">
                                            <button type="submit" class="text-[11px] font-semibold text-slate-400 hover:text-rose-700">Dismiss</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">
                    <x-table-pagination :paginator="$notifications" />
                </div>
            @endif
        </section>

        <section class="h-fit rounded-lg border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-xs font-semibold text-slate-900 dark:text-slate-100">Preferences</h2>
            <p class="mt-1 text-[11px] leading-4 text-slate-500">These choices apply only to your account. Backup failures and critical security alerts stay on for the roles that receive them.</p>
            <form method="POST" action="{{ route('notifications.preferences') }}" class="mt-3 space-y-2">
                @csrf
                @foreach ([
                    'in_app' => 'In-app notifications',
                    'email' => 'Email notifications',
                    'toasts' => 'Important toasts',
                    'operational' => 'Deployments and manpower',
                    'hr' => 'HR and leave',
                    'finance' => 'Finance and payroll',
                    'system' => 'System and security',
                ] as $name => $label)
                    <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="{{ $name }}" value="1" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600" @checked($preferences[$name] ?? false)>
                        {{ $label }}
                    </label>
                @endforeach
                <button type="submit" class="btn btn-primary mt-2">Save preferences</button>
            </form>
        </section>
    </div>
</div>
@endsection
