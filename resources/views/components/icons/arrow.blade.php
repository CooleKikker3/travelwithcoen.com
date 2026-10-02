@props(['left' => false])
{{-- A plain arrow, the same on every device (the "→" character is not in the fonts). Sized with the text. --}}
<svg {{ $attributes->class(['inline-block size-[1.1em] shrink-0 align-[-0.18em]', '-scale-x-100' => $left]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15M13 6l6 6-6 6"/></svg>
