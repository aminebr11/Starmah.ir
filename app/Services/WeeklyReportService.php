<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\ClassroomPref;
use App\Models\ParentNote;
use App\Models\ScreenTime;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Support\DbSchema;
use App\Support\Jalali;
use App\Support\SmsGateway;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * «📬 گزارشِ هفتگیِ والدین» + «⏱️ ۱۰ دقیقه با فرزندم».
 * حالت‌ها (برای هر کلاس): دستی (معلم می‌سازد و می‌فرستد) · با تأیید (سیستم سرِ موعد پیش‌نویس می‌سازد، معلم تأیید می‌کند)
 * · خودکار (سرِ موعد ساخته و فرستاده می‌شود).
 */
class WeeklyReportService
{
    public const KEY = 'weekly_report';

    public const DEFAULTS = ['mode' => 'approve', 'day' => 5, 'hour' => 16, 'app' => true, 'sms' => false];

    /** روزهای هفته از شنبه (۰) تا جمعه (۶). */
    public const DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /** «۱۰ دقیقه با فرزندم»: کارهای کوتاه و بی‌وسیله برای خانه، بر اساسِ درس. */
    public const ACTIVITIES = [
        'ریاضی' => [
            ['emoji' => '🛒', 'title' => 'بازیِ فروشگاهِ خانگی', 'steps' => ['سه وسیله‌ی خانه را بردارید و روی هر کدام یک قیمتِ ساده بگذارید.', 'فرزندتان «فروشنده» شود و جمعِ خرید و باقی‌مانده‌ی پول را حساب کند.', 'جای‌تان را عوض کنید؛ این بار شما عمداً یک اشتباه کنید تا او پیدا کند.'], 'tip' => 'اشتباه‌گرفتن از بزرگ‌ترها برای بچه‌ها خیلی شیرین است و اعتمادبه‌نفس می‌سازد.'],
            ['emoji' => '🍽️', 'title' => 'کسرهای سفره', 'steps' => ['یک نان یا میوه را به ۲، ۴ یا ۸ قسمتِ مساوی تقسیم کنید.', 'بپرسید: «اگر سه تکه را بخوریم چه کسری مانده؟»', 'بگذارید خودش یک سؤالِ کسری برای شما بسازد.'], 'tip' => 'دیدن و لمس‌کردن، کسر را از یک مفهومِ ذهنی به تجربه تبدیل می‌کند.'],
            ['emoji' => '🎲', 'title' => 'مسابقه‌ی ذهنیِ ۱۰ ثانیه‌ای', 'steps' => ['با دو تاس یا دو عدد از تقویم، یک ضرب یا جمع بسازید.', 'هر کس زودتر جواب داد یک امتیاز؛ تا ۱۰ امتیاز.', 'آخرِ بازی بپرسید «کدام سخت‌تر بود و چرا؟»'], 'tip' => 'گفت‌وگو درباره‌ی «چرا سخت بود» مهم‌تر از خودِ جواب است.'],
        ],
        'فارسی' => [
            ['emoji' => '📖', 'title' => 'قصه‌خوانیِ نوبتی', 'steps' => ['یک صفحه از کتابِ داستان یا درسِ فارسی را انتخاب کنید.', 'یک جمله شما بخوانید، یک جمله او؛ با صدای شخصیت‌ها!', 'آخر بپرسید: «اگر تو جای قهرمان بودی چه می‌کردی؟»'], 'tip' => 'خواندنِ نوبتی روانیِ خواندن را بدونِ فشار بالا می‌برد.'],
            ['emoji' => '🧩', 'title' => 'کلمه‌سازی', 'steps' => ['یک کلمه‌ی بلند بگویید (مثلاً «کتابخانه»).', 'در ۳ دقیقه هر کدام با حروفِ آن کلمه‌های تازه بسازید.', 'کلمه‌ها را بلند بخوانید و با یکی از آن‌ها جمله بسازید.'], 'tip' => 'بازی با حروف، دقتِ دیداری و املا را تقویت می‌کند.'],
        ],
        'املا' => [
            ['emoji' => '✍️', 'title' => 'املای معکوس', 'steps' => ['۵ کلمه از درسِ این هفته را شما بنویسید و عمداً دو تا را غلط بنویسید.', 'فرزندتان «معلم» شود و غلط‌ها را با خودکارِ رنگی درست کند.', 'برای هر کلمه یک کلمه‌ی هم‌خانواده پیدا کنید.'], 'tip' => 'پیدا کردنِ غلطِ دیگران، دقت به شکلِ کلمه را خیلی بیشتر از تکرار بالا می‌برد.'],
            ['emoji' => '🔤', 'title' => 'کارآگاهِ حرف‌های هم‌صدا', 'steps' => ['یک حرفِ هم‌صدا انتخاب کنید (مثل س/ص/ث).', 'در کتاب‌های خانه ۵ کلمه با هر کدام پیدا کنید.', 'کلمه‌ها را در سه ستون بنویسید و بلند بخوانید.'], 'tip' => 'دسته‌بندیِ کلمه‌ها به ذهن کمک می‌کند شکلِ درست را به خاطر بسپارد.'],
        ],
        'علوم' => [
            ['emoji' => '🔬', 'title' => 'آزمایشِ آشپزخانه', 'steps' => ['یک لیوان آب، کمی نمک و یک تخم‌مرغ (یا میوه) بردارید.', 'پیش‌بینی کنید: در آبِ ساده و آبِ نمک شناور می‌ماند یا نه؟', 'امتحان کنید و درباره‌ی «چرا» با هم حدس بزنید.'], 'tip' => 'پیش‌بینی ← آزمایش ← توضیح؛ همان روشِ دانشمندها!'],
            ['emoji' => '🌱', 'title' => 'دفترچه‌ی طبیعت', 'steps' => ['از پنجره یا حیاط یک گیاه، پرنده یا ابر را ۲ دقیقه تماشا کنید.', 'فرزندتان آن را بکشد و سه ویژگی‌اش را بنویسد.', 'یک سؤال که جوابش را نمی‌دانید بنویسید و فردا با هم جست‌وجو کنید.'], 'tip' => 'کنجکاوی را با «نمی‌دانم، بیا پیدا کنیم» تشویق کنید.'],
        ],
        'عمومی' => [
            ['emoji' => '💬', 'title' => 'سه سؤالِ طلایی', 'steps' => ['بپرسید: «امروز چه چیزِ تازه‌ای یاد گرفتی؟»', 'بپرسید: «کجایش سخت بود و چطور حلش کردی؟»', 'بپرسید: «فردا دوست داری چه چیزی یاد بگیری؟»'], 'tip' => 'به جای «درس خواندی؟» درباره‌ی «یادگیری» بپرسید؛ گفت‌وگو عمیق‌تر می‌شود.'],
            ['emoji' => '🎤', 'title' => 'معلمِ کوچک', 'steps' => ['از فرزندتان بخواهید یک چیز از درس‌های این هفته را به شما «درس بدهد».', 'شما دانش‌آموزِ کنجکاو باشید و دو سؤال بپرسید.', 'در آخر به «معلم» یک ستاره‌ی تشویقی بدهید.'], 'tip' => 'درس‌دادن به دیگران یکی از قوی‌ترین روش‌های یادگیری است.'],
        ],
    ];

    public static function settings(?int $classroomId): array
    {
        return ClassroomPref::get($classroomId, self::KEY, self::DEFAULTS);
    }

    /** شنبه‌ی هفته‌ی جاری (هفته‌ی ایرانی). */
    public static function weekStart(?CarbonInterface $at = null): Carbon
    {
        return Carbon::instance($at ?? now())->startOfWeek(Carbon::SATURDAY)->startOfDay();
    }

    /** اندیسِ روز در هفته‌ی ایرانی (شنبه=۰). */
    public static function dayIndex(?CarbonInterface $at = null): int
    {
        return (($at ?? now())->dayOfWeek + 1) % 7;
    }

    /** آمارِ یک هفته‌ی یک دانش‌آموز. */
    public function stats(User $student, Carbon $from, Carbon $to): array
    {
        $id = $student->id;
        $between = [$from, $to];
        $safe = fn (callable $f, $d = 0) => rescue($f, $d, false);

        $xp = (int) $safe(fn () => DB::table('xp_ledger')->where('student_id', $id)->whereBetween('created_at', $between)->sum('amount'));
        $prevXp = (int) $safe(fn () => DB::table('xp_ledger')->where('student_id', $id)->whereBetween('created_at', [$from->copy()->subWeek(), $from])->sum('amount'));

        $acts = [
            'missions' => $safe(fn () => DB::table('mission_completions')->where('student_id', $id)->whereBetween('play_date', [$from->toDateString(), $to->toDateString()])->count()),
            'games' => $safe(fn () => DB::table('edu_game_attempts')->where('student_id', $id)->whereBetween('completed_at', $between)->count()),
            'exams' => $safe(fn () => DB::table('smart_exam_attempts')->where('student_id', $id)->whereBetween('finished_at', $between)->count()),
            'worksheets' => $safe(fn () => DB::table('worksheet_submissions')->where('student_id', $id)->whereBetween('submitted_at', $between)->count()),
            'audio' => $safe(fn () => DbSchema::hasTable('audio_submissions') ? DB::table('audio_submissions')->where('student_id', $id)->whereBetween('submitted_at', $between)->count() : 0),
            'reviews' => $safe(fn () => DbSchema::hasTable('review_completions') ? DB::table('review_completions')->where('student_id', $id)->whereBetween('play_date', [$from->toDateString(), $to->toDateString()])->count() : 0),
            'contests' => $safe(fn () => DbSchema::hasTable('live_contest_players') ? DB::table('live_contest_players')->where('student_id', $id)->whereBetween('created_at', $between)->count() : 0),
        ];

        $answers = $safe(fn () => DbSchema::hasTable('practice_answers') ? DB::table('practice_answers')->where('practice_answers.student_id', $id)
            ->whereBetween('practice_answers.created_at', $between)
            ->leftJoin('learning_objectives', 'learning_objectives.id', '=', 'practice_answers.objective_id')
            ->get(['practice_answers.correct', 'learning_objectives.label', 'learning_objectives.subject']) : collect(), collect());
        $accuracy = $answers->count() ? (int) round(100 * $answers->where('correct', true)->count() / $answers->count()) : null;
        $byObj = $answers->whereNotNull('label')->groupBy('label')->filter(fn ($g) => $g->count() >= 3)
            ->map(fn ($g) => ['label' => $g->first()->label, 'subject' => $g->first()->subject, 'n' => $g->count(), 'pct' => (int) round(100 * $g->where('correct', true)->count() / $g->count())]);
        $strong = $byObj->sortByDesc('pct')->first();
        $weak = $byObj->sortBy('pct')->first();
        if ($weak && $weak['pct'] >= 75) $weak = null;
        if ($strong && $strong['pct'] < 70) $strong = null;
        if ($strong && $weak && $strong['label'] === $weak['label']) $weak = null;

        $att = $safe(fn () => DB::table('attendance_records')->where('student_id', $id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v)->all(), []);

        $grades = $safe(fn () => DB::table('grades')->join('grade_columns', 'grade_columns.id', '=', 'grades.grade_column_id')
            ->where('grades.student_id', $id)->whereBetween('grades.updated_at', $between)
            ->orderByDesc('grades.updated_at')->limit(5)
            ->get(['grade_columns.title', 'grade_columns.lesson', 'grade_columns.max', 'grades.score', 'grades.text'])
            ->map(fn ($g) => ['title' => $g->title, 'lesson' => $g->lesson, 'value' => $g->text ?: ($g->score !== null ? rtrim(rtrim(number_format((float) $g->score, 2, '.', ''), '0'), '.') . ' از ' . ($g->max ?: 20) : '—')])->all(), []);

        $disc = $safe(fn () => DB::table('discipline_records')->where('student_id', $id)->whereBetween('created_at', $between)
            ->selectRaw('sum(case when points > 0 then 1 else 0 end) as pos, sum(case when points < 0 then 1 else 0 end) as neg')->first(), null);

        $minutes = $safe(fn () => ScreenTime::ready() ? (int) round(ScreenTime::where('student_id', $id)->whereBetween('day', [$from->toDateString(), $to->toDateString()])->sum('seconds') / 60) : null, null);

        return [
            'from' => Jalali::format($from), 'to' => Jalali::format($to),
            'xp' => $xp, 'prev_xp' => $prevXp, 'acts' => $acts, 'total_acts' => array_sum($acts),
            'accuracy' => $accuracy, 'answers' => $answers->count(),
            'strong' => $strong, 'weak' => $weak,
            'attendance' => $att, 'grades' => $grades,
            'discipline' => ['pos' => (int) ($disc->pos ?? 0), 'neg' => (int) ($disc->neg ?? 0)],
            'minutes' => $minutes,
        ];
    }

    /** جمله‌های قابل‌فهم برای والدین. */
    public static function highlights(array $d, string $name): array
    {
        $out = [];
        $fa = fn ($n) => Jalali::fa((string) $n);
        if ($d['xp'] > 0) {
            $trend = $d['prev_xp'] > 0 ? (int) round(100 * ($d['xp'] - $d['prev_xp']) / max(1, $d['prev_xp'])) : null;
            $out[] = '⭐ ' . $fa($d['xp']) . ' امتیاز گرفت' . ($trend !== null && $trend >= 10 ? ' (' . $fa($trend) . '٪ بیشتر از هفته‌ی قبل 👏)' : ($trend !== null && $trend <= -30 ? ' (کمتر از هفته‌ی قبل)' : '')) . '.';
        } else {
            $out[] = '💤 این هفته در سایت فعالیتِ امتیازداری نداشت.';
        }
        $labels = ['missions' => 'مأموریت', 'games' => 'بازی', 'exams' => 'آزمون', 'worksheets' => 'کاربرگ', 'audio' => 'املا/روخوانی', 'reviews' => 'مرور', 'contests' => 'مسابقه'];
        $parts = collect($d['acts'])->filter()->map(fn ($n, $k) => $fa($n) . ' ' . $labels[$k])->values()->all();
        if ($parts) $out[] = '📚 انجام داد: ' . implode('، ', $parts) . '.';
        if ($d['accuracy'] !== null && $d['answers'] >= 5) $out[] = '🎯 دقتِ پاسخ‌ها: ' . $fa($d['accuracy']) . '٪ در ' . $fa($d['answers']) . ' سؤال.';
        if ($d['strong']) $out[] = '💪 نقطه‌ی قوت: «' . $d['strong']['label'] . '» (' . $fa($d['strong']['pct']) . '٪ درست).';
        if ($d['weak']) $out[] = '🌱 نیاز به تمرینِ بیشتر: «' . $d['weak']['label'] . '».';
        $abs = ($d['attendance']['absent'] ?? 0);
        if ($abs) $out[] = '📅 ' . $fa($abs) . ' روز غیبت داشت.';
        elseif (array_sum($d['attendance'])) $out[] = '📅 حضورِ کامل در کلاس 👌';
        if ($d['discipline']['pos']) $out[] = '🌟 ' . $fa($d['discipline']['pos']) . ' بار تشویق شد.';

        return $out;
    }

    /** انتخابِ «۱۰ دقیقه با فرزندم» بر اساسِ نقطه‌ی ضعف یا درسِ هفته. */
    public static function activity(array $d, int $seed): array
    {
        $subject = $d['weak']['subject'] ?? null;
        $key = collect(array_keys(self::ACTIVITIES))->first(fn ($k) => $subject && str_contains($subject, $k)) ?? 'عمومی';
        if (! $subject && $d['grades']) {
            $lesson = (string) ($d['grades'][0]['lesson'] ?? '');
            $key = collect(array_keys(self::ACTIVITIES))->first(fn ($k) => $lesson !== '' && str_contains($lesson, $k)) ?? $key;
        }
        $list = self::ACTIVITIES[$key];
        $a = $list[$seed % count($list)];
        $a['subject'] = $key;
        if ($d['weak']) $a['focus'] = $d['weak']['label'];

        return $a;
    }

    /** ساختن/به‌روز کردنِ پیش‌نویس‌های یک کلاس برای یک هفته. */
    public function build(Classroom $room, ?Carbon $weekStart = null): int
    {
        $from = $weekStart ? $weekStart->copy()->startOfDay() : self::weekStart();
        $to = min($from->copy()->addDays(7)->subSecond(), now());
        $n = 0;
        foreach ($room->students()->get() as $s) {
            $existing = WeeklyReport::where('student_id', $s->id)->whereDate('week_start', $from->toDateString())->first();
            if ($existing && $existing->status === 'sent') continue;
            $data = $this->stats($s, $from, $to);
            $data['highlights'] = self::highlights($data, $s->name);
            $attrs = ['school_id' => $room->school_id, 'classroom_id' => $room->id, 'teacher_id' => $room->teacher_id,
                'data' => $data, 'activity' => self::activity($data, $s->id + (int) $from->format('W'))];
            if ($existing) {
                $existing->update($attrs);
            } else {
                WeeklyReport::create($attrs + ['student_id' => $s->id, 'week_start' => $from->toDateString(), 'status' => 'draft']);
            }
            $n++;
        }

        return $n;
    }

    /** متنِ پیامک (کوتاه). */
    public static function smsText(WeeklyReport $r, string $name): string
    {
        $d = $r->data;
        $fa = fn ($n) => Jalali::fa((string) $n);
        $t = "گزارش هفتگی {$name}\n⭐ " . $fa($d['xp'] ?? 0) . ' امتیاز · ' . $fa($d['total_acts'] ?? 0) . ' فعالیت';
        if (($d['accuracy'] ?? null) !== null) $t .= ' · دقت ' . $fa($d['accuracy']) . '٪';
        if (! empty($d['weak']['label'])) $t .= "\nتمرین بیشتر: " . $d['weak']['label'];
        if ($r->teacher_note) $t .= "\n💬 " . mb_substr($r->teacher_note, 0, 120);
        if (! empty($r->activity['title'])) $t .= "\n⏱ ۱۰ دقیقه با فرزندم: " . $r->activity['title'];
        $t .= "\nجزئیات: استارماه ← بخش والدین";

        return $t;
    }

    /** متنِ کاملِ پیامِ داخلِ برنامه (بخشِ والدین). */
    public static function appText(WeeklyReport $r): string
    {
        $d = $r->data;
        $lines = $d['highlights'] ?? [];
        if (! empty($d['grades'])) {
            $lines[] = '📝 نمره‌های این هفته: ' . collect($d['grades'])->map(fn ($g) => $g['title'] . ' — ' . $g['value'])->implode('؛ ');
        }
        if ($r->teacher_note) $lines[] = "\n💬 یادداشتِ معلم: " . $r->teacher_note;
        $a = $r->activity;
        if ($a) {
            $lines[] = "\n⏱️ ۱۰ دقیقه با فرزندم — {$a['emoji']} {$a['title']}" . (! empty($a['focus']) ? " (برای «{$a['focus']}»)" : '');
            foreach ($a['steps'] as $i => $st) $lines[] = Jalali::fa((string) ($i + 1)) . '. ' . $st;
            $lines[] = '💡 ' . $a['tip'];
        }

        return implode("\n", $lines);
    }

    /** فرستادن به والدین (درون‌برنامه‌ای و/یا پیامک). */
    public function send(WeeklyReport $r, ?User $by = null, ?array $settings = null): array
    {
        $settings ??= self::settings($r->classroom_id);
        $student = $r->student;
        if (! $student) return ['ok' => false];
        $title = '📬 گزارشِ هفتگیِ ' . $student->name . ' (' . ($r->data['from'] ?? '') . ' تا ' . ($r->data['to'] ?? '') . ')';
        $sms = null;

        if ($settings['app'] ?? true) {
            rescue(function () use ($r, $student, $title, $by) {
                ParentNote::create(['school_id' => $student->school_id, 'student_id' => $student->id, 'sender_id' => $by?->id ?? $r->teacher_id,
                    'from_parent' => false, 'title' => $title, 'body' => self::appText($r)]);
                $parents = $student->parents()->pluck('users.id')->all();
                if ($parents) {
                    $ann = Announcement::create(['school_id' => $student->school_id, 'sender_id' => $by?->id ?? $r->teacher_id, 'audience' => 'personal',
                        'title' => $title, 'body' => self::appText($r), 'link' => '/parent?child=' . $student->id]);
                    $ann->recipients()->sync($parents);
                }
            }, null, true);
        }
        if (($settings['sms'] ?? false) && SmsGateway::gatewayReady() && SmsGateway::schoolEnabled($student->school)) {
            $phones = SmsGateway::parentPhones($student);
            $res = $phones ? rescue(fn () => SmsGateway::sendMany($by ?? User::find($r->teacher_id), $student->school,
                array_map(fn ($p) => ['phone' => $p, 'user' => $student], $phones), self::smsText($r, $student->name), 'weekly_report'), ['ok' => false], true) : ['ok' => false];
            $sms = ! $phones ? 'no-phone' : (($res['ok'] ?? false) ? 'sent' : 'failed');
        }
        $r->update(['status' => 'sent', 'sent_at' => now(), 'sms_status' => $sms]);

        return ['ok' => true, 'sms' => $sms];
    }

    /** اجرای زمان‌بندی‌شده (لِیزی از داشبورد + دستورِ artisan). */
    public function runDue(?int $schoolId = null, bool $force = false): int
    {
        if (! WeeklyReport::ready()) return 0;
        $lock = 'weekly-reports-run:' . ($schoolId ?? 'all');
        if (! $force && ! Cache::add($lock, 1, 1800)) return 0;
        $rooms = ClassroomPref::where('key', self::KEY)->pluck('classroom_id');
        $done = 0;
        $q = Classroom::query()->when($schoolId, fn ($q) => $q->where('school_id', $schoolId));
        foreach ($q->get() as $room) {
            $s = $rooms->contains($room->id) ? self::settings($room->id) : self::DEFAULTS;
            if ($s['mode'] === 'manual') continue;
            if (self::dayIndex() < (int) $s['day'] || (self::dayIndex() === (int) $s['day'] && now()->hour < (int) $s['hour'])) continue;
            $week = self::weekStart();
            $have = WeeklyReport::where('classroom_id', $room->id)->whereDate('week_start', $week->toDateString())->exists();
            if (! $have) {
                if (! $room->students()->exists()) continue;
                $this->build($room, $week);
                if ($s['mode'] === 'approve') {
                    if ($room->teacher_id) rescue(function () use ($room) {
                        $ann = Announcement::create(['school_id' => $room->school_id, 'sender_id' => $room->teacher_id, 'audience' => 'personal',
                            'title' => '📬 گزارش‌های هفتگیِ «' . $room->name . '» آماده‌ی تأیید است',
                            'body' => 'یک نگاه بیندازید، اگر خواستید یادداشت بگذارید و «تأیید و ارسال» را بزنید.',
                            'link' => '/teacher/weekly-reports?classroom=' . $room->id]);
                        $ann->recipients()->sync([$room->teacher_id]);
                    }, null, true);
                }
            }
            if ($s['mode'] === 'auto') {
                foreach (WeeklyReport::where('classroom_id', $room->id)->whereDate('week_start', $week->toDateString())->whereIn('status', ['draft', 'approved'])->get() as $r) {
                    $this->send($r, null, $s);
                    $done++;
                }
            }
        }

        return $done;
    }
}
