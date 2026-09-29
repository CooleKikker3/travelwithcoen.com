@php
    // The plan in a few facts (texts editable under "Website texts").
    $facts = [
        [__('site.home.facts.departure'), ucfirst(__('site.departure'))],
        [__('site.home.facts.duration'), __('site.home.facts.duration_value')],
        [__('site.home.facts.daily'), __('site.home.facts.daily_value')],
        [__('site.home.facts.pack'), __('site.home.facts.pack_value')],
    ];
@endphp
{{-- Key facts of the journey as tilted luggage tags. --}}
<section {{ $attributes }}>
    <dl class="flex flex-wrap gap-4">
        @foreach ($facts as [$label, $value])
            <div class="bob rounded-xl border-2 border-dashed border-olive-300 bg-sand-100 px-4 py-2 shadow-sm" data-reveal style="--i: {{ $loop->index }}; --tilt: {{ [2, -2, 1, -1][$loop->index % 4] }}deg; animation-delay: -{{ $loop->index * 0.8 }}s">
                <dt class="text-xs font-bold tracking-wide text-bark-700 uppercase">{{ $label }}</dt>
                <dd class="font-hand text-2xl leading-tight text-forest-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
</section>
