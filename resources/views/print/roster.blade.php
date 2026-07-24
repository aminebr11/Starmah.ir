@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
@endphp

@section('content')
    <h2>👥 فهرستِ کاملِ دانش‌آموزان ({{ $fa($students->count()) }} نفر)</h2>
    <table>
        <tr>
            <th>#</th><th>نام و نام‌خانوادگی</th><th>کدِ ملی</th><th>تولد</th><th>پایه</th>
            <th>کلاس / معلم</th><th>تیم</th><th>موبایل</th><th>سرپرست</th><th>موبایلِ سرپرست</th><th>امتیاز</th>
        </tr>
        @foreach($students as $i => $s)
            <tr>
                <td class="num">{{ $fa($i + 1) }}</td>
                <td><b>{{ $s['name'] }}</b></td>
                <td class="num">{{ $fa($dash($s['national_id'])) }}</td>
                <td class="num" style="white-space:nowrap">{{ $dash($s['birth']) }}</td>
                <td>{{ $dash($s['grade']) }}</td>
                <td style="white-space:nowrap">{{ $dash($s['class']) }}@if($s['teacher']) / {{ $s['teacher'] }}@endif</td>
                <td style="white-space:nowrap">{{ $dash($s['team']) }}</td>
                <td class="num" dir="ltr" style="text-align:left">{{ $fa($dash($s['phone'])) }}</td>
                <td>{{ $dash($s['guardian']) }}</td>
                <td class="num" dir="ltr" style="text-align:left">{{ $fa($dash($s['g_phone'])) }}</td>
                <td class="num">{{ $fa($s['xp']) }}</td>
            </tr>
        @endforeach
    </table>
    <p style="font-size:10.5px;color:var(--muted);margin-top:10px">
        ⚠️ این فهرست حاویِ اطلاعاتِ شخصی است — فقط برای استفاده‌ی اداریِ مدرسه چاپ و نگهداری شود.
    </p>
    <div class="sig">
        <div>تهیه‌کننده</div>
        <div>امضای مدیرِ مدرسه</div>
        <div>مهرِ مدرسه</div>
    </div>
@endsection
