<x-mail::message>
<x-mail.banner :label="$priorityLabel" :tone="$priorityTone" />

<h1 style="margin: 0 0 12px; font-size: 20px; line-height: 1.3; font-weight: 700; color: #0f172a;">
    {{ $headline }}
</h1>

<p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #334155;">
    {{ $summary }}
</p>

@if ($actorName)
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
    <tr>
        <td style="padding: 12px 16px;">
            <p style="margin: 0 0 2px; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8;">Action by</p>
            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #0f172a;">{{ $actorName }}</p>
        </td>
    </tr>
</table>
@endif

@if ($details !== [])
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
    <tr>
        <td colspan="2" style="padding: 10px 16px; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b;">Details</p>
        </td>
    </tr>
    @foreach ($details as $detail)
        @php
            [$key, $value] = array_pad(explode(': ', $detail, 2), 2, null);
            $hasKey = $value !== null;
        @endphp
        <tr>
            <td style="padding: 10px 16px; width: 38%; font-size: 13px; color: #64748b; border-top: 1px solid #f1f5f9; vertical-align: top;">
                {{ $hasKey ? $key : '—' }}
            </td>
            <td style="padding: 10px 16px; font-size: 13px; font-weight: 600; color: #0f172a; border-top: 1px solid #f1f5f9; vertical-align: top;">
                {{ $hasKey ? $value : $detail }}
            </td>
        </tr>
    @endforeach
</table>
@endif

@if ($actionUrl)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionLabel }}
</x-mail::button>

<x-slot:subcopy>
If the button above does not work, copy and paste this link into your browser:<br>
<span style="word-break: break-all;">{{ $actionUrl }}</span>
</x-slot:subcopy>
@endif

<p style="margin: 24px 0 0; font-size: 13px; line-height: 1.5; color: #64748b;">
    This is an automated notification from {{ config('psg.company', config('app.name')) }} {{ config('psg.system_subtitle', 'Operations & Workforce Management System') }}.
    Please do not reply to this automated email.
</p>
</x-mail::message>
