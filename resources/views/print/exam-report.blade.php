@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $dur = fn ($s) => $s >= 60 ? $fa(intdiv((int) $s, 60)) . ' دقیقه' : $fa((int) $s) . ' ثانیه';
    $cls = fn ($p) => $p >= 70 ? 'b-ok' : ($p >= 50 ? 'b-warn' : 'b-bad');
    $st = ['finished' => 'پایان‌یافته', 'completed' => 'پایان‌یافته', 'graded' => 'تصحیح‌شده', 'in_progress' => 'در حالِ انجام', 'started' => 'در حالِ انجام'];
@endphp

@section('content')
    <div class="kv">
        @foreach($meta as $k => $v)<div>{{ $k }}: <b>{{ is_numeric($v) ? $fa($v) : $v }}</b></div>@endforeach
    </div>

    <h2>📊 خلاصه</h2>
    <div class="kv">
        <div>شرکت‌کننده: <b>{{ $fa($summary['started'] ?? 0) }}@if(!empty($summary['targeted'])) از {{ $fa($summary['targeted']) }}@endif نفر</b></div>
        <div>تکمیل‌شده: <b>{{ $fa($summary['completed'] ?? 0) }} نفر</b></div>
        <div>میانگینِ نمره: <b>{{ $fa($summary['avg'] ?? 0) }}٪</b></div>
        <div>درصدِ قبولی: <b>{{ $fa($summary['pass'] ?? 0) }}٪</b></div>
        <div>میانگینِ زمان: <b>{{ $dur($summary['avgDuration'] ?? 0) }}</b></div>
        <div>&nbsp;</div>
    </div>

    @if(!empty($buckets))
        <h2>📈 توزیعِ نمرات</h2>
        <table>
            <tr>@foreach($buckets as $k => $v)<th>{{ $fa($k) }}</th>@endforeach</tr>
            <tr>@foreach($buckets as $k => $v)<td class="num" style="text-align:center">{{ $fa($v) }} نفر</td>@endforeach</tr>
        </table>
    @endif

    <h2>👥 نتایجِ دانش‌آموزان</h2>
    @if(count($rows))
        <table>
            <tr><th>#</th><th>نام</th><th>نمره</th><th>درصد</th><th>وضعیت</th><th>دفعات</th><th>زمان</th></tr>
            @foreach($rows as $i => $r)
                <tr>
                    <td class="num">{{ $fa($i + 1) }}</td>
                    <td><b>{{ $r['name'] }}</b></td>
                    <td class="num">{{ $fa($r['score']) }} از {{ $fa($r['max']) }}</td>
                    <td><span class="badge {{ $cls($r['percent']) }}">{{ $fa($r['percent']) }}٪</span></td>
                    <td>{{ $st[$r['status']] ?? $r['status'] }}</td>
                    <td class="num">{{ $fa($r['attempts'] ?? 1) }}</td>
                    <td class="num">{{ $dur($r['duration'] ?? 0) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="color:var(--muted)">هنوز کسی در این آزمون شرکت نکرده است.</p>
    @endif

    @if(count($perQuestion))
        <h2>❓ تحلیلِ سؤال‌به‌سؤال</h2>
        <table>
            <tr><th>#</th><th>سؤال</th><th>درصدِ پاسخِ درست</th><th>پاسخِ غلط</th></tr>
            @foreach($perQuestion as $p)
                <tr>
                    <td class="num">{{ $fa(($p['i'] ?? 0) + 1) }}</td>
                    <td>{{ $p['prompt'] }}</td>
                    <td>@if(isset($p['pct']))<span class="badge {{ $cls($p['pct']) }}">{{ $fa($p['pct']) }}٪</span>@else — @endif</td>
                    <td class="num">{{ $fa($p['wrong'] ?? 0) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(count($weakTopics))
        <h2>📌 مباحثی که نیازِ به تمرین دارند</h2>
        <div class="advice">
            @foreach($weakTopics as $w){{ $w['topic'] }} ({{ $fa($w['pct']) }}٪)@if(!$loop->last) · @endif @endforeach
        </div>
    @endif

    <div class="sig">
        <div>امضای معلم</div>
        <div>امضای مدیرِ مدرسه</div>
        <div>مهرِ مدرسه</div>
    </div>
@endsection
