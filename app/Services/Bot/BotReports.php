<?php

namespace App\Services\Bot;

use App\Models\AttendanceRecord;
use App\Models\AudioSubmission;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeColumn;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\Models\WorksheetSubmission;
use App\Support\DbSchema;
use App\Support\Jalali;
use Illuminate\Support\Collection;

/** گزارش‌های متنیِ بات (کوتاه، خوانا روی گوشی، بدونِ شماره‌تلفن و اطلاعاتِ حساس). */
class BotReports
{
    public function __construct(private User $user, private Collection $classes)
    {
    }

    private static function fa($n): string
    {
        return Jalali::fa((string) $n);
    }

    private function ids(): array
    {
        return $this->classes->pluck('id')->all();
    }

    private function site(string $path): string
    {
        return rtrim((string) (\App\Support\Bot\BotConfig::get('site_url', '') ?: config('app.url')), '/') . $path;
    }

    /** 🌅 گزارشِ امروز (همان گزارشِ صبحگاهی). */
    public function today(): string
    {
        $now = now();
        $L = ['🌅 ' . Jalali::format($now, true) . ' — سلام ' . explode(' ', trim($this->user->name))[0] . ' 🌸', ''];

        // برنامه‌ی امروز
        $dow = ((int) $now->format('w') + 1) % 7;
        $rows = DbSchema::hasTable('schedule_entries') ? ScheduleEntry::withoutGlobalScopes()->whereIn('classroom_id', $this->ids())
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('specific_date')->where('day_of_week', $dow))->orWhereDate('specific_date', $now->toDateString()))
            ->orderByRaw('start_time IS NULL, start_time')->orderBy('period')->get() : collect();
        if ($rows->where('kind', '!=', 'recess')->isNotEmpty()) {
            $L[] = '📅 برنامه‌ی امروز:';
            foreach ($rows->where('kind', '!=', 'recess')->take(10) as $r) {
                $L[] = '  • ' . ($r->time_range ?: ($r->start_time ? substr($r->start_time, 0, 5) : 'زنگِ ' . self::fa($r->period))) . ' — ' . $r->title
                    . ($this->classes->count() > 1 ? ' (' . ($this->classes->firstWhere('id', $r->classroom_id)?->name) . ')' : '');
            }
        } else {
            $L[] = '📅 برای امروز برنامه‌ای ثبت نشده.';
        }

        // حضور و غیابِ امروز
        foreach ($this->classes as $c) {
            $att = AttendanceRecord::withoutGlobalScopes()->where('classroom_id', $c->id)->whereDate('date', $now->toDateString())->get();
            $L[] = $att->isEmpty()
                ? "📋 {$c->name}: حضور و غیابِ امروز هنوز ثبت نشده."
                : "📋 {$c->name}: " . self::fa($att->where('status', 'present')->count()) . ' حاضر · ' . self::fa($att->where('status', 'absent')->count()) . ' غایب · ' . self::fa($att->where('status', 'late')->count()) . ' تأخیر';
        }

        // کارهای منتظرِ تصحیح
        [$ws, $au] = $this->pendingCounts();
        if ($ws + $au > 0) {
            $L[] = '✍️ منتظرِ تصحیح: ' . ($ws ? self::fa($ws) . ' کاربرگ' : '') . ($ws && $au ? ' · ' : '') . ($au ? self::fa($au) . ' املا/روخوانی' : '');
        } else {
            $L[] = '✍️ کارِ منتظرِ تصحیح نداری 👏';
        }

        // هشدارهای مهم
        $risk = $this->riskAlerts()->whereIn('level', ['crit'])->count();
        if ($risk) {
            $L[] = '⚠️ ' . self::fa($risk) . ' هشدارِ مهم دربارهٔ دانش‌آموزان — «⚠️ نیازمندِ توجه» را بزن.';
        }

        return implode("\n", $L);
    }

    public function pendingCounts(): array
    {
        $ws = rescue(fn () => DbSchema::hasColumn('worksheet_submissions', 'graded_at')
            ? WorksheetSubmission::whereNotNull('file_path')->whereNull('graded_at')->whereHas('worksheet', fn ($q) => $q->withoutGlobalScopes()->where('teacher_id', $this->user->id))->count() : 0, 0, false);
        $au = rescue(fn () => \App\Models\AudioTask::ready()
            ? AudioSubmission::whereNotNull('file_path')->whereNull('graded_at')->whereHas('task', fn ($q) => $q->where('teacher_id', $this->user->id))->count() : 0, 0, false);

        return [(int) $ws, (int) $au];
    }

    /** ⏳ کارهای منتظرِ تصحیح (با پیوند به صفحه‌ی تصحیح). */
    public function pending(): string
    {
        [$ws, $au] = $this->pendingCounts();
        if ($ws + $au === 0) {
            return '✍️ همه‌ی کارها تصحیح شده‌اند 👏';
        }
        $L = ['⏳ منتظرِ تصحیح:'];
        if ($ws) {
            $L[] = '📄 ' . self::fa($ws) . ' کاربرگ — ' . $this->site('/teacher/worksheet-inbox');
        }
        if ($au) {
            $tasks = AudioSubmission::whereNotNull('file_path')->whereNull('graded_at')
                ->whereHas('task', fn ($q) => $q->where('teacher_id', $this->user->id))->with('task:id,title')->get()->groupBy('audio_task_id');
            foreach ($tasks->take(8) as $items) {
                $t = $items->first()->task;
                $L[] = '🎧 ' . $t->title . ' — ' . self::fa($items->count()) . ' مورد — ' . $this->site('/teacher/audio/' . $t->id);
            }
        }
        $L[] = '';
        $L[] = '(تصحیح از داخلِ بات در فازِ بعد اضافه می‌شود؛ فعلاً پیوند را باز کن.)';

        return implode("\n", $L);
    }

    /** 📋 حضورِ ۷ روزِ اخیر: درصدِ حضور + بیشترین غیبت. */
    public function attendanceWeek(): string
    {
        $from = now()->subDays(6)->startOfDay();
        $L = ['📋 حضور و غیابِ ۷ روزِ اخیر'];
        foreach ($this->classes as $c) {
            $rows = AttendanceRecord::withoutGlobalScopes()->where('classroom_id', $c->id)->where('date', '>=', $from)->get();
            if ($rows->isEmpty()) {
                $L[] = "\n🏫 {$c->name}: در این هفته ثبت نشده.";
                continue;
            }
            $pct = round($rows->whereIn('status', ['present', 'late'])->count() / max(1, $rows->count()) * 100);
            $L[] = "\n🏫 {$c->name}: حضور " . self::fa($pct) . '٪ در ' . self::fa($rows->pluck('date')->unique()->count()) . ' روز';
            $abs = $rows->where('status', 'absent')->countBy('student_id')->sortDesc()->take(5);
            if ($abs->isNotEmpty()) {
                $names = User::whereIn('id', $abs->keys())->pluck('name', 'id');
                $L[] = '  بیشترین غیبت: ' . $abs->map(fn ($n, $id) => ($names[$id] ?? '؟') . ' (' . self::fa($n) . ')')->implode('، ');
            }
        }

        return implode("\n", $L);
    }

    /** 📔 نمره‌های اخیر (۵ فعالیتِ آخر). */
    public function recentGrades(): string
    {
        $cols = GradeColumn::withoutGlobalScopes()->whereIn('classroom_id', $this->ids())->with('grades')
            ->orderByDesc('graded_at')->orderByDesc('id')->limit(6)->get();
        if ($cols->isEmpty()) {
            return '📔 هنوز نمره‌ای در دفتر ثبت نشده.';
        }
        $L = ['📔 فعالیت‌های اخیرِ دفترِ نمره'];
        foreach ($cols as $c) {
            $type = $c->score_type ?: $c->type;
            if ($type === 'numeric') {
                $avg = $c->grades->whereNotNull('score')->avg('score');
                $sum = $avg !== null ? 'میانگین ' . self::fa(round($avg, 1)) . ' از ' . self::fa((float) $c->max) : 'بدونِ نمره';
            } else {
                $sum = $c->grades->whereNotNull('text')->countBy('text')->map(fn ($n, $k) => $k . ' ' . self::fa($n))->implode('، ') ?: 'بدونِ نمره';
            }
            $L[] = "\n• " . ($c->graded_at ? Jalali::format($c->graded_at) . ' — ' : '') . $c->title . ($c->lesson ? " ({$c->lesson})" : '')
                . "\n  {$sum} · ثبت‌شده: " . self::fa($c->grades->count());
        }

        return implode("\n", $L);
    }

    private function riskAlerts(): Collection
    {
        if (! $this->user->school_id) {
            return collect();
        }
        $data = rescue(fn () => app(\App\Services\EarlyWarningService::class)->forSchool((int) $this->user->school_id), ['alerts' => []], false);

        return collect($data['alerts'] ?? [])->whereIn('classroom_id', $this->ids())->values();
    }

    /** ⚠️ نیازمندِ توجه (هشدارِ زودهنگام). */
    public function risk(): string
    {
        $alerts = $this->riskAlerts()->sortBy(fn ($a) => ['crit' => 0, 'warn' => 1, 'info' => 2][$a['level']] ?? 3)->take(10);
        if ($alerts->isEmpty()) {
            return '✅ هشدارِ مهمی دربارهٔ دانش‌آموزانت نیست.';
        }
        $L = ['⚠️ نیازمندِ توجه'];
        foreach ($alerts as $a) {
            $L[] = "\n" . (['crit' => '🔴', 'warn' => '🟠', 'info' => '🔵'][$a['level']] ?? '•') . ' ' . $a['title']
                . (! empty($a['detail']) ? "\n  " . $a['detail'] : '') . (! empty($a['tip']) ? "\n  💡 " . $a['tip'] : '');
        }

        return implode("\n", $L);
    }

    /** 👤 گزارشِ یک دانش‌آموز. */
    public function student(User $s): string
    {
        $L = ['👤 ' . $s->name];
        $att = AttendanceRecord::withoutGlobalScopes()->where('student_id', $s->id)->where('date', '>=', now()->subDays(30))->get();
        if ($att->isNotEmpty()) {
            $L[] = '📋 حضورِ ۳۰ روزِ اخیر: ' . self::fa(round($att->whereIn('status', ['present', 'late'])->count() / $att->count() * 100)) . '٪'
                . ' · غیبت ' . self::fa($att->where('status', 'absent')->count()) . ' · تأخیر ' . self::fa($att->where('status', 'late')->count());
        }
        $grades = Grade::where('student_id', $s->id)->with('gradeColumn')->latest('id')->limit(6)->get()->filter(fn ($g) => $g->gradeColumn);
        if ($grades->isNotEmpty()) {
            $L[] = '📔 نمره‌های اخیر:';
            foreach ($grades as $g) {
                $L[] = '  • ' . $g->gradeColumn->title . ($g->gradeColumn->lesson ? " ({$g->gradeColumn->lesson})" : '') . ': '
                    . ($g->text ?: ($g->score !== null ? self::fa((float) $g->score) . ' از ' . self::fa((float) $g->gradeColumn->max) : '—'));
            }
        }
        $m = rescue(fn () => app(\App\Services\MasteryService::class)->forStudent($s->id), null, false);
        $subs = collect($m['subjects'] ?? [])->filter(fn ($x) => ($x['mastery'] ?? null) !== null)->take(6);
        if ($subs->isNotEmpty()) {
            $L[] = '🧠 تسلط: ' . $subs->map(fn ($x) => ($x['name'] ?? $x['subject'] ?? '') . ' ' . self::fa($x['mastery']) . '٪')->implode('، ');
        }
        if (count($L) === 1) {
            $L[] = 'هنوز سابقه‌ای (حضور، نمره، فعالیت) برای این دانش‌آموز ثبت نشده.';
        }

        return implode("\n", $L);
    }
}
