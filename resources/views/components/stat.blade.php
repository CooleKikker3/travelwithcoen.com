@props(['label', 'value', 'hint' => null])
<div {{ $attributes->class('rounded-2xl bg-white p-5 ring-1 ring-sage-200') }}>
    <dt class="text-xs font-semibold tracking-wide text-moss-600 uppercase">{{ $label }}</dt>
    <dd class="mt-1 font-display text-2xl text-forest-900">{{ $value }}</dd>
    @if ($hint)
        <dd class="mt-0.5 text-xs text-moss-600">{{ $hint }}</dd>
    @endif
</div>
