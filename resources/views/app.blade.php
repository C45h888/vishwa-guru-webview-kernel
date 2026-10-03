<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#E9680C">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Libre+Caslon+Display:wght@400;700&display=swap" rel="stylesheet">
    {{--
        Server-rendered SEO head (Pass 5 — SEO Optimisation).

        Inertia hands this shell the fully-resolved page object as `$page`
        (vendor/inertiajs/inertia-laravel/src/Response.php:219), so the
        `seo` prop built by App\Seo\Services\SeoMetaBuilder is available
        here with no extra query.

        Why this block exists: WhatsApp, Facebook (facebookexternalhit)
        and iMessage (Apple's unfurl service) never execute JavaScript.
        They GET the URL and read <head> directly, so tags authored in
        <svelte:head> were invisible to all three. These tags are the
        reason a shared link renders a real preview.

        The loop is deliberately generic — SeoMetaBuilder owns which tags
        exist and what they contain. This view owns no SEO logic, which
        is also what keeps app.blade.php the runtime's only Blade
        template (AGENTS.md).

        Server tags are emitted BEFORE @inertiaHead so they are
        first-in-document-order, which is what scrapers read.
    --}}
    @php($seo = $page['props']['seo'] ?? null)
    <title inertia>{{ $seo['title'] ?? config('app.name', 'Temple Trust') }}</title>
    @if (! empty($seo['tags']))
        @foreach ($seo['tags'] as $seoTag)
            <{{ $seoTag['tag'] }}@foreach ($seoTag['attrs'] as $seoKey => $seoValue) {{ $seoKey }}="{{ $seoValue }}"@endforeach />
        @endforeach
    @endif
    @if (! empty($seo['jsonLdString']))
        <script type="application/ld+json">{!! $seo['jsonLdString'] !!}</script>
    @endif
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
