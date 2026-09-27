{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ($page['urls'] as $loc)
    <url>
        <loc>{{ $loc }}</loc>
@if ($page['lastmod'])
        <lastmod>{{ $page['lastmod']->toAtomString() }}</lastmod>
@endif
@if (count($page['urls']) > 1)
@foreach ($page['urls'] as $locale => $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $locale }}" href="{{ $alternate }}"/>
@endforeach
@isset ($page['urls'][$default])
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $page['urls'][$default] }}"/>
@endisset
@endif
    </url>
@endforeach
@endforeach
</urlset>
