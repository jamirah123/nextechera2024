@props([
    'label',
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => ['bg' => '#ecfdf5', 'border' => '#1E5D48', 'text' => '#14532d'],
        'info' => ['bg' => '#eff6ff', 'border' => '#1845de', 'text' => '#1e3a8a'],
        'warning' => ['bg' => '#fffbeb', 'border' => '#d97706', 'text' => '#92400e'],
        'success' => ['bg' => '#ecfdf5', 'border' => '#059669', 'text' => '#065f46'],
    ];
    $palette = $tones[$tone] ?? $tones['brand'];
@endphp

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 24px;">
    <tr>
        <td style="background-color: {{ $palette['bg'] }}; border-left: 4px solid {{ $palette['border'] }}; border-radius: 6px; padding: 14px 16px;">
            <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: {{ $palette['text'] }};">
                {{ $label }}
            </p>
        </td>
    </tr>
</table>
