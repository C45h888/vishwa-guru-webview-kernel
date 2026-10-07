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
    @php($fallbackTitle = trim((string) config('app.name')) !== '' ? config('app.name') : config('trust.fallback_name', 'Temple Trust'))
    <title inertia>{{ ! empty($seo['title']) ? $seo['title'] : $fallbackTitle }}</title>
    @if (empty($seo['tags']))
        <meta name="description" content="{{ config('trust.description') }}" />
        <meta property="og:title" content="{{ $fallbackTitle }}" />
        <meta property="og:description" content="{{ config('trust.description') }}" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary" />
    @endif
    @if (! empty($seo['tags']))
        @foreach ($seo['tags'] as $seoTag)
            <{{ $seoTag['tag'] }}@foreach ($seoTag['attrs'] as $seoKey => $seoValue) {{ $seoKey }}="{{ $seoValue }}"@endforeach />
        @endforeach
    @endif
    @if (! empty($seo['jsonLdString']))
        <script type="application/ld+json">{!! $seo['jsonLdString'] !!}</script>
    @endif
    {{-- Analytics (Phase 1). Everything is env-gated: with no IDs set,
         nothing is emitted. Preferred path is GTM (configure GA4 + Meta
         Pixel tags inside the container). GA4_ID / META_PIXEL_ID load the
         tags directly ONLY when GTM_ID is unset, to avoid double counting. --}}
    @php($gtmId = config('services.gtm.id'))
    @php($ga4Id = config('services.ga4.id'))
    @php($pixelId = config('services.meta_pixel.id'))
    <script>window.dataLayer = window.dataLayer || [];</script>
    @if ($gtmId)
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtmId));</script>
    @else
        @if ($ga4Id)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga4Id) }}"></script>
            <script>function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($ga4Id));</script>
        @endif
        @if ($pixelId)
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($pixelId));fbq('track','PageView');</script>
        @endif
    @endif
    @vite('resources/js/app.ts')
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @if ($gtmId)
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtmId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @inertia
</body>
</html>
