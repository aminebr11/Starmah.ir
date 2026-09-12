@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
@endphp

@section('content')
    {{-- عکس + شناسه‌ی کوتاه --}}
    <div style="display:flex;gap:14px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:4px">
        <div style="width:96px;height:120px;border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#f1f4fa;display:flex;align-items:center;justify-content:center;flex:0 0 auto">
            @if($s['avatar'])
                <img src="{{ $s['avatar'] }}" alt="" style="width:100%;height:100%;object-fit:cover">
            @else
                <span style="font-size:36px;color:#9aa6bd">👤</span>
            @endif
        </div>
        <div style="flex:1">
            <div style="font-size:18px;font-weight:800;color:var(--navy)">{{ $s['name'] }}</div>
            <div style="font-size:11.5px;color:var(--muted);margin-top:2px">
                {{ $dash($s['class']) }} @if($s['teacher']) · معلم: {{ $s['teacher'] }} @endif
            </div>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
                <span class="badge b-ok">⚡ {{ $fa($s['xp']) }} امتیاز</span>
                @if($s['team'])<span class="badge b-warn">{{ $s['team'] }}</span>@endif
                @if($s['grade'])<span class="badge b-warn">پایه‌ی {{ $s['grade'] }}</span>@endif
            </div>
        </div>
    </div>

    <h2>👤 مشخصاتِ فردی</h2>
    <div class="kv">
        <div><b>نام و نام‌خانوادگی:</b> {{ $s['name'] }}</div>
        <div><b>کدِ ملی:</b> {{ $fa($dash($s['national_id'])) }}</div>
        <div><b>جنسیت:</b> {{ $dash($s['gender']) }}</div>
        <div><b>تاریخِ تولد:</b> {{ $dash($s['birth']) }}</div>
        <div><b>پایه:</b> {{ $dash($s['grade']) }}</div>
        <div><b>موبایلِ دانش‌آموز:</b> {{ $fa($dash($s['phone'])) }}</div>
    </div>

    <h2>🏫 اطلاعاتِ تحصیلی</h2>
    <div class="kv">
        <div><b>کلاس:</b> {{ $dash($s['class']) }}</div>
        <div><b>معلمِ کلاس:</b> {{ $dash($s['teacher']) }}</div>
        <div><b>تیم/گروه:</b> {{ $dash($s['team']) }}</div>
        <div><b>تاریخِ ثبت‌نام:</b> {{ $dash($s['joined']) }}</div>
        <div><b>مجموعِ امتیاز:</b> {{ $fa($s['xp']) }}</div>
        <div><b>وضعیت:</b> فعال</div>
    </div>

    <h2>👨‍👩‍👧 سرپرست و تماس</h2>
    <div class="kv">
        <div><b>نامِ پدر:</b> {{ $dash($s['father_name']) }}</div>
        <div><b>نامِ مادر:</b> {{ $dash($s['mother_name']) }}</div>
        <div><b>نسبتِ سرپرست:</b> {{ $dash($s['parent_relation']) }}</div>
        <div><b>موبایلِ سرپرست:</b> {{ $fa($dash($s['guardian_phone'])) }}</div>
        <div style="grid-column:span 2"><b>نشانی:</b> {{ $dash($s['address']) }}</div>
    </div>

    @if($showPin && $s['parent_pin'])
        <div class="advice" style="margin-top:10px">
            🔐 <b>رمزِ ورودِ بخشِ والدین:</b> {{ $fa($s['parent_pin']) }}
            <div style="color:var(--muted);font-size:10.5px;margin-top:2px">
                این رمز محرمانه است و فقط باید به سرپرستِ همین دانش‌آموز داده شود.
                اگر برگه را در پرونده بایگانی می‌کنید، این بخش را جدا کنید.
            </div>
        </div>
    @endif

    <div class="sig">
        <div>امضای سرپرست</div>
        <div>امضای معلمِ کلاس</div>
        <div>مهر و امضای مدرسه</div>
    </div>
@endsection
