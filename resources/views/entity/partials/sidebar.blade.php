{{-- Shared sidebar stack for entity show pages --}}
@props(['lifecycle' => null, 'relatedPanels' => []])

<div class="space-y-3">
    @if ($lifecycle)
        <x-entity.status-lifecycle
            :steps="$lifecycle['steps']"
            :current-step="$lifecycle['current']"
            :terminal-label="$lifecycle['terminal'] ?? null"
            :terminal-tone="$lifecycle['terminal_tone'] ?? 'slate'"
        />
    @endif

    @if (! empty($relatedPanels))
        <x-entity.related-records :panels="$relatedPanels" />
    @endif
</div>
