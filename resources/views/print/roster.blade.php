@extends('print.layout')

@php
    $fa = fn ($n) => str_replace(['0','1','2','3','4','5','6','7','8','9'], ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], (string)($n ?? ''));
    $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
    $ltr = ['phone', 'g_phone'];
    $numeric = ['national_id', 'phone', 'g_phone', 'xp'];
@endphp

@section('content')
    <h2>👥 فهرستِ دانش‌آموزان ({{ $fa($students->count()) }} نفر)</h2>
    <table>
        <tr>
            <th>#</th>
            @foreach($cols as $key => $label)<th>{{ $label }}</th>@endforeach
        </tr>
        @foreach($students as $i => $s)
            <tr>
                <td class="num">{{ $fa($i + 1) }}</td>
                @foreach($cols as $key => $label)
                    <td class="{{ in_array($key, $numeric) ? 'num' : '' }}"
                        @if(in_array($key, $ltr)) dir="ltr" style="text-align:left" @endif>
                        @if($key === 'name')<b>{{ $s['name'] }}</b>
                        @elseif($key === 'class'){{ $dash($s['class']) }}@if($s['teacher']) / {{ $s['teacher'] }}@endif
                        @elseif(in_array($key, $numeric)){{ $fa($dash($s[$key])) }}
                        @else{{ $dash($s[$key]) }}@endif
                    </td>
                @endforeach
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
