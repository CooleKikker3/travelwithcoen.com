{{-- Under maps, for visitors: why the location is not live. Family (logged in) sees everything live. --}}
@php
    $days = (int) round(\App\Support\Settings::get('public_tracking_delay_hours') / 24);
@endphp
@if ($days > 0 && ! auth()->user()?->canSeeLiveTracking())
    <p {{ $attributes->class('flex items-center gap-2 text-sm') }}>
        <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-12.5a.75.75 0 0 0-1.5 0V10c0 .2.08.39.22.53l2.5 2.5a.75.75 0 1 0 1.06-1.06l-2.28-2.28V5.5Z" clip-rule="evenodd"/></svg>
        {{ __('site.map.delay_note', ['days' => $days]) }}
    </p>
@endif