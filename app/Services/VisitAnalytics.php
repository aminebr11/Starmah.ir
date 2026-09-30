<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\School;
use App\Models\User;
use App\Support\Jalali;
use App\Support\Roles;
use App\Support\VisitTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * موتورِ گزارشِ بازدید و مشارکت — برای ادمینِ کل، مدیرِ مدرسه و معلم.
 *
 * «بازدید» از جدولِ visits می‌آید (یک ردیف برای هر نفر در هر روز) و «انجامِ وظایف»
 * از کارهای واقعیِ ثبت‌شده: مأموریت، محتوا، بازی، آزمون، کاربرگ، تکلیف
 * (برای دانش‌آموز) و ساختنِ محتوا، نمره، حضور و غیاب، پیام (برای معلم).
 */
class VisitAnalytics
{
    public const PERIODS = [7, 30, 90];

    public const ROLE_LABELS = [
        Roles::SUPER_ADMIN => 'ادمینِ کل', Roles::SCHOOL_ADMIN => 'مدیرِ مدرسه',
        Roles::TEACHER => 'معلم', Roles::STUDENT => 'دانش‌آموز', Roles::PARENT => 'ولی', 'guest' => 'مهمان',
    ];

    /** نامِ خوانای بخش‌های سایت برای «پربازدیدترین بخش‌ها». */
    private const SECTIONS = [
        'welcome' => 'صفحه‌ی اصلی', 'about' => 'درباره‌ی ما', 'pricing' => 'تعرفه‌ها', 'install' => 'نصبِ اپ',
        'login' => 'ورود', 'register' => 'ثبت‌نام', 'password' => 'رمزِ عبور', 'dashboard' => 'پیشخوان',
        'missions' => 'مأموریت‌ها', 'gameworld' => 'دنیای بازی‌ها', 'games' => 'بازی‌ها', 'exams' => 'آزمون‌ها',
        'student.smart' => 'آزمونِ هوشمند', 'my.content' => 'محتوای کلاس', 'my.homework' => 'تکلیف‌ها',
        'my.grades' => 'نمره‌ها', 'my.reports' => 'کارنامه', 'my.team' => 'تیمِ من', 'my.activities' => 'فعالیت‌ها',
        'my.discipline' => 'انضباط', 'report' => 'کارنامه', 'progress' => 'پیشرفت', 'leaderboard' => 'رقابتِ تیم‌ها',
        'schedule' => 'برنامه‌ی کلاسی', 'messages' => 'پیام‌ها', 'notices' => 'اعلان‌ها', 'profile' => 'پروفایل',
        'family' => 'خانواده', 'parent' => 'پنلِ والدین', 'world' => 'انتخابِ دنیا',
        'teacher.dashboard' => 'پیشخوانِ معلم', 'teacher.studio' => 'استودیوی بازی', 'teacher.missions' => 'مأموریت‌سازی',
        'teacher.materials' => 'مطالب و محتوا', 'teacher.smart' => 'آزمونِ هوشمند (معلم)', 'teacher.gradebook' => 'دفترِ نمره',
        'teacher.attendance' => 'حضور و غیاب (معلم)', 'teacher.students' => 'دانش‌آموزان (معلم)', 'teacher.worksheets' => 'کاربرگ‌ها',
        'teacher.reports' => 'گزارش‌های معلم', 'teacher' => 'بخش‌های دیگرِ معلم',
        'school' => 'پنلِ مدیرِ مدرسه', 'admin' => 'پنلِ ادمینِ کل', 'bank' => 'بانکِ سؤالات', 'curriculum' => 'دروس',
        'print' => 'چاپ',
    ];

    /* ================= ابزارهای مشترک ================= */

    public static function period($days): int
    {
        $d = (int) $days;
        return in_array($d, self::PERIODS, true) ? $d : 30;
    }

    private function from(int $days): Carbon
    {
        return now()->subDays($days - 1)->startOfDay();
    }

    public function sectionLabel(string $route): string
    {
        $best = null;
        foreach (self::SECTIONS as $prefix => $label) {
            if (($route === $prefix || str_starts_with($route, $prefix . '.')) && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best ? self::SECTIONS[$best] : 'سایرِ بخش‌ها';
    }

    private function onlineSince(): Carbon
    {
        return now()->subMinutes(VisitTracker::ONLINE_MINUTES);
    }

    /** آمارِ بازدیدِ هر کاربر در بازه: روزهای حضور، صفحه‌ها، زمان، آخرین حضور. */
    public function userVisitStats(Collection $userIds, Carbon $from): Collection
    {
        if ($userIds->isEmpty()) return collect();

        return $userIds->chunk(800)->flatMap(fn ($ids) => DB::table('visits')
            ->whereIn('user_id', $ids->all())->where('date', '>=', $from->toDateString())
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as days, SUM(hits) as hits, SUM(seconds) as seconds, MAX(last_at) as last_at')
            ->get())->keyBy('user_id');
    }

    /** سریِ روزانه: بازدیدکننده‌ی یکتا و تعدادِ صفحه — برای نمودار. */
    public function dailySeries(int $days, ?Collection $userIds = null, bool $withGuests = false, ?int $schoolId = null): array
    {
        $from = $this->from($days);
        $q = DB::table('visits')->where('date', '>=', $from->toDateString());
        if ($userIds !== null) $q->whereIn('user_id', $userIds->all() ?: [0]);
        elseif ($schoolId) $q->where('school_id', $schoolId);
        elseif (! $withGuests) $q->whereNotNull('user_id');

        $rows = $q->groupBy('date')->selectRaw(
            'date, SUM(CASE WHEN user_id IS NULL THEN 0 ELSE 1 END) as members, '
            . 'SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guests, SUM(hits) as hits'
        )->get()->keyBy(fn ($r) => substr((string) $r->date, 0, 10));

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $r = $rows[$d->toDateString()] ?? null;
            $out[] = [
                'date' => $d->toDateString(), 'label' => Jalali::ymParts($d)['short'],
                'members' => (int) ($r->members ?? 0), 'guests' => (int) ($r->guests ?? 0), 'hits' => (int) ($r->hits ?? 0),
            ];
        }

        return $out;
    }

    /** توزیعِ بازدید در ساعت‌های شبانه‌روز (۰ تا ۲۳). */
    public function hours(int $days, ?int $schoolId = null): array
    {
        $q = DB::table('visit_hours')->where('date', '>=', $this->from($days)->toDateString());
        if ($schoolId) $q->where('school_id', $schoolId);
        $h = $q->groupBy('hour')->selectRaw('hour, SUM(hits) as hits')->pluck('hits', 'hour');

        return collect(range(0, 23))->map(fn ($i) => (int) ($h[$i] ?? 0))->all();
    }

    /** پربازدیدترین بخش‌ها. */
    public function topSections(int $days, ?int $schoolId = null, ?array $roles = null): array
    {
        $q = DB::table('visit_pages')->where('date', '>=', $this->from($days)->toDateString());
        if ($schoolId) $q->where('school_id', $schoolId);
        if ($roles) $q->whereIn('role', $roles);
        $rows = $q->groupBy('route')->selectRaw('route, SUM(hits) as hits')->get();

        return $rows->groupBy(fn ($r) => $this->sectionLabel($r->route))
            ->map(fn ($g, $label) => ['label' => $label, 'value' => (int) $g->sum('hits')])
            ->sortByDesc('value')->take(10)->values()->all();
    }

    /** سهمِ دستگاه‌ها (اپ / موبایل / کامپیوتر). */
    public function devices(int $days, ?int $schoolId = null, bool $withGuests = true): array
    {
        $q = DB::table('visits')->where('date', '>=', $this->from($days)->toDateString());
        if ($schoolId) $q->where('school_id', $schoolId);
        if (! $withGuests) $q->whereNotNull('user_id');
        $d = $q->groupBy('device')->selectRaw('device, COUNT(*) as c')->pluck('c', 'device');
        $names = ['app' => 'اپِ اندروید', 'mobile' => 'مرورگرِ موبایل', 'desktop' => 'کامپیوتر'];

        return collect($names)->map(fn ($l, $k) => ['label' => $l, 'value' => (int) ($d[$k] ?? 0)])->values()->all();
    }

    /** ردیفِ خلاصه‌ی یک کاربر برای جدول‌ها. */
    private function personRow(User $u, $v, int $days): array
    {
        $online = $u->last_seen_at && $u->last_seen_at->gte($this->onlineSince());

        return [
            'id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar ? $u->avatar_url : null,
            'online' => $online,
            'last_seen' => $u->last_seen_at?->toIso8601String(),
            'last_seen_label' => $this->ago($u->last_seen_at),
            'last_login' => $u->last_login_at ? Jalali::ymParts($u->last_login_at)['short'] . ' · ' . Jalali::fa($u->last_login_at->format('H:i')) : null,
            'days' => (int) ($v->days ?? 0), 'hits' => (int) ($v->hits ?? 0),
            'minutes' => (int) round(($v->seconds ?? 0) / 60),
            'presence' => $days ? (int) round(((int) ($v->days ?? 0)) / $days * 100) : 0,
            'never' => ! $u->last_seen_at && ! $u->last_login_at,
            'logins' => (int) ($u->login_count ?? 0),
        ];
    }

    public function ago(?Carbon $t): string
    {
        if (! $t) return 'هرگز';
        $m = (int) floor($t->diffInMinutes(now(), true));
        if ($m < VisitTracker::ONLINE_MINUTES) return 'همین حالا';
        if ($m < 60) return $this->fa($m) . ' دقیقه پیش';
        if ($m < 1440) return $this->fa(intdiv($m, 60)) . ' ساعت پیش';
        if ($m < 1440 * 30) return $this->fa(intdiv($m, 1440)) . ' روز پیش';

        return Jalali::format($t);
    }

    private function fa($n): string
    {
        return strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /* ================= وظایفِ دانش‌آموز ================= */

    /**
     * کارهای انجام‌شده‌ی هر دانش‌آموز در بازه + درصدِ انجامِ وظایفی که
     * در همین بازه برای کلاسش منتشر شده (محتوا، کاربرگ، تکلیف، بازی، آزمون، روزهای مأموریت).
     *
     * @param  Collection<int,User>  $students  (با classroom_id در ویژگیِ class_id)
     */
    public function studentTasks(Collection $students, Carbon $from): Collection
    {
        $ids = $students->pluck('id')->all();
        if (! $ids) return collect();
        $fromD = $from->toDateString();

        $count = fn (string $table, string $dateCol, array $extra = []) => DB::table($table)
            ->whereIn('student_id', $ids)->where($dateCol, '>=', $from)
            ->when($extra, fn ($q) => $q->where($extra))
            ->groupBy('student_id')->selectRaw('student_id, COUNT(*) c')->pluck('c', 'student_id');

        $missions = DB::table('mission_completions')->whereIn('student_id', $ids)->where('play_date', '>=', $fromD)
            ->groupBy('student_id')->selectRaw('student_id, COUNT(*) c, COUNT(DISTINCT play_date) d')->get()->keyBy('student_id');
        $contents = DB::table('content_views')->whereIn('student_id', $ids)
            ->where(fn ($q) => $q->where('completed_at', '>=', $from)->orWhere(fn ($x) => $x->whereNull('completed_at')->where('viewed', true)->where('updated_at', '>=', $from)))
            ->groupBy('student_id')->selectRaw('student_id, COUNT(*) c')->pluck('c', 'student_id');
        $games = $count('edu_game_attempts', 'completed_at');
        $exams = $count('smart_exam_attempts', 'finished_at');
        $sheets = $count('worksheet_submissions', 'submitted_at');
        $homework = $count('assignment_submissions', 'submitted_at');
        $xp = DB::table('xp_ledger')->whereIn('student_id', $ids)->where('created_at', '>=', $from)->where('amount', '>', 0)
            ->groupBy('student_id')->selectRaw('student_id, SUM(amount) s')->pluck('s', 'student_id');

        // کارهای «منتشرشده در این بازه» برای هر کلاس و این‌که هر دانش‌آموز کدام را انجام داده
        $available = $this->availableByClass($students->pluck('class_id')->filter()->unique()->values(), $from);
        $done = $this->doneItems($ids);

        return $students->mapWithKeys(function ($s) use ($missions, $contents, $games, $exams, $sheets, $homework, $xp, $available, $done) {
            $m = $missions[$s->id] ?? null;
            $av = $available[$s->class_id] ?? ['items' => [], 'mission_days' => 0];
            $mine = $done[$s->id] ?? [];
            $doneItems = count(array_intersect($av['items'], $mine));
            $mDays = min((int) ($m->d ?? 0), $av['mission_days']);
            $den = count($av['items']) + $av['mission_days'];

            $row = [
                'missions' => (int) ($m->c ?? 0), 'mission_days' => (int) ($m->d ?? 0),
                'contents' => (int) ($contents[$s->id] ?? 0), 'games' => (int) ($games[$s->id] ?? 0),
                'exams' => (int) ($exams[$s->id] ?? 0), 'worksheets' => (int) ($sheets[$s->id] ?? 0),
                'homework' => (int) ($homework[$s->id] ?? 0), 'xp' => (int) ($xp[$s->id] ?? 0),
                'assigned' => $den, 'completed' => $doneItems + $mDays,
                'completion' => $den ? (int) round(($doneItems + $mDays) / $den * 100) : null,
            ];
            $row['tasks'] = $row['missions'] + $row['contents'] + $row['games'] + $row['exams'] + $row['worksheets'] + $row['homework'];

            return [$s->id => $row];
        });
    }

    /** برای هر کلاس: کلیدِ کارهای منتشرشده در بازه + تعدادِ روزهایی که مأموریت داشته. */
    private function availableByClass(Collection $classIds, Carbon $from): array
    {
        $out = [];
        $classes = Classroom::whereIn('id', $classIds)->get(['id', 'school_id', 'teacher_id']);
        $today = now()->startOfDay();

        foreach ($classes as $c) {
            $forClass = fn ($q) => $q->where('teacher_id', $c->teacher_id)
                ->where(fn ($x) => $x->whereNull('classroom_id')->orWhere('classroom_id', $c->id));
            $items = [];
            foreach (DB::table('class_contents')->where($forClass)->where('is_visible', true)
                ->whereRaw('COALESCE(publish_at, created_at) >= ?', [$from])->whereRaw('COALESCE(publish_at, created_at) <= ?', [now()])->pluck('id') as $id) $items[] = 'c' . $id;
            foreach (DB::table('worksheets')->where($forClass)->where('is_published', true)
                ->whereRaw('COALESCE(published_at, created_at) >= ?', [$from])->pluck('id') as $id) $items[] = 'w' . $id;
            foreach (DB::table('assignments')->where($forClass)->where('is_published', true)->where('created_at', '>=', $from)->pluck('id') as $id) $items[] = 'a' . $id;
            foreach (DB::table('edu_games')->where('teacher_id', $c->teacher_id)->where('status', 'published')
                ->whereRaw('COALESCE(publish_at, created_at) >= ?', [$from])->pluck('id') as $id) $items[] = 'g' . $id;
            foreach (DB::table('smart_exams')->where('teacher_id', $c->teacher_id)->where('status', 'published')
                ->whereRaw('COALESCE(opens_at, created_at) >= ?', [$from])->whereRaw('COALESCE(opens_at, created_at) <= ?', [now()])->pluck('id') as $id) $items[] = 'e' . $id;

            $missions = Mission::where('is_active', true)->where('teacher_id', $c->teacher_id)
                ->where(fn ($x) => $x->whereNull('classroom_id')->orWhere('classroom_id', $c->id))->get();
            $mDays = 0;
            if ($missions->isNotEmpty()) {
                for ($d = $from->copy(); $d->lte($today); $d->addDay()) {
                    // مأموریت‌ها پیش از ساخته‌شدن حساب نمی‌شوند
                    if ($missions->contains(fn ($m) => $m->created_at->startOfDay()->lte($d) && $m->runsOn($d))) $mDays++;
                }
            }
            $out[$c->id] = ['items' => $items, 'mission_days' => $mDays];
        }

        return $out;
    }

    /** همه‌ی کارهایی که هر دانش‌آموز تا امروز انجام داده (کلیدهای c/w/a/g/e). */
    private function doneItems(array $ids): array
    {
        $done = [];
        $add = function ($rows, string $p) use (&$done) {
            foreach ($rows as $r) $done[$r->student_id][] = $p . $r->k;
        };
        $add(DB::table('content_views')->whereIn('student_id', $ids)->where(fn ($q) => $q->whereNotNull('completed_at')->orWhere('viewed', true))
            ->select('student_id', 'class_content_id as k')->get(), 'c');
        $add(DB::table('worksheet_submissions')->whereIn('student_id', $ids)->where(fn ($q) => $q->whereNotNull('submitted_at')->orWhereNotNull('downloaded_at'))
            ->select('student_id', 'worksheet_id as k')->get(), 'w');
        $add(DB::table('assignment_submissions')->whereIn('student_id', $ids)->whereNotNull('submitted_at')
            ->select('student_id', 'assignment_id as k')->get(), 'a');
        $add(DB::table('edu_game_attempts')->whereIn('student_id', $ids)->where('status', 'completed')
            ->select('student_id', 'edu_game_id as k')->get(), 'g');
        $add(DB::table('smart_exam_attempts')->whereIn('student_id', $ids)->whereNotNull('finished_at')
            ->select('student_id', 'smart_exam_id as k')->get(), 'e');

        return array_map('array_unique', $done);
    }

    /** نمره‌ی مشارکتِ ترکیبی (۰ تا ۱۰۰): حضور ۴۰٪ + انجامِ وظایف ۴۵٪ + زمان ۱۵٪. */
    public function participation(int $presence, ?int $completion, int $minutes, int $days): int
    {
        $time = min(100, (int) round($minutes / max(1, $days * 10) * 100)); // روزی ۱۰ دقیقه = کامل
        $c = $completion ?? $presence;

        return (int) round($presence * 0.40 + $c * 0.45 + $time * 0.15);
    }

    /* ================= جدولِ دانش‌آموزان (مشترکِ مدیر و معلم) ================= */

    public function studentRows(Collection $students, int $days): Collection
    {
        $from = $this->from($days);
        $visits = $this->userVisitStats($students->pluck('id'), $from);
        $tasks = $this->studentTasks($students, $from);

        return $students->map(function ($s) use ($visits, $tasks, $days) {
            $row = $this->personRow($s, $visits[$s->id] ?? null, $days) + ($tasks[$s->id] ?? []);
            $row['class_id'] = $s->class_id;
            $row['class'] = $s->class_name;
            $row['team'] = $s->theme?->name;
            $row['team_emoji'] = $s->theme?->emoji;
            $row['theme_id'] = $s->theme_id;
            $row['score'] = $this->participation($row['presence'], $row['completion'] ?? null, $row['minutes'], $days);

            return $row;
        })->sortByDesc('score')->values();
    }

    /** دانش‌آموزان همراه با کلاس (class_id / class_name). */
    public function studentsOf(Collection $classIds, ?int $schoolId = null): Collection
    {
        $q = User::role(Roles::STUDENT)->with('theme')
            ->leftJoin('classroom_student', 'classroom_student.student_id', '=', 'users.id')
            ->leftJoin('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->select('users.*', 'classrooms.id as class_id', 'classrooms.name as class_name');
        if ($schoolId) $q->where('users.school_id', $schoolId);
        if ($classIds->isNotEmpty() || ! $schoolId) $q->whereIn('classrooms.id', $classIds->all() ?: [0]);

        return $q->get()->unique('id')->values();
    }

    /** رتبه‌بندیِ گروه‌ها (کلاس یا تیم) بر اساسِ نرخِ مشارکت. */
    public function groupRanking(Collection $rows, string $key, string $labelKey, ?string $emojiKey = null): array
    {
        return $rows->filter(fn ($r) => $r[$key] !== null)->groupBy($key)->map(function ($g) use ($labelKey, $emojiKey) {
            $n = $g->count();
            $active = $g->where('days', '>', 0)->count();
            $comp = $g->pluck('completion')->filter(fn ($v) => $v !== null);

            return [
                'label' => $g->first()[$labelKey] ?? '—', 'emoji' => $emojiKey ? ($g->first()[$emojiKey] ?? '') : '',
                'members' => $n, 'active' => $active,
                'rate' => $n ? (int) round($active / $n * 100) : 0,
                'completion' => $comp->isNotEmpty() ? (int) round($comp->avg()) : null,
                'score' => (int) round($g->avg('score')),
                'minutes' => (int) $g->sum('minutes'), 'tasks' => (int) $g->sum('tasks'),
            ];
        })->sortByDesc('score')->values()->all();
    }

    /* ================= کارهای معلم ================= */

    /** کارهای هر معلم در بازه: ساختنِ محتوا/بازی/آزمون/مأموریت/کاربرگ، نمره، حضور و غیاب، پیام. */
    public function teacherTasks(Collection $teacherIds, Carbon $from): Collection
    {
        $ids = $teacherIds->all();
        if (! $ids) return collect();
        $by = fn (string $table, string $col = 'teacher_id', string $date = 'created_at', ?string $distinct = null) => DB::table($table)
            ->whereIn($col, $ids)->where($date, '>=', $from)->groupBy($col)
            ->selectRaw("$col as t, COUNT(" . ($distinct ? "DISTINCT $distinct" : '*') . ') c')->pluck('c', 't');

        $set = [
            'contents' => $by('class_contents'), 'missions' => $by('missions'), 'games' => $by('edu_games'),
            'exams' => $by('smart_exams'), 'worksheets' => $by('worksheets'), 'assignments' => $by('assignments'),
            'activities' => $by('class_activities'), 'grades' => $by('grade_columns'),
            'attendance' => $by('attendance_records', 'recorded_by', 'created_at', 'date'),
            'discipline' => $by('discipline_records', 'recorded_by'),
            'messages' => $by('messages', 'sender_id'),
            'notes' => $by('parent_notes', 'sender_id'),
        ];

        return $teacherIds->mapWithKeys(function ($id) use ($set) {
            $row = collect($set)->map(fn ($m) => (int) ($m[$id] ?? 0))->all();
            $row['produced'] = $row['contents'] + $row['missions'] + $row['games'] + $row['exams'] + $row['worksheets'] + $row['assignments'] + $row['activities'];
            $row['classwork'] = $row['grades'] + $row['attendance'] + $row['discipline'];
            $row['comms'] = $row['messages'] + $row['notes'];

            return [$id => $row];
        });
    }

    /** ردیفِ کاملِ معلم‌ها: بازدید + کارها + مشارکتِ دانش‌آموزانش. */
    public function teacherRows(School $school, int $days, ?Collection $studentRows = null): Collection
    {
        $from = $this->from($days);
        $teachers = User::role(Roles::TEACHER)->where('school_id', $school->id)->get();
        $visits = $this->userVisitStats($teachers->pluck('id'), $from);
        $tasks = $this->teacherTasks($teachers->pluck('id'), $from);
        $classes = Classroom::where('school_id', $school->id)->get(['id', 'name', 'teacher_id'])->groupBy('teacher_id');
        $studentRows ??= $this->studentRows($this->studentsOf(collect(), $school->id), $days);

        return $teachers->map(function ($t) use ($visits, $tasks, $classes, $studentRows, $days) {
            $row = $this->personRow($t, $visits[$t->id] ?? null, $days) + ($tasks[$t->id] ?? []);
            $myClasses = $classes[$t->id] ?? collect();
            $mine = $studentRows->whereIn('class_id', $myClasses->pluck('id'));
            $comp = $mine->pluck('completion')->filter(fn ($v) => $v !== null);
            $row['classes'] = $myClasses->pluck('name')->implode('، ');
            $row['students'] = $mine->count();
            $row['students_active'] = $mine->where('days', '>', 0)->count();
            $row['student_rate'] = $mine->count() ? (int) round($row['students_active'] / $mine->count() * 100) : 0;
            $row['student_completion'] = $comp->isNotEmpty() ? (int) round($comp->avg()) : null;
            $row['student_xp'] = (int) $mine->sum('xp');
            // عملکردِ معلم: حضورِ خودش + فعالیتِ آموزشی + مشارکتِ کلاسش
            $work = min(100, ($row['produced'] ?? 0) * 8 + ($row['classwork'] ?? 0) * 3 + ($row['comms'] ?? 0) * 2);
            $row['score'] = (int) round($row['presence'] * 0.25 + $work * 0.35 + $row['student_rate'] * 0.2 + ($row['student_completion'] ?? $row['student_rate']) * 0.2);

            return $row;
        })->sortByDesc('score')->values();
    }

    /** والدین: آیا وضعیتِ فرزندشان را دنبال می‌کنند؟ */
    public function parentRows(Collection $studentIds, int $days): Collection
    {
        $links = DB::table('parent_student')->whereIn('student_id', $studentIds->all() ?: [0])->get();
        $parents = User::whereIn('id', $links->pluck('parent_id')->unique())->get();
        $visits = $this->userVisitStats($parents->pluck('id'), $this->from($days));
        $kids = User::whereIn('id', $links->pluck('student_id'))->pluck('name', 'id');

        return $parents->map(fn ($p) => $this->personRow($p, $visits[$p->id] ?? null, $days) + [
            'children' => $links->where('parent_id', $p->id)->map(fn ($l) => $kids[$l->student_id] ?? '')->filter()->implode('، '),
        ])->sortByDesc('days')->values();
    }

    /** خلاصه‌ی عددی برای کاشی‌ها. */
    public function summarize(Collection $rows, int $days): array
    {
        $n = $rows->count();
        $active = $rows->where('days', '>', 0)->count();
        $comp = $rows->pluck('completion')->filter(fn ($v) => $v !== null);

        return [
            'total' => $n, 'online' => $rows->where('online', true)->count(),
            'active' => $active, 'rate' => $n ? (int) round($active / $n * 100) : 0,
            'never' => $rows->where('never', true)->count(),
            'inactive7' => $rows->filter(fn ($r) => ! $r['online'] && (! $r['last_seen'] || Carbon::parse($r['last_seen'])->lt(now()->subDays(7))))->count(),
            'hits' => (int) $rows->sum('hits'), 'minutes' => (int) $rows->sum('minutes'),
            'avg_minutes' => $active ? (int) round($rows->sum('minutes') / $active) : 0,
            'completion' => $comp->isNotEmpty() ? (int) round($comp->avg()) : null,
            'tasks' => (int) $rows->sum('tasks'),
        ];
    }
}
