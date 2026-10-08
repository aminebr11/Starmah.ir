@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $dur = fn ($s) => $s >= 60 ? $fa(intdiv((int) $s, 60)) . ' دقیقه ' . ($s % 60 ? $fa($s % 60) . ' ثانیه' : '') : $fa((int) $s) . ' ثانیه';
    $cls = fn ($p) => $p >= 70 ? 'b-ok' : ($p >= 50 ? 'b-warn' : 'b-bad');
@endphp

@section('content')
    <div class="kv">
        @foreach($meta as $k => $v)<div>{{ $k }}: <b>{{ is_numeric($v) ? $fa($v) : $v }}</b></div>@endforeach
    </div>

    <h2>📊 خلاصه</h2>
    <div class="kv">
        <div>شروع کرده: <b>{{ $fa($summary['started']) }} نفر</b></div>
        <div>تکمیل کرده: <b>{{ $fa($summary['completed']) }} نفر</b></div>
        <div>میانگینِ دقت: <b>{{ $fa($summary['avg']) }}٪</b></div>
        <div>میانگینِ زمان: <b>{{ $dur($summary['avgDuration']) }}</b></div>
        <div>استفاده از راهنما: <b>{{ $fa($summary['hints']) }} بار</b></div>
        <div>&nbsp;</div>
    </div>

    <h2>👥 نتایجِ دانش‌آموزان</h2>
    @if(count($rows))
        <table>
            <tr><th>#</th><th>نام</th><th>امتیاز</th><th>دقت</th><th>وضعیت</th><th>راهنما</th><th>زمان</th><th>تاریخ</th></tr>
            @foreach($rows as $i => $r)
                <tr>
                    <td class="num">{{ $fa($i + 1) }}</td>
                    <td><b>{{ $r['name'] }}</b></td>
                    <td class="num">{{ $fa($r['score']) }} از {{ $fa($r['max']) }}</td>
                    <td><span class="badge {{ $cls($r['percent']) }}">{{ $fa($r['percent']) }}٪</span></td>
                    <td>{{ $r['status'] === 'completed' ? 'تکمیل' : 'ناتمام' }}</td>
                    <td class="num">{{ $fa($r['hints']) }}</td>
                    <td class="num">{{ $dur($r['duration'] ?? 0) }}</td>
                    <td>{{ $r['jdate'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="color:var(--muted)">هنوز کسی این بازی را انجام نداده است.</p>
    @endif

    @if(count($hardQuestions))
        <h2>🔧 سؤال‌های دشوار (بیشترین پاسخِ غلط)</h2>
        <table>
            <tr><th>#</th><th>سؤال</th><th>تعدادِ پاسخِ غلط</th></tr>
            @foreach($hardQuestions as $i => $h)
                <tr><td class="num">{{ $fa($i + 1) }}</td><td>{{ $h['prompt'] }}</td><td class="num">{{ $fa($h['wrong']) }}</td></tr>
            @endforeach
        </table>
    @endif

    <div class="sig">
        <div>امضای معلم</div>
        <div>امضای مدیرِ مدرسه</div>
        <div>مهرِ مدرسه</div>
    </div>
@endsection
