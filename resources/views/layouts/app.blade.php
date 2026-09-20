<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $pageTitle = seo_title($metaTitle ?? null);
        $pageDescription = $metaDescription ?? setting('meta_description');
        $pageCanonical = $canonical ?? url()->current();
        $pageImage = $ogImage ?? media_url(setting('hero_image'), asset('images/placeholder.svg'));
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $pageDescription), 300, '') }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $pageCanonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ site_name() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $pageDescription), 300, '') }}">
    <meta property="og:url" content="{{ $pageCanonical }}">
    <meta property="og:image" content="{{ $pageImage }}">
    <meta property="og:locale" content="tr_TR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0f2a44">
    @if (setting('google_site_verification'))
        <meta name="google-site-verification" content="{{ setting('google_site_verification') }}">
    @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&family=Josefin+Sans:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @foreach (($jsonLd ?? []) as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endforeach
    @if (setting('google_analytics_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('google_analytics_id') }}"></script>
        <script>window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', '{{ setting('google_analytics_id') }}');</script>
    @endif
    @stack('head')
</head>
<body class="{{ $bodyClass ?? '' }}">
    @include('partials.header')

    <main id="main">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.floating-cta')
    @stack('scripts')
</body>
</html>
