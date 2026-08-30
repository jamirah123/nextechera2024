@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $primary = config('psg.theme_primary', '#1845de');
    $primaryStyle = $color === 'primary'
        ? "background-color: {$primary}; border-bottom: 8px solid {$primary}; border-left: 18px solid {$primary}; border-right: 18px solid {$primary}; border-top: 8px solid {$primary}; color: #ffffff;"
        : null;
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener"@if($primaryStyle) style="{{ $primaryStyle }}"@endif>{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
