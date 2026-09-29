@extends('layouts.app')

@section('title', 'Email delivery')
@section('page-title', 'Email delivery')

@section('content')
<div class="space-y-3">
    <x-page-header title="Email delivery" subtitle="Queued operational messages. Business records are kept even when mail delivery fails." size="sm" />

    <form method="GET" action="{{ route('email-deliveries.index') }}" class="flex flex-wrap items-end gap-3">
        <x-form-field label="Status" name="status" type="select">
            <option value="">All</option>
            @foreach (['queued' => 'Queued', 'sending' => 'Sending', 'sent' => 'Sent', 'retrying' => 'Retrying', 'failed' => 'Failed'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </x-form-field>
        <button type="submit" class="rounded-lg bg-brand-700 px-3 py-2 text-xs font-semibold text-white">Filter</button>
    </form>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <table class="min-w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-2 font-semibold">When</th>
                    <th class="px-3 py-2 font-semibold">Event</th>
                    <th class="px-3 py-2 font-semibold">Recipient</th>
                    <th class="px-3 py-2 font-semibold">Priority</th>
                    <th class="px-3 py-2 font-semibold">Status</th>
                    <th class="px-3 py-2 font-semibold">Attempts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($deliveries as $delivery)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-600 dark:text-slate-300">{{ $delivery->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</td>
                        <td class="px-3 py-2">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $delivery->subject }}</p>
                            <p class="text-slate-500">{{ $delivery->action }}</p>
                        </td>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                            {{ $delivery->recipient?->name ?? 'External' }}
                            <span class="block text-slate-500">{{ $delivery->recipient_email }}</span>
                        </td>
                        <td class="px-3 py-2 capitalize text-slate-700 dark:text-slate-200">{{ $delivery->priority }}</td>
                        <td class="px-3 py-2 capitalize text-slate-700 dark:text-slate-200">
                            {{ $delivery->status }}
                            @if ($delivery->failure_reason)
                                <span class="block text-slate-500">{{ $delivery->failure_reason }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $delivery->attempts }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-slate-500">No email deliveries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{ $deliveries->links() }}
</div>
@endsection
