<?php

namespace App\Services\Bot;

use App\Models\AttendanceRecord;
use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeColumn;
use App\Models\User;
use App\Models\WorksheetSubmission;
use App\Models\XpEntry;
use App\Support\Bot\BotParser as P;
use App\Support\Bot\NameMatch;
use App\Support\DbSchema;
use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 🔎 «چشمِ» دستیار: ابزارهای فقط‌خواندنی که هوش مصنوعی برای جواب دادن صدا می‌زند.
 *
 * هوش مصنوعی هیچ عدد یا نامی را از خودش نمی‌سازد؛ هر جوابی درباره‌ی بچه‌ها، نمره‌ها، حضور،
 * کارهای فرستاده‌شده، آزمون‌ها و امتیازها از همین ابزارها می‌آید. خروجی‌ها فشرده‌اند (برای هزینه
 * و سرعت) و فقط کلاس‌های همین معلم را می‌بینند.
 */
class BotData
{
    private Collection $classes;

    private array $roster;   // [id => name] همه‌ی کلاس‌ها

    private array $classOf;  // [studentId => className]

    public function __construct(private User $user, private TeacherBot $bot)
    {
        $this->classes = $bot->classes($user);
        $this->roster = [];
        $this->classOf = [];
        foreach ($this->classes as $c) {
            foreach ($c->students()->orderBy('name')->pluck('name', 'users.id') as $id => $name) {
                $this->roster[(int) $id] = $name;
                $this->classOf[(int) $id] ??= $c->name;
            }
        }
    }

    /** تعریفِ ابزارهای خواندنی برای مدل (JSON Schema). */
    public static function tools(): array
    {
        $str = ['type' => 'string'];
        $int = ['type' => 'integer'];
        $kind = ['type' => 'string', 'enum' => ['reading', 'dictation', 'worksheet', 'any'], 'description' => 'reading=روخوانی، dictation=املا، worksheet=کاربرگ'];

        return [
            'today_summary' => ['خلاصه‌ی امروز: برنامه، حضور و غیاب، کارهای منتظرِ تصحیح، تولدها', []],
            'list_students' => ['فهرستِ دانش‌آموزانِ کلاس(ها)', ['class' => $str]],
            'attendance' => ['حضور و غیاب: یک روز (date) یا چند روزِ اخیر (days)', ['date' => $str + ['description' => 'YYYY-MM-DD؛ خالی = امروز'], 'days' => $int, 'class' => $str, 'student' => $str]],
            'gradebook' => ['نمره‌های دفترِ نمره (ستون‌ها و نمره‌ی هر دانش‌آموز)', ['class' => $str, 'lesson' => $str, 'student' => $str, 'days' => $int]],
            'submissions' => ['کارهای فرستاده‌شده‌ی بچه‌ها (روخوانی، املا، کاربرگ) با وضعیتِ تصحیح', ['kind' => $kind, 'status' => ['type' => 'string', 'enum' => ['pending', 'graded', 'all']],
                'date' => $str, 'after_hour' => $int, 'days' => $int, 'task' => $str, 'student' => $str]],
            'missing_submissions' => ['چه کسانی تکلیفِ روخوانی/املا/کاربرگ را هنوز نفرستاده‌اند', ['kind' => $kind, 'task' => $str]],
            'tasks' => ['تکلیف‌های روخوانی/املا/کاربرگِ معلم با آمارِ ارسال و تصحیح', ['kind' => $kind, 'days' => $int]],
            'student_profile' => ['کارنامه‌ی کاملِ یک دانش‌آموز: حضور، نمره‌ها، تسلط، امتیاز، انضباط، کارهای فرستاده، آزمون‌ها', ['name' => $str]],
            'points' => ['امتیازِ (XP) بچه‌ها', ['class' => $str, 'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'all']]]],
            'discipline_records' => ['سوابقِ انضباطی (تشویق و تذکر)', ['days' => $int, 'student' => $str]],
            'my_items' => ['فهرستِ آزمون‌ها، بازی‌ها و مسابقه‌های زنده‌ی معلم (وضعیت، تعدادِ سؤال، شرکت‌کننده)', [
                'type' => ['type' => 'string', 'enum' => ['exam', 'game', 'live', 'all']], 'limit' => $int]],
            'item_questions' => ['متنِ کاملِ سؤال‌ها (با گزینه‌ها و پاسخ) از یک آزمون/بازی/مسابقه با عنوان، یا از چند موردِ آخر', [
                'type' => ['type' => 'string', 'enum' => ['exam', 'game', 'live']], 'title' => $str, 'last' => $int + ['description' => '«سه بازیِ آخر» = 3']]],
            'exams' => ['آزمون‌های معلم با تعدادِ شرکت‌کننده و میانگین', []],
            'exam_results' => ['نتیجه‌ی یک آزمون: نمره‌ی هر دانش‌آموز و چه کسانی شرکت نکرده‌اند', ['title' => $str]],
            'games' => ['بازی‌های آموزشی و نتیجه‌ها (با title: نتیجه‌ی هر دانش‌آموز)', ['title' => $str]],
            'live_contests' => ['مسابقه‌های زنده و رتبه‌ها', []],
            'messages' => ['پیام‌ها و سؤال‌های بچه‌ها و والدین به معلم', ['unread_only' => ['type' => 'boolean']]],
            'schedule' => ['برنامه‌ی کلاسیِ یک روز', ['date' => $str]],
        ];
    }

    public function run(string $tool, array $a): array
    {
        try {
            return match ($tool) {
                'today_summary' => ['text' => (new BotReports($this->user, $this->classes))->today()],
                'list_students' => $this->students($a),
                'attendance' => $this->attendance($a),
                'gradebook' => $this->gradebook($a),
                'submissions' => $this->submissions($a),
                'missing_submissions' => $this->missing($a),
                'tasks' => $this->tasks($a),
                'student_profile' => $this->profile($a),
                'points' => $this->points($a),
                'discipline_records' => $this->discipline($a),
                'my_items' => $this->myItems($a),
                'item_questions' => $this->itemQuestions($a),
                'exams' => $this->exams(),
                'exam_results' => $this->examResults($a),
                'games' => $this->games($a),
                'live_contests' => $this->live(),
                'messages' => $this->messages($a),
                'schedule' => $this->schedule($a),
                default => ['error' => 'ابزارِ ناشناخته'],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'خواندنِ داده ناموفق بود'];
        }
    }

    /* ───────────── کمکی ───────────── */

    private function classIds(?string $name): array
    {
        if ($name && ($c = $this->classes->first(fn ($c) => P::has($c->name, $name) || P::has($name, $c->name)))) {
            return [$c->id];
        }

        return $this->classes->pluck('id')->all();
    }

    /** نامِ گفته‌شده → شناسه (دقیق یا نزدیک). */
    private function student(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['id' => null];
        }
        $r = P::namesIn($name, $this->roster);
        if (count($r['ids']) === 1) {
            return ['id' => $r['ids'][0]];
        }
        if ($r['ambiguous']) {
            return ['id' => null, 'ambiguous' => array_values($r['ambiguous'])[0]];
        }
        $f = NameMatch::best($name, $this->roster);

        return $f['id'] ? ['id' => $f['id']] : ['id' => null, 'ambiguous' => $f['ambiguous'], 'not_found' => ! $f['ambiguous']];
    }

    private function when(?Carbon $t): ?string
    {
        return $t ? Jalali::format($t, true) . ' ' . $t->format('H:i') : null;
    }

    private static function day(?string $d): Carbon
    {
        try {
            return $d ? Carbon::parse(P::digits($d))->startOfDay() : now()->startOfDay();
        } catch (\Throwable $e) {
            return now()->startOfDay();
        }
    }

    private static function flat(string $v): string
    {
        return preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{200C}\s\-–_«»"]+/u', '', P::norm($v)) ?? $v;
    }

    /* ───────────── ابزارها ───────────── */

    private function students(array $a): array
    {
        $ids = $this->classIds($a['class'] ?? null);

        return ['classes' => $this->classes->whereIn('id', $ids)->map(fn ($c) => ['class' => $c->name, 'grade' => $c->grade,
            'students' => $c->students()->orderBy('name')->pluck('name')->all()])->values()->all()];
    }

    private function attendance(array $a): array
    {
        $ids = $this->classIds($a['class'] ?? null);
        $st = $this->student($a['student'] ?? null);
        $days = max(1, min(60, (int) ($a['days'] ?? 1)));
        $q = AttendanceRecord::withoutGlobalScopes()->whereIn('classroom_id', $ids)->when($st['id'], fn ($w) => $w->where('student_id', $st['id']));
        if ($days > 1) {
            $rows = $q->where('date', '>=', now()->subDays($days - 1)->startOfDay())->get();
            $per = $rows->groupBy('student_id')->map(fn ($g, $sid) => ['name' => $this->roster[$sid] ?? '—', 'absent' => $g->where('status', 'absent')->count(),
                'late' => $g->where('status', 'late')->count(), 'excused' => $g->where('status', 'excused')->count(), 'recorded_days' => $g->count()])
                ->filter(fn ($r) => $r['absent'] + $r['late'] + $r['excused'] > 0 || ($a['student'] ?? null))->sortByDesc('absent')->values();

            return ['period_days' => $days, 'recorded_days' => $rows->pluck('date')->unique()->count(), 'students_with_absence_or_late' => $per->take(40)->all()];
        }
        $d = self::day($a['date'] ?? null);
        $rows = $q->whereDate('date', $d->toDateString())->get();
        $out = [];
        foreach ($this->classes->whereIn('id', $ids) as $c) {
            $r = $rows->where('classroom_id', $c->id);
            $out[] = $r->isEmpty() ? ['class' => $c->name, 'recorded' => false] : ['class' => $c->name, 'recorded' => true,
                'present' => $r->where('status', 'present')->count(),
                'absent' => $r->where('status', 'absent')->map(fn ($x) => $this->roster[$x->student_id] ?? '—')->values()->all(),
                'late' => $r->where('status', 'late')->map(fn ($x) => $this->roster[$x->student_id] ?? '—')->values()->all(),
                'excused' => $r->where('status', 'excused')->map(fn ($x) => $this->roster[$x->student_id] ?? '—')->values()->all()];
        }

        return ['date' => Jalali::format($d, true), 'classes' => $out] + ($st['id'] ? ['student' => $this->roster[$st['id']]] : []);
    }

    private function gradebook(array $a): array
    {
        $ids = $this->classIds($a['class'] ?? null);
        $st = $this->student($a['student'] ?? null);
        if (! empty($a['student']) && ! $st['id']) {
            return ['student_not_found' => $a['student'], 'similar' => $st['ambiguous'] ?? []];
        }
        $days = max(1, min(365, (int) ($a['days'] ?? 60)));
        $cols = GradeColumn::withoutGlobalScopes()->whereIn('classroom_id', $ids)->where('created_at', '>=', now()->subDays($days))
            ->when(! empty($a['lesson']), fn ($q) => $q->where(fn ($w) => $w->where('lesson', 'like', '%' . $a['lesson'] . '%')->orWhere('title', 'like', '%' . $a['lesson'] . '%')))
            ->latest('id')->limit($st['id'] ? 25 : 8)->get();
        $grades = Grade::whereIn('grade_column_id', $cols->pluck('id'))->when($st['id'], fn ($q) => $q->where('student_id', $st['id']))->get()->groupBy('grade_column_id');

        return ['columns' => $cols->map(fn ($c) => ['title' => $c->title, 'lesson' => $c->lesson, 'type' => $c->score_type ?: $c->type, 'max' => $c->max,
            'date' => $c->graded_at ? Jalali::format($c->graded_at) : Jalali::format($c->created_at),
            'class' => $this->classes->firstWhere('id', $c->classroom_id)?->name,
            'grades' => ($grades[$c->id] ?? collect())->mapWithKeys(fn ($g) => [($this->roster[$g->student_id] ?? '—') => $g->text ?: ($g->score !== null ? (float) $g->score : null)])->all(),
        ])->values()->all()];
    }

    /** کارهای فرستاده‌شده (روخوانی/املا/کاربرگ). */
    private function submissions(array $a): array
    {
        $kind = $a['kind'] ?? 'any';
        $status = $a['status'] ?? 'all';
        $st = $this->student($a['student'] ?? null);
        [$from, $to] = isset($a['date']) || isset($a['after_hour']) || isset($a['days'])
            ? array_slice(BotGrading::window($a), 0, 2) : [now()->subDays(30), now()];
        $rows = collect();
        if ($kind !== 'worksheet' && AudioTask::ready()) {
            $rows = $rows->merge(AudioSubmission::whereNotNull('file_path')->whereBetween('submitted_at', [$from, $to])
                ->whereHas('task', fn ($q) => $q->where('teacher_id', $this->user->id)->when(in_array($kind, ['reading', 'dictation'], true), fn ($w) => $w->where('kind', $kind)))
                ->when($status === 'pending', fn ($q) => $q->whereNull('graded_at'))->when($status === 'graded', fn ($q) => $q->whereNotNull('graded_at'))
                ->when($st['id'], fn ($q) => $q->where('student_id', $st['id']))
                ->with('task:id,title,kind,score_type')->latest('submitted_at')->limit(80)->get()
                ->map(fn ($s) => ['student' => $this->roster[$s->student_id] ?? '—', 'kind' => $s->task->kind === 'reading' ? 'روخوانی' : 'املا', 'task' => $s->task->title,
                    'sent' => $this->when($s->submitted_at), 'graded' => (bool) $s->graded_at, 'grade' => $s->grade ?? ($s->score !== null ? (float) $s->score : null),
                    'mistakes' => $s->mistakes]));
        }
        if (in_array($kind, ['worksheet', 'any'], true) && DbSchema::hasColumn('worksheet_submissions', 'graded_at')) {
            $rows = $rows->merge(WorksheetSubmission::whereNotNull('file_path')->whereBetween('submitted_at', [$from, $to])
                ->whereHas('worksheet', fn ($q) => $q->withoutGlobalScopes()->where('teacher_id', $this->user->id))
                ->when($status === 'pending', fn ($q) => $q->whereNull('graded_at'))->when($status === 'graded', fn ($q) => $q->whereNotNull('graded_at'))
                ->when($st['id'], fn ($q) => $q->where('student_id', $st['id']))
                ->with('worksheet:id,title')->latest('submitted_at')->limit(80)->get()
                ->map(fn ($s) => ['student' => $this->roster[$s->student_id] ?? '—', 'kind' => 'کاربرگ', 'task' => $s->worksheet?->title,
                    'sent' => $this->when($s->submitted_at), 'graded' => (bool) $s->graded_at, 'grade' => $s->grade]));
        }
        if (! empty($a['task'])) {
            $h = self::flat($a['task']);
            $rows = $rows->filter(fn ($r) => str_contains(self::flat((string) $r['task']), $h));
        }

        return ['from' => $this->when($from), 'to' => $this->when($to), 'count' => $rows->count(), 'items' => $rows->values()->take(60)->all()];
    }

    /** چه کسانی هنوز نفرستاده‌اند (آخرین تکلیف‌های منتشرشده، یا همان که عنوانش آمده). */
    private function missing(array $a): array
    {
        $kind = $a['kind'] ?? 'any';
        $out = [];
        $hint = ! empty($a['task']) ? self::flat($a['task']) : null;
        if ($kind !== 'worksheet' && AudioTask::ready()) {
            $tasks = AudioTask::where('teacher_id', $this->user->id)->where('is_published', true)
                ->when(in_array($kind, ['reading', 'dictation'], true), fn ($q) => $q->where('kind', $kind))->latest('id')->limit(20)->get()
                ->filter(fn ($t) => ! $hint || str_contains(self::flat($t->title), $hint))->take(3);
            foreach ($tasks as $t) {
                $class = $this->classes->firstWhere('id', $t->classroom_id);
                $roster = $class ? $class->students()->pluck('name', 'users.id')->all() : [];
                $sent = AudioSubmission::where('audio_task_id', $t->id)->whereNotNull('file_path')->pluck('student_id')->all();
                $out[] = ['task' => $t->title, 'kind' => $t->kind === 'reading' ? 'روخوانی' : 'املا', 'class' => $class?->name, 'due' => $t->due_at ? $this->when($t->due_at) : null,
                    'sent' => count(array_intersect(array_keys($roster), $sent)), 'total' => count($roster),
                    'not_sent' => array_values(array_diff_key($roster, array_flip($sent)))];
            }
        }
        if (in_array($kind, ['worksheet', 'any'], true)) {
            $ws = \App\Models\Worksheet::withoutGlobalScopes()->where('teacher_id', $this->user->id)->where('is_published', true)->latest('id')->limit(20)->get()
                ->filter(fn ($w) => ! $hint || str_contains(self::flat($w->title), $hint))->take(3);
            foreach ($ws as $w) {
                $class = $this->classes->firstWhere('id', $w->classroom_id) ?? ($this->classes->count() === 1 ? $this->classes->first() : null);
                $roster = $class ? $class->students()->pluck('name', 'users.id')->all() : $this->roster;
                $sent = WorksheetSubmission::where('worksheet_id', $w->id)->whereNotNull('file_path')->pluck('student_id')->all();
                $out[] = ['task' => $w->title, 'kind' => 'کاربرگ', 'class' => $class?->name, 'sent' => count(array_intersect(array_keys($roster), $sent)), 'total' => count($roster),
                    'not_sent' => array_values(array_diff_key($roster, array_flip($sent)))];
            }
        }

        return $out ? ['tasks' => $out] : ['tasks' => [], 'note' => 'تکلیفِ منتشرشده‌ای با این مشخصات پیدا نشد'];
    }

    private function tasks(array $a): array
    {
        $kind = $a['kind'] ?? 'any';
        $from = now()->subDays(max(1, min(120, (int) ($a['days'] ?? 30))));
        $out = [];
        if ($kind !== 'worksheet' && AudioTask::ready()) {
            foreach (AudioTask::where('teacher_id', $this->user->id)->where('created_at', '>=', $from)
                ->when(in_array($kind, ['reading', 'dictation'], true), fn ($q) => $q->where('kind', $kind))->withCount([
                    'submissions as sent' => fn ($q) => $q->whereNotNull('file_path'),
                    'submissions as pending' => fn ($q) => $q->whereNotNull('file_path')->whereNull('graded_at'),
                ])->latest('id')->limit(15)->get() as $t) {
                $out[] = ['title' => $t->title, 'kind' => $t->kind === 'reading' ? 'روخوانی' : 'املا', 'class' => $this->classes->firstWhere('id', $t->classroom_id)?->name,
                    'published' => (bool) $t->is_published, 'due' => $t->due_at ? $this->when($t->due_at) : null, 'score_type' => $t->score_type === 'numeric' ? 'عددی' : 'توصیفی',
                    'sent' => (int) $t->sent, 'waiting_for_grading' => (int) $t->pending];
            }
        }
        if (in_array($kind, ['worksheet', 'any'], true)) {
            foreach (\App\Models\Worksheet::withoutGlobalScopes()->where('teacher_id', $this->user->id)->where('created_at', '>=', $from)->latest('id')->limit(15)->get() as $w) {
                $subs = WorksheetSubmission::where('worksheet_id', $w->id)->whereNotNull('file_path');
                $out[] = ['title' => $w->title, 'kind' => 'کاربرگ', 'published' => (bool) $w->is_published, 'sent' => (clone $subs)->count(),
                    'waiting_for_grading' => DbSchema::hasColumn('worksheet_submissions', 'graded_at') ? (clone $subs)->whereNull('graded_at')->count() : null];
            }
        }

        return ['tasks' => $out];
    }

    private function profile(array $a): array
    {
        $st = $this->student($a['name'] ?? null);
        if (! $st['id']) {
            return ['not_found' => $a['name'] ?? '', 'similar' => $st['ambiguous'] ?? [], 'hint' => 'اگر چند نفرند، از معلم بپرس کدام'];
        }
        $id = $st['id'];
        $s = User::find($id);
        $att = AttendanceRecord::withoutGlobalScopes()->where('student_id', $id)->where('date', '>=', now()->subDays(30))->get();
        $grades = Grade::where('student_id', $id)->with('gradeColumn')->latest('id')->limit(12)->get()->filter(fn ($g) => $g->gradeColumn);
        $mastery = rescue(fn () => app(\App\Services\MasteryService::class)->forStudent($id), null, false);
        $xpWeek = (int) XpEntry::where('student_id', $id)->where('created_at', '>=', now()->startOfWeek(Carbon::SATURDAY))->sum('amount');
        $xpAll = (int) XpEntry::where('student_id', $id)->sum('amount');
        $disc = DbSchema::hasTable('discipline_records') ? \App\Models\DisciplineRecord::withoutGlobalScopes()->where('student_id', $id)->where('created_at', '>=', now()->subDays(60))->latest()->limit(8)->get() : collect();
        $exams = \App\Models\SmartExamAttempt::where('student_id', $id)->whereIn('status', ['completed', 'needs_review'])->with('exam:id,title,teacher_id')->latest('id')->limit(6)->get()
            ->filter(fn ($x) => $x->exam && $x->exam->teacher_id === $this->user->id);

        return [
            'name' => $s?->name, 'class' => $this->classOf[$id] ?? null,
            'attendance_30_days' => ['recorded' => $att->count(), 'absent' => $att->where('status', 'absent')->count(), 'late' => $att->where('status', 'late')->count(),
                'absent_dates' => $att->where('status', 'absent')->map(fn ($r) => Jalali::format($r->date))->values()->take(8)->all()],
            'recent_grades' => $grades->map(fn ($g) => ['title' => $g->gradeColumn->title, 'lesson' => $g->gradeColumn->lesson,
                'value' => $g->text ?: ($g->score !== null ? (float) $g->score . ' از ' . (float) $g->gradeColumn->max : null), 'feedback' => $g->feedback])->values()->all(),
            'mastery_percent' => collect($mastery['subjects'] ?? [])->filter(fn ($x) => ($x['mastery'] ?? null) !== null)
                ->mapWithKeys(fn ($x) => [($x['name'] ?? $x['subject'] ?? '?') => $x['mastery']])->all(),
            'points' => ['this_week' => $xpWeek, 'total' => $xpAll],
            'discipline_60_days' => $disc->map(fn ($d) => ['title' => $d->title, 'points' => $d->points, 'note' => $d->note, 'date' => Jalali::format($d->created_at)])->values()->all(),
            'submissions_30_days' => $this->submissions(['student' => $s?->name, 'days' => 30])['items'] ?? [],
            'exams' => $exams->map(fn ($x) => ['title' => $x->exam->title, 'score' => $x->score, 'max' => $x->max_score,
                'percent' => $x->max_score ? (int) round(100 * $x->score / $x->max_score) : null])->values()->all(),
        ];
    }

    private function points(array $a): array
    {
        $ids = $this->classIds($a['class'] ?? null);
        $period = $a['period'] ?? 'week';
        $from = match ($period) {
            'today' => now()->startOfDay(),
            'month' => now()->subDays(30),
            'all' => null,
            default => now()->startOfWeek(Carbon::SATURDAY),
        };
        $out = [];
        foreach ($this->classes->whereIn('id', $ids) as $c) {
            $names = $c->students()->pluck('name', 'users.id');
            $sum = XpEntry::whereIn('student_id', $names->keys())->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->groupBy('student_id')->selectRaw('student_id, SUM(amount) as s')->pluck('s', 'student_id');
            $out[] = ['class' => $c->name, 'period' => $period, 'students' => $names->map(fn ($n, $id) => ['name' => $n, 'points' => (int) ($sum[$id] ?? 0)])
                ->sortByDesc('points')->values()->all()];
        }

        return ['classes' => $out];
    }

    private function discipline(array $a): array
    {
        if (! DbSchema::hasTable('discipline_records')) {
            return ['records' => []];
        }
        $st = $this->student($a['student'] ?? null);
        $rows = \App\Models\DisciplineRecord::withoutGlobalScopes()->whereIn('student_id', array_keys($this->roster))
            ->when($st['id'], fn ($q) => $q->where('student_id', $st['id']))
            ->where('created_at', '>=', now()->subDays(max(1, min(180, (int) ($a['days'] ?? 30)))))->latest()->limit(40)->get();

        return ['records' => $rows->map(fn ($d) => ['student' => $this->roster[$d->student_id] ?? '—', 'title' => $d->title, 'points' => $d->points, 'note' => $d->note,
            'date' => Jalali::format($d->created_at)])->values()->all()];
    }

    private function myItems(array $a): array
    {
        $it = new BotItems($this->user);
        $types = isset(BotItems::TYPES[$a['type'] ?? '']) ? [$a['type']] : array_keys(BotItems::TYPES);
        $limit = max(1, min(30, (int) ($a['limit'] ?? 12)));
        $out = [];
        foreach ($types as $t) {
            $out[$t] = $it->search($t, null, $limit)->map(fn ($m) => $it->summary($t, $m))->all();
        }

        return $out;
    }

    private function itemQuestions(array $a): array
    {
        $it = new BotItems($this->user);
        $t = isset(BotItems::TYPES[$a['type'] ?? '']) ? $a['type'] : 'game';
        $items = ! empty($a['title']) ? $it->search($t, (string) $a['title'], 1) : $it->search($t, null, max(1, min(10, (int) ($a['last'] ?? 1))));
        if ($items->isEmpty()) {
            return ['not_found' => $a['title'] ?? $t];
        }
        $left = 80;

        return ['items' => $items->map(function ($m) use ($it, $t, &$left) {
            $qs = array_slice($it->questions($t, $m), 0, max(0, $left));
            $left -= count($qs);

            return ['title' => $m->title, 'status' => $it->summary($t, $m)['status'], 'questions' => array_map(fn ($q) => [
                'type' => $q['type'], 'prompt' => $q['prompt'], 'choices' => array_column($q['choices'], 'value'), 'answer' => BotQuiz::answerOf($q), 'has_figure' => (bool) $q['media_url'],
            ], $qs)];
        })->values()->all(), 'hint' => 'برای ساختنِ آزمون/بازی/مسابقه از همین‌ها ابزارِ build_from_items را صدا بزن.'];
    }

    private function exams(): array
    {
        $rows = \App\Models\SmartExam::withoutGlobalScopes()->where('teacher_id', $this->user->id)->latest('id')->limit(12)->get();

        return ['exams' => $rows->map(function ($e) {
            $att = \App\Models\SmartExamAttempt::where('smart_exam_id', $e->id)->whereIn('status', ['completed', 'needs_review'])->get(['score', 'max_score', 'status']);

            return ['title' => $e->title, 'status' => $e->status, 'subject' => $e->subject, 'created' => Jalali::format($e->created_at),
                'participants' => $att->count(), 'needs_review' => $att->where('status', 'needs_review')->count(),
                'avg_percent' => $att->count() ? (int) round($att->avg(fn ($x) => $x->max_score ? 100 * $x->score / $x->max_score : 0)) : null];
        })->values()->all()];
    }

    private function examResults(array $a): array
    {
        $h = self::flat((string) ($a['title'] ?? ''));
        $e = \App\Models\SmartExam::withoutGlobalScopes()->where('teacher_id', $this->user->id)->latest('id')->limit(40)->get()
            ->first(fn ($x) => $h === '' || str_contains(self::flat($x->title), $h));
        if (! $e) {
            return ['not_found' => $a['title'] ?? ''];
        }
        $att = \App\Models\SmartExamAttempt::where('smart_exam_id', $e->id)->get()->groupBy('student_id')->map(fn ($g) => $g->sortByDesc('score')->first());
        $done = $att->filter(fn ($x) => in_array($x->status, ['completed', 'needs_review'], true));

        return ['exam' => $e->title, 'status' => $e->status,
            'results' => $done->map(fn ($x, $sid) => ['name' => $this->roster[$sid] ?? '—', 'score' => $x->score, 'max' => $x->max_score,
                'percent' => $x->max_score ? (int) round(100 * $x->score / $x->max_score) : null, 'needs_review' => $x->status === 'needs_review'])->sortByDesc('percent')->values()->all(),
            'in_progress' => $att->where('status', 'in_progress')->keys()->map(fn ($sid) => $this->roster[$sid] ?? '—')->values()->all(),
            'not_started' => array_values(array_diff_key($this->roster, $att->all()))];
    }

    private function games(array $a): array
    {
        $games = \App\Models\EduGame::withoutGlobalScopes()->where('teacher_id', $this->user->id)->latest('id')->limit(20)->get();
        if (! empty($a['title'])) {
            $h = self::flat($a['title']);
            $g = $games->first(fn ($x) => str_contains(self::flat($x->title), $h));
            if (! $g) {
                return ['not_found' => $a['title']];
            }
            $att = \App\Models\EduGameAttempt::where('edu_game_id', $g->id)->get()->groupBy('student_id')->map(fn ($x) => $x->sortByDesc('score')->first());

            return ['game' => $g->title, 'status' => $g->status, 'results' => $att->map(fn ($x, $sid) => ['name' => $this->roster[$sid] ?? '—', 'score' => $x->score, 'max' => $x->max_score,
                'completed' => (bool) $x->completed_at])->sortByDesc('score')->values()->all(), 'not_played' => array_values(array_diff_key($this->roster, $att->all()))];
        }

        return ['games' => $games->map(fn ($g) => ['title' => $g->title, 'status' => $g->status, 'subject' => $g->subject,
            'players' => \App\Models\EduGameAttempt::where('edu_game_id', $g->id)->distinct('student_id')->count('student_id')])->values()->all()];
    }

    private function live(): array
    {
        return ['contests' => \App\Models\LiveContest::withoutGlobalScopes()->where('teacher_id', $this->user->id)->latest('id')->limit(8)->get()
            ->map(fn ($c) => ['title' => $c->title, 'phase' => $c->phase, 'code' => $c->code, 'starts' => $c->starts_at ? $this->when($c->starts_at) : null,
                'players' => $c->players()->count(), 'top' => $c->ranking()->take(5)->map(fn ($r) => $r['name'] . ' (' . $r['score'] . ')')->all()])->values()->all()];
    }

    private function messages(array $a): array
    {
        $q = \App\Models\Message::where('recipient_id', $this->user->id)->with('sender:id,name')->latest()->limit(25);
        if ($a['unread_only'] ?? true) {
            $q->whereNull('read_at');
        }

        return ['messages' => $q->get()->map(fn ($m) => ['from' => $m->sender?->name, 'text' => mb_substr((string) $m->body, 0, 300), 'at' => $this->when($m->created_at),
            'read' => (bool) $m->read_at])->values()->all()];
    }

    private function schedule(array $a): array
    {
        if (! DbSchema::hasTable('schedule_entries')) {
            return ['entries' => []];
        }
        $d = self::day($a['date'] ?? null);
        $dow = ((int) $d->format('w') + 1) % 7;
        $rows = \App\Models\ScheduleEntry::withoutGlobalScopes()->whereIn('classroom_id', $this->classes->pluck('id'))
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('specific_date')->where('day_of_week', $dow))->orWhereDate('specific_date', $d->toDateString()))
            ->orderBy('period')->get();

        return ['date' => Jalali::format($d, true), 'entries' => $rows->map(fn ($r) => ['period' => $r->period, 'time' => $r->time_range, 'title' => $r->title, 'kind' => $r->kind,
            'class' => $this->classes->firstWhere('id', $r->classroom_id)?->name])->values()->all()];
    }
}
