<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial.">
        <meta name="author" content="Firdaus Nanda Christian">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="id_ID">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ config('app.name') }}">
        <meta property="og:description" content="Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial.">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ asset('app-icon-512.png') }}">
        <meta property="og:image:width" content="512">
        <meta property="og:image:height" content="512">
        <meta property="og:image:alt" content="Ikon {{ config('app.name') }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ config('app.name') }}">
        <meta name="twitter:description" content="Sistem Informasi Pendataan dan Inventarisasi Hutan Sosial.">
        <meta name="twitter:image" content="{{ asset('app-icon-512.png') }}">

        <title inertia>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
        <link href="https://fonts.googleapis.com" rel="preconnect">
        <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#143e2c">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
