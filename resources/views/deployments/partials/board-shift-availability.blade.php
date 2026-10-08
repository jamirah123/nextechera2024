@php
    $windows = $boardAvailability[$guard->id] ?? [
        'day' => ['deployed' => false, 'site' => null],
        'night' => ['deployed' => false, 'site' => null],
    ];
@endphp
<p class="leading-snug">Day: {{ $windows['day']['deployed'] ? $windows['day']['site'] : 'Available' }}</p>
<p class="leading-snug">Night: {{ $windows['night']['deployed'] ? $windows['night']['site'] : 'Available' }}</p>
