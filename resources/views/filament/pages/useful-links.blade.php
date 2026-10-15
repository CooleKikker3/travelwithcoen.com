{{-- Handy links (UsefulLinks). Inline styles: Filament's CSS has no custom Tailwind classes. --}}
<x-filament-panels::page>
    @foreach ($this->groups() as $group => $links)
        <x-filament::section :heading="$group">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:.75rem">
                @foreach ($links as [$title, $url, $description])
                    <a href="{{ $url }}" target="_blank" rel="noopener" style="display:flex;gap:.75rem;align-items:flex-start;padding:.875rem 1rem;border-radius:.75rem;border:1px solid rgb(128 128 128 / .25)">
                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" style="width:1.25rem;height:1.25rem;flex-shrink:0;margin-top:.125rem;color:rgb(22 163 74)" />
                        <span style="display:grid;gap:.125rem">
                            <strong style="font-size:.95rem">{{ $title }}</strong>
                            <span style="font-size:.8rem;opacity:.7">{{ $description }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
