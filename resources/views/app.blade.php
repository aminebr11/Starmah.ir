<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta name="theme-color" content="#0b1224">

        <title inertia>{{ config('app.name', 'ستاره ماه') }}</title>

        <link rel="icon" type="image/svg+xml" href="/brand/logo-mark.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/brand/icon-32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/brand/icon-180.png">
        <link rel="manifest" href="/manifest.webmanifest">

        <!-- Persian font -->
        <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
        <!-- Team header fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@600;800&family=Press+Start+2P&family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="antialiased" style="font-family:'Vazirmatn',system-ui,sans-serif">
        @inertia
    </body>
</html>
