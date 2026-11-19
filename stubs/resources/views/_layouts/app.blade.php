<!DOCTYPE html>
<html lang="{{ LaravelLocalization::getCurrentLocale() }}">
<head>
    <meta charset="UTF-8">

    <meta name="application-name" content="{{ config('app.name') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $sectionTitle = trim($__env->yieldContent('title'));
        $pageTitle = filled($sectionTitle) ? $sectionTitle . ' | ' . config('app.name') : config('app.name');
        $pageDescription = trim($__env->yieldContent('description'));
    @endphp

    <title>{{ $pageTitle }}</title>
    <link rel="canonical" href="{{ url()->current() }}">

    @if(filled($pageDescription))
        <meta name="description" content="{{ $pageDescription }}">
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ LaravelLocalization::getCurrentLocaleRegional() }}">
    {{--<meta property="og:image" content="{{ asset('og.jpg') }}?v={{ filemtime(public_path('og.jpg')) }}">--}}
    {{--<meta property="og:image:type" content="image/jpeg">--}}
    {{--<meta property="og:image:width" content="1200">--}}
    {{--<meta property="og:image:height" content="630">--}}
    {{--<meta property="og:image:alt" content="{{ config('app.name') }}">--}}

    {{--<meta name="twitter:card" content="summary_large_image">--}}
    {{--<meta name="twitter:image" content="{{ asset('og.jpg') }}?v={{ filemtime(public_path('og.jpg')) }}">--}}

    {{--<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">--}}

    @foreach(LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
        <link rel="alternate" hreflang="{{ $localeCode }}" href="{{ LaravelLocalization::getLocalizedURL($localeCode, url()->current()) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ LaravelLocalization::getLocalizedURL(LaravelLocalization::getDefaultLocale(), url()->current()) }}">

    <style>[x-cloak] { display: none !important; }</style>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex flex-col min-h-screen overflow-anchor-none text-zinc-800 antialiased">

<main class="flex flex-col flex-1">
    @yield('content')
</main>

@stack('scripts')
</body>
</html>
