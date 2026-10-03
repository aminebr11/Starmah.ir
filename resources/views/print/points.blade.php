@extends('print.layout')

@section('content')
    <h2>رتبه‌بندیِ امتیازِ دانش‌آموزان</h2>
    <div class="kv">
        <div><b>بازه:</b> {{ $range }}</div>
        <div><b>کلاس:</b> {{ $classroom ?: '—' }}</div>
        <div><b>معلم:</b> {{ $teacher }}</div>
        <div><b>تعدادِ دانش‌آموز:</b> {{ \App\Support\Jalali::fa((string) $sum['students']) }}</div>
        <div><b>جمعِ امتیازِ بازه:</b> {{ \App\Support\Jalali::fa((string) $sum['xp']) }}</div>
        <div><b>تعدادِ تراکنش:</b> {{ \App\Support\Jalali::fa((string) $sum['entries']) }}</div>
    </div>

    <table style="margin-top:12px">
        <thead>
            <tr>
                <th style="width:34px">رتبه</th>
                <th>دانش‌آموز</th>
                <th>تیم</th>
                <th style="width:70px">امتیازِ بازه</th>
                <th style="width:60px">دریافتی</th>
                <th style="width:60px">کسرشده</th>
                <th style="width:58px">تراکنش</th>
                <th style="width:88px">آخرین امتیاز</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td class="num">{{ \App\Support\Jalali::fa((string) $r['rank']) }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['team'] ?: '—' }}</td>
                    <td class="num"><b>{{ \App\Support\Jalali::fa((string) $r['xp']) }}</b></td>
                    <td class="num">{{ \App\Support\Jalali::fa((string) $r['plus']) }}</td>
                    <td class="num">{{ $r['minus'] ? '−' . \App\Support\Jalali::fa((string) $r['minus']) : '—' }}</td>
                    <td class="num">{{ \App\Support\Jalali::fa((string) $r['entries']) }}</td>
                    <td class="num">{{ $r['last'] ?: '—' }}</td>
                </tr>
            @endforeach
            @if (! count($rows))
                <tr><td colspan="8" style="text-align:center;color:var(--muted)">در این بازه امتیازی ثبت نشده است.</td></tr>
            @endif
        </tbody>
    </table>

    <div class="sig">
        <div>امضای معلم</div>
        <div>امضای مدیر</div>
        <div>مهرِ مدرسه</div>
    </div>
@endsection
