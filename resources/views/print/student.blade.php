@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
@endphp

@section('content')
    <h2>👤 مشخصاتِ دانش‌آموز</h2>
    <div class="kv">
        <div><b>نام و نام‌خانوادگی:</b> {{ $student['name'] }}</div>
        <div><b>کدِ ملی:</b> {{ $fa($dash($student['national_id'])) }}</div>
        <div><b>تاریخِ تولد:</b> {{ $dash($student['birth']) }}</div>
        <div><b>پایه:</b> {{ $dash($student['grade']) }}</div>
        <div><b>جنسیت:</b> {{ $dash($student['gender']) }}</div>
        <div><b>موبایل:</b> {{ $fa($dash($student['phone'])) }}</div>
        <div><b>کلاس:</b> {{ $dash($r['classroom']) }}</div>
        <div><b>معلم:</b> {{ $dash($r['teacher']) }}</div>
        <div><b>تیم/گروه:</b> {{ $r['team'] ? $r['team']['emoji'].' '.$r['team']['name'] : '—' }}</div>
    </div>

    <h2>👨‍👩‍👧 اطلاعاتِ سرپرست</h2>
    <div class="kv">
        <div><b>نامِ پدر:</b> {{ $dash($student['father']) }}</div>
        <div><b>نامِ مادر:</b> {{ $dash($student['mother']) }}</div>
        <div><b>موبایلِ سرپرست:</b> {{ $fa($dash($student['g_phone'])) }}</div>
    </div>
    @if($student['address'])
        <div style="border:1px solid var(--line);border-top:0;padding:6px 10px;font-size:11.5px"><b style="color:var(--navy)">آدرس:</b> {{ $student['address'] }}</div>
    @endif

    <h2>📊 خلاصه‌ی عملکرد</h2>
    <table>
        <tr>
            <th>کلِ امتیاز</th><th>امتیازِ این هفته</th><th>هفته‌ی قبل</th><th>میانگینِ هفتگیِ کلاس</th><th>رتبه در کلاس</th><th>تسلطِ کلی</th>
        </tr>
        <tr>
            <td class="num">⚡ {{ $fa($r['xp_total']) }}</td>
            <td class="num">{{ $fa($r['week_xp']) }}
                @if($r['week_xp'] > $r['prev_week_xp']) <span class="badge b-ok">صعودی ↑</span>
                @elseif($r['week_xp'] < $r['prev_week_xp']) <span class="badge b-warn">نزولی ↓</span>@endif
            </td>
            <td class="num">{{ $fa($r['prev_week_xp']) }}</td>
            <td class="num">{{ $r['class_avg_week'] === null ? '—' : $fa($r['class_avg_week']) }}</td>
            <td class="num">{{ $r['rank'] ? $fa($r['rank']).' از '.$fa($r['class_size']) : '—' }}</td>
            <td class="num">
                @if($r['mastery'] === null) — <span style="font-size:10px;color:var(--muted)">داده‌ی کافی نیست</span>
                @else
                    {{ $fa($r['mastery']) }}٪
                    @php $lv = $r['mastery_detail']['level'] ?? null; @endphp
                    <span class="badge {{ $r['mastery'] >= 70 ? 'b-ok' : ($r['mastery'] >= 50 ? 'b-warn' : 'b-bad') }}">{{ $lv['label'] ?? '' }}</span>
                @endif
            </td>
        </tr>
    </table>

    @php $subs = $r['mastery_detail']['subjects'] ?? []; @endphp
    @if(count($subs))
        <h2>🎯 تسلط بر درس‌ها</h2>
        <table>
            <tr><th>درس</th><th>تسلط</th><th>سطح</th><th>تعدادِ نشانه</th><th>مبحث‌های نیازمندِ تمرین</th></tr>
            @foreach($subs as $s)
                <tr>
                    <td><b>{{ $s['name'] }}</b></td>
                    <td class="num">{{ $s['mastery'] === null ? '—' : $fa($s['mastery']).'٪' }}</td>
                    <td>@if($s['level'])<span class="badge {{ $s['mastery'] >= 70 ? 'b-ok' : ($s['mastery'] >= 50 ? 'b-warn' : 'b-bad') }}">{{ $s['level']['label'] }}</span>@else داده‌ی کافی نیست @endif</td>
                    <td class="num">{{ $fa($s['n']) }}</td>
                    <td>{{ collect($s['topics'])->filter(fn ($t) => $t['mastery'] !== null && $t['mastery'] < 70)->pluck('name')->take(3)->implode('، ') ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
        <p style="font-size:10px;color:var(--muted);margin-top:6px">تسلط از همه‌ی پاسخ‌ها در آزمون، بازی، مأموریت، تکلیف و نمره‌ی معلم برآورد می‌شود؛ کارهای تازه‌تر و سؤال‌های دشوارتر وزنِ بیشتری دارند. مسلط = ۸۵٪ و بالاتر.</p>
    @endif

    @if (!empty($pt['points']))
        @php
            $pts = collect($pt['points']);
            $withXp = $pts->filter(fn ($p) => $p['xp'] > 0);
            $maxXp = max(1, (int) $pts->max('xp'));
            $sum = $pt['summary'];
        @endphp
        <h2>📊 روندِ امتیاز و رتبه — سالِ تحصیلیِ {{ $pt['label'] }}</h2>
        <div class="kv">
            <div><b>امتیازِ امسال:</b> {{ $fa((string) $sum['total']) }}</div>
            <div><b>رتبه در کلاس:</b> {{ $sum['class_rank'] ? $fa((string) $sum['class_rank']) . ' از ' . $fa((string) $sum['class_size']) : '—' }}</div>
            <div><b>بهترین رتبه‌ی امسال:</b> {{ $sum['best_rank'] ? $fa((string) $sum['best_rank']) : '—' }}</div>
            <div><b>رتبه در مدرسه:</b> {{ $sum['school_rank'] ? $fa((string) $sum['school_rank']) . ' از ' . $fa((string) $sum['school_size']) : '—' }}</div>
            <div><b>بهترین هفته:</b> {{ $sum['best_week'] ? $sum['best_week']['label'] . ' (' . $fa((string) $sum['best_week']['xp']) . ')' : '—' }}</div>
            <div><b>امتیازِ هفته‌ی جاری:</b> {{ $fa((string) $sum['week_xp']) }}</div>
        </div>

        {{-- نمودارِ ستونیِ امتیازِ هفتگی با CSS خالص تا در چاپ هم درست دربیاید --}}
        <div style="display:flex;align-items:flex-end;gap:3px;height:74px;margin:10px 0 2px;border-bottom:1px solid var(--line);padding-bottom:2px">
            @foreach ($pts as $p)
                <div style="flex:1;min-width:0;display:flex;justify-content:center;align-items:flex-end" title="{{ $p['range'] }}">
                    <div style="width:100%;max-width:26px;height:{{ max(2, (int) round($p['xp'] / $maxXp * 62)) }}px;background:{{ $p['xp'] === (int) $pts->max('xp') && $p['xp'] > 0 ? '#f0a020' : '#8fb6f5' }};border-radius:3px 3px 0 0"></div>
                </div>
            @endforeach
        </div>
        <div style="display:flex;gap:3px;font-size:8.5px;color:var(--muted);margin-bottom:10px">
            @foreach ($pts as $p)
                <div style="flex:1;min-width:0;text-align:center">{{ $loop->first || $loop->last || $loop->index % 3 === 0 ? $p['label'] : '' }}</div>
            @endforeach
        </div>

        <table>
            <thead><tr><th>هفته</th><th>بازه</th><th style="width:60px">امتیازِ هفته</th><th style="width:60px">جمعِ امتیاز</th><th style="width:76px">رتبه در کلاس</th></tr></thead>
            <tbody>
                @foreach ($pts->reverse()->take(12)->reverse() as $p)
                    <tr>
                        <td>{{ $p['label'] }}</td>
                        <td style="font-size:10.5px;color:var(--muted)">{{ $p['range'] }}</td>
                        <td class="num">{{ $fa((string) $p['xp']) }}</td>
                        <td class="num">{{ $fa((string) $p['total']) }}</td>
                        <td class="num">{{ $fa((string) $p['class_rank']) }} از {{ $fa((string) $p['class_size']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>🧩 امتیاز از کجا آمده</h2>
    <table>
        <tr><th>نوعِ فعالیت</th><th>امتیاز</th><th>سهم</th></tr>
        @php $tot = max(1, collect($r['by_type'])->sum('points')); @endphp
        @forelse($r['by_type'] as $t)
            <tr><td>{{ $t['label'] }}</td><td class="num">{{ $fa($t['points']) }}</td><td class="num">{{ $fa(round($t['points'] / $tot * 100)) }}٪</td></tr>
        @empty
            <tr><td colspan="3">هنوز امتیازی ثبت نشده است.</td></tr>
        @endforelse
    </table>

    <h2>✅ حضور و انضباط (۳۰ روزِ اخیر)</h2>
    <table>
        <tr><th>حاضر</th><th>غایب</th><th>تأخیر</th><th>موجه</th><th>موردِ انضباطیِ مثبت</th><th>موردِ انضباطیِ منفی</th></tr>
        <tr>
            <td class="num">{{ $fa($r['attendance']['present']) }}</td>
            <td class="num">{{ $fa($r['attendance']['absent']) }} @if($r['attendance']['absent'] >= 2)<span class="badge b-warn">توجه</span>@endif</td>
            <td class="num">{{ $fa($r['attendance']['late']) }}</td>
            <td class="num">{{ $fa($r['attendance']['excused']) }}</td>
            <td class="num">{{ $fa($r['discipline']['pos']) }}</td>
            <td class="num">{{ $fa($r['discipline']['neg']) }} @if($r['discipline']['neg'] > 0)<span class="badge b-bad">توجه</span>@endif</td>
        </tr>
    </table>

    <h2>💡 جمع‌بندی و توصیه</h2>
    @foreach($r['advice'] as $a)
        <div class="advice">{{ $a['tone'] === 'ok' ? '✅' : '⚠️' }} {{ $a['text'] }}</div>
    @endforeach

    <div class="sig">
        <div>امضای معلم</div>
        <div>امضای مدیرِ مدرسه</div>
        <div>امضای والد/سرپرست</div>
    </div>
@endsection
