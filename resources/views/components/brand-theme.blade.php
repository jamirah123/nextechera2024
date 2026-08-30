@php
    $css = $brand['theme_css'] ?? null;
@endphp

@if ($css)
    <style id="brand-theme">{!! $css !!}</style>
@endif
