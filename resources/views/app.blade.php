<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($seo = app(\App\Support\Seo::class))
    <title inertia>{{ $seo->fullTitle() }}</title>
    <meta name="description" content="{{ $seo->metaDescription() }}">
    <link rel="canonical" href="{{ $seo->canonical() }}">
    @if ($seo->noindex)
    <meta name="robots" content="noindex, nofollow">
    @endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:title" content="{{ $seo->title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $seo->metaDescription() }}">
    <meta property="og:url" content="{{ $seo->canonical() }}">
    <meta property="og:image" content="{{ $seo->imageUrl() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#1c1917">
    @foreach ($seo->jsonLdScripts() as $json)
    <script type="application/ld+json">{!! $json !!}</script>
    @endforeach
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|playfair-display:700,800&display=swap" rel="stylesheet" />
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-stone-50 font-sans text-stone-800 antialiased">
    @inertia
</body>
</html>
