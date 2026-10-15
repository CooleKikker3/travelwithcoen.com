{{-- Cover focus picker (App\Filament\Support\CoverFocus). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
@php($url = $getRecord()?->coverUrl())
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
            focus: $wire.$entangle(@js($getStatePath())),
            get pos() { return this.focus || '50% 50%' },
            pick(event) {
                const box = event.currentTarget.getBoundingClientRect();
                const x = Math.round((event.clientX - box.left) / box.width * 100);
                const y = Math.round((event.clientY - box.top) / box.height * 100);
                this.focus = `${Math.min(100, Math.max(0, x))}% ${Math.min(100, Math.max(0, y))}%`;
            },
        }" style="display:grid;gap:.75rem">
        <div x-on:click="pick($event)" style="position:relative;cursor:crosshair;border-radius:.5rem;overflow:hidden;line-height:0">
            <img src="{{ $url }}" alt="" style="width:100%;height:auto">
            <span :style="{ left: pos.split(' ')[0], top: pos.split(' ')[1] }"
                  style="position:absolute;width:1.75rem;height:1.75rem;margin:-.875rem 0 0 -.875rem;border-radius:9999px;border:3px solid #fff;box-shadow:0 0 0 2px rgb(22 163 74),0 2px 8px rgb(0 0 0 / .5);pointer-events:none"></span>
        </div>
        <div style="display:flex;gap:.5rem;align-items:flex-start">
            @foreach (['Computer' => '16 / 7', 'Telefoon' => '9 / 14', 'Kaartje' => '4 / 3'] as $label => $ratio)
                <figure style="margin:0;flex:{{ $label === 'Telefoon' ? '0 0 22%' : '1' }}">
                    <img src="{{ $url }}" alt="" :style="{ objectPosition: pos }" style="width:100%;aspect-ratio:{{ $ratio }};object-fit:cover;border-radius:.375rem">
                    <figcaption style="font-size:.75rem;opacity:.7;margin-top:.25rem">{{ $label }}</figcaption>
                </figure>
            @endforeach
        </div>
        <div>
            <x-filament::button color="gray" size="sm" x-on:click="focus = null" icon="heroicon-o-arrows-pointing-in">Midden</x-filament::button>
        </div>
    </div>
</x-dynamic-component>
