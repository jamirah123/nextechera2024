{{-- @deprecated Use x-form-panel inside a <form> tag instead. --}}
<div {{ $attributes->class(['form-panel']) }}>
    <div class="form-panel__body">
        {{ $slot }}
    </div>
</div>
