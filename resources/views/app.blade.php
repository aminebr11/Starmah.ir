<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0b1224">

        <title inertia>{{ config('app.name', 'ستاره ماه') }}</title>

        <link rel="icon" type="image/svg+xml" href="/brand/logo-mark.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/brand/icon-32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/brand/icon-180.png">
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- نصب روی آیفون: iOS از manifest برای حالتِ اپ استفاده نمی‌کند
             و به این متاتگ‌ها نیاز دارد («افزودن به صفحه‌ی اصلی» در سافاری). --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="ستاره ماه">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="ستاره ماه">

        {{-- فونتِ فارسی — از خودِ سرورِ سایت، نه CDNِ بیرونی.
             پیش از این از cdn.jsdelivr.net می‌آمد و چون شیوه‌نامه‌ی مسدودکننده‌ی
             رندر بود، هر کندی یا قطعیِ آن دامنه صفحه را چند ثانیه سفید نگه
             می‌داشت و لمس روی دکمه‌ها بی‌اثر می‌شد. --}}
        <link rel="preload" href="/fonts/vazirmatn/Vazirmatn-Regular.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="stylesheet" href="/fonts/vazirmatn/vazirmatn.css">

        {{-- فونت‌های سرتیترِ تیم‌ها — همان چهار فونتِ انتخابی، ولی از سرورِ خودمان.
             مرورگر هر فایل را فقط وقتی می‌گیرد که عنصری از آن استفاده کند،
             پس روی صفحه‌ی اول هیچ هزینه‌ای ندارد. --}}
        <link rel="stylesheet" href="/fonts/team/team-fonts.css">

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
