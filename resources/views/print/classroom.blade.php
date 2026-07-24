@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $t = $report['totals'];
@endphp

@section('content')
    <div class="kv" style="grid-template-columns:repeat(2,1fr)">
        <div><b>کلاس:</b> {{ $classname }}</div>
        <div><b>معلم:</b> {{ $teacher }}</div>
    </div>

    <h2>📊 خلاصه‌ی کلاس</h2>
    <table>
        <tr><th>دانش‌آموزان</th><th>مجموعِ امتیاز</th><th>فعالیت‌ها</th><th>درگیریِ هفته</th><th>میانگینِ تسلط</th></tr>
        <tr>
            <td class="num">{{ $fa($t['students']) }}</td>
            <td class="num">⚡ {{ $fa($t['points']) }}</td>
            <td class="num">{{ $fa($t['activities']) }}</td>
            <td class="num">{{ $fa($t['engagement']) }}٪</td>
            <td class="num">{{ $fa($t['avg_mastery']) }}٪</td>
        </tr>
    </table>

    <h2>🏆 رقابتِ تیم‌ها</h2>
    <table>
        <tr><th>رتبه</th><th>تیم</th><th>اعضا</th><th>مجموعِ امتیاز</th></tr>
        @foreach($report['by_group'] as $i => $g)
            <tr>
                <td class="num">{{ $fa($i + 1) }}</td>
                <td>{{ $g['emoji'] }} {{ $g['name'] }}</td>
                <td class="num">{{ $fa($g['count']) }}</td>
                <td class="num">{{ $fa($g['total']) }}</td>
            </tr>
        @endforeach
    </table>

    <h2>🧩 امتیاز بر اساسِ نوعِ فعالیت</h2>
    <table>
        <tr><th>نوع</th><th>دفعات</th><th>امتیاز</th></tr>
        @forelse($report['by_type'] as $g)
            <tr><td>{{ $g['label'] }}</td><td class="num">{{ $fa($g['count']) }}</td><td class="num">{{ $fa($g['points']) }}</td></tr>
        @empty
            <tr><td colspan="3">هنوز امتیازی از فعالیت‌ها ثبت نشده.</td></tr>
        @endforelse
    </table>

    <h2>👥 جدولِ کاملِ دانش‌آموزان</h2>
    <table>
        <tr><th>#</th><th>نام</th><th>تیم</th><th>امتیاز</th><th>تسلط</th><th>فعالیت‌ها</th></tr>
        @foreach($report['per_student'] as $i => $s)
            <tr>
                <td class="num">{{ $fa($i + 1) }}</td>
                <td><b>{{ $s['name'] }}</b></td>
                <td>{{ $s['emoji'] }} {{ $s['group'] ?? '—' }}</td>
                <td class="num">{{ $fa($s['xp']) }}</td>
                <td class="num">
                    {{ $fa($s['mastery']) }}٪
                    @if($s['mastery'] >= 70)<span class="badge b-ok">خوب</span>
                    @elseif($s['mastery'] >= 40)<span class="badge b-warn">در مسیر</span>
                    @else<span class="badge b-bad">ضعیف</span>@endif
                </td>
                <td class="num">{{ $fa($s['activities']) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="sig">
        <div>امضای معلم</div>
        <div>امضای مدیرِ مدرسه</div>
        <div>مهرِ مدرسه</div>
    </div>
@endsection
