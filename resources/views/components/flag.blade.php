@props(['country'])
{{-- Flag image (flag-icons); emoji flags don't show on Windows. --}}
<span {{ $attributes->class('fi fi-'.strtolower($country->iso_code).' rounded-sm shadow-sm') }} aria-hidden="true"></span>
