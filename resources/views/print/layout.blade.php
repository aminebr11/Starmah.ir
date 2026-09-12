<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — ستاره ماه</title>
    {{-- فونت از سرورِ خودمان؛ برگه‌ی چاپ نباید منتظرِ CDNِ بیرونی بماند --}}
    <link rel="stylesheet" href="/fonts/vazirmatn/vazirmatn.css">
    <style>
        :root{ --navy:#16264f; --gold:#b9831a; --line:#d9dfe9; --muted:#5a6478; }
        *{ box-sizing:border-box; }
        body{ font-family:'Vazirmatn',system-ui,sans-serif; margin:0; background:#eceff5; color:#141b2b; line-height:1.8; }
        .sheet{ max-width:210mm; margin:16px auto; background:#fff; padding:14mm 12mm; box-shadow:0 10px 30px -18px rgba(20,30,60,.45); min-height:270mm; }
        /* سربرگ */
        .ph{ display:flex; align-items:center; gap:12px; border-bottom:3px solid var(--navy); padding-bottom:10px; }
        .ph img.slogo{ width:52px; height:52px; border-radius:10px; object-fit:cover; }
        .ph .brand{ margin-inline-start:auto; text-align:center; font-size:11px; color:var(--muted); }
        .ph .brand img{ width:38px; height:38px; border-radius:50%; display:block; margin:0 auto 2px; }
        .ph h1{ margin:0; font-size:17px; color:var(--navy); }
        .ph .sub{ font-size:11.5px; color:var(--muted); }
        .meta{ display:flex; justify-content:space-between; font-size:11px; color:var(--muted); margin:8px 0 14px; }
        h2{ font-size:13.5px; color:var(--navy); border-inline-start:4px solid var(--gold); padding-inline-start:8px; margin:18px 0 8px; }
        table{ width:100%; border-collapse:collapse; font-size:11.5px; }
        th,td{ border:1px solid var(--line); padding:5px 8px; text-align:right; }
        th{ background:#f1f4fa; color:var(--navy); font-weight:700; white-space:nowrap; }
        td.num{ font-variant-numeric:tabular-nums; }
        .kv{ display:grid; grid-template-columns:repeat(3,1fr); gap:0; border:1px solid var(--line); }
        .kv > div{ padding:6px 10px; font-size:11.5px; border-bottom:1px solid var(--line); }
        .kv > div:nth-child(3n+1),.kv > div:nth-child(3n+2){ border-inline-end:1px solid var(--line); }
        .kv b{ color:var(--navy); }
        .badge{ display:inline-block; border-radius:12px; padding:1px 9px; font-size:10.5px; font-weight:700; }
        .b-ok{ background:#e2f6ec; color:#177a4c; } .b-warn{ background:#fdeedd; color:#a05a12; } .b-bad{ background:#fde4e6; color:#b0303c; }
        .advice{ border:1px dashed var(--line); border-radius:8px; padding:8px 12px; font-size:11.5px; margin-top:6px; }
        .sig{ display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-top:26px; }
        .sig > div{ border-top:1px solid var(--line); padding-top:8px; text-align:center; font-size:11.5px; color:var(--muted); height:64px; }
        .pfoot{ margin-top:18px; padding-top:8px; border-top:1px solid var(--line); font-size:10px; color:var(--muted); display:flex; justify-content:space-between; }
        /* نوارِ ابزار (فقط نمایش، نه چاپ) */
        .toolbar{ position:sticky; top:0; background:var(--navy); color:#fff; padding:10px 16px; display:flex; gap:10px; align-items:center; z-index:5; }
        .toolbar button{ font-family:inherit; font-weight:800; font-size:13px; border:0; border-radius:10px; padding:8px 18px; cursor:pointer; background:#f5b53f; color:#221503; }
        .toolbar a{ color:#c4d2f0; font-size:12px; text-decoration:none; }
        @media print{
            body{ background:#fff; }
            .sheet{ margin:0; box-shadow:none; padding:8mm 10mm; min-height:auto; max-width:none; }
            .toolbar{ display:none; }
            @page{ size:A4 {{ ($orient ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait' }}; margin:8mm; }
            tr{ break-inside:avoid; }
        }
        @if(($orient ?? 'portrait') === 'landscape')
            .sheet{ max-width:297mm; }
        @endif
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨️ چاپ / ذخیره به PDF</button>
        <a href="{{ $back ?? url('/') }}">← بازگشت</a>
        <button type="button" onclick="tryClose()" style="background:#33405e;color:#fff">✕ بستن</button>
        <span style="margin-inline-start:auto;font-size:12px;opacity:.8">برای PDF: در پنجره‌ی چاپ، «Save as PDF» را انتخاب کنید</span>
    </div>
    <script>
        function tryClose(){ window.close(); setTimeout(function(){ if(!window.closed){ window.location.href = @json($back ?? url('/')); } }, 120); }
    </script>
    <div class="sheet">
        <div class="ph">
            @if($school_logo)<img class="slogo" src="{{ $school_logo }}" alt="">@endif
            <div>
                <h1>{{ $title }}</h1>
                <div class="sub">{{ $school_name ?? 'ستاره ماه' }}@if(!empty($school_city)) · {{ $school_city }}@endif</div>
            </div>
            <div class="brand"><img src="/brand/logo-emblem.png" alt="">ستاره ماه</div>
        </div>
        <div class="meta"><span>تاریخِ صدور: {{ $today }}</span><span>starmah.ir</span></div>
        @yield('content')
        <div class="pfoot">
            <span>این گزارش به‌صورتِ خودکار از سامانه‌ی «ستاره ماه» صادر شده است.</span>
            <span>{{ $today }}</span>
        </div>
    </div>
</body>
</html>
