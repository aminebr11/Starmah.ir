<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
        <meta name="theme-color" content="#0b1224">

        <title inertia>{{ config('app.name', 'ستاره ماه') }}</title>

        <link rel="manifest" href="/manifest.webmanifest">

        <!-- Persian font -->
        <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">

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
