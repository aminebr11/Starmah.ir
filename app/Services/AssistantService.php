<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\AssistantAccess;
use App\Support\Roles;
use App\Support\StudentInsight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * دستیارِ هوشمندِ هر کاربر — راهنمای سایت + دسترسی به داده‌ی همان کاربر.
 * از همان کلید/سرویس‌دهنده‌ی AiContentService استفاده می‌کند؛ بدونِ کلید به یک
 * راهنمای قاعده‌مندِ محلی + خلاصه‌ی واقعیِ داده‌ی کاربر برمی‌گردد (همیشه مفید).
 */
class AssistantService
{
    public function __construct(
        private AiContentService $ai,
        private CrossSubjectService $cross,
        private StudentInsight $insight,
    ) {}

    /** پرونده‌ی تحلیلیِ دانش‌آموز — یک‌بار در هر درخواست ساخته می‌شود. */
    private ?array $cachedProfile = null;
    private ?array $cachedAnalysis = null;

    /**
     * بخش‌هایی از داده که در این درخواست ساخته نشدند.
     *
     * به کاربرِ عادی نشان داده نمی‌شود؛ فقط مدیرِ مدرسه و ادمینِ کل آن را
     * می‌بینند تا بفهمند کدام جدول/مهاجرت روی سرور کم است.
     *
     * @var array<int,string>
     */
    private array $skipped = [];

    /** @return array<int,string> */
    public function skippedParts(): array
    {
        return array_values(array_unique(array_merge($this->skipped, $this->insight->skippedParts())));
    }

    private function student(User $user): array
    {
        if ($this->cachedProfile === null) {
            $this->cachedProfile = $this->insight->profile($user);
            $this->cachedAnalysis = $this->insight->analysis($this->cachedProfile);
        }

        return [$this->cachedProfile, $this->cachedAnalysis];
    }

    /**
     * پاسخِ دستیار.
     *
     * ترتیب: اگر هوش مصنوعی هم تنظیم شده و هم مدرسه اجازه داده → پاسخِ
     * زنده. در هر خطا یا نبودِ کلید → پاسخِ سامانه‌ایِ آماده، که خودش از
     * داده‌ی واقعیِ کاربر ساخته می‌شود و همیشه مفید است.
     *
     * @param array<int,array{role:string,content:string}> $history
     * @return array{reply:string,mode:string}
     */
    public function reply(User $user, array $history, string $message): array
    {
        try {
            $context = $this->userContext($user);
        } catch (\Throwable $e) {
            Log::warning('assistant context failed: ' . get_class($e) . ' — ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            $context = "نام: {$user->name}";
        }

        if ($this->ai->isConfigured() && AssistantAccess::aiAllowed($user)) {
            try {
                $reply = $this->llm($this->systemPrompt($user, $context), $history, $message);
                if (trim($reply) !== '') {
                    return ['reply' => $reply, 'mode' => 'ai', 'skipped' => $this->skippedParts()];
                }
            } catch (\Throwable $e) {
                Log::info('assistant AI unavailable, using templates: ' . $e->getMessage());
            }
        }

        return [
            'reply'   => $this->localReply($user, $message, $context),
            'mode'    => 'local',
            'skipped' => $this->skippedParts(),
        ];
    }

    /**
     * پاسخِ اضطراری — وقتی حتی ساختِ زمینه هم شکست خورده باشد.
     * هرگز استثنا پرتاب نمی‌کند.
     */
    public function safeReply(User $user, string $message): string
    {
        // پله‌ی اول: پاسخِ محلی با زمینه‌ی کامل
        try {
            return $this->localReply($user, $message, $this->userContext($user));
        } catch (\Throwable $e) {
            Log::warning('assistant safeReply(full) failed: ' . get_class($e) . ' — ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        }

        // پله‌ی دوم: همان پاسخِ محلی، بدونِ زمینه‌ی داده‌ای
        try {
            return $this->localReply($user, $message, "نام: {$user->name}");
        } catch (\Throwable $e) {
            Log::warning('assistant safeReply(bare) failed: ' . get_class($e) . ' — ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        }

        // پله‌ی آخر: راهنمای ثابتِ همان نقش
        return $this->staticGuide($user);
    }

    /**
     * اجرای یک پرس‌وجوی داده با تورِ ایمنی.
     *
     * @template T
     * @param  callable():T  $fn
     * @param  T  $default
     * @return T
     */
    private function tryOr(callable $fn, mixed $default, string $part = 'query'): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->skipped[] = $part;
            Log::warning("assistant[{$part}] skipped: " . get_class($e) . ' — ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());

            return $default;
        }
    }

    /** آخرین تورِ ایمنی — متنِ ثابتِ نقش‌محور، بدونِ هیچ پرس‌وجویی. */
    private function staticGuide(User $user): string
    {
        try {
            $role = match (true) {
                $user->hasRole(Roles::TEACHER) => 'teacher',
                $user->hasRole(Roles::SCHOOL_ADMIN) => 'admin',
                $user->hasRole(Roles::SUPER_ADMIN) => 'admin',
                default => 'student',
            };
        } catch (\Throwable) {
            $role = 'student';
        }

        if ($role === 'teacher') {
            return "سلام {$user->name} 👋 من دستیارِ آموزشیِ شما هستم. از این‌جا شروع کنید:\n"
                . "• «مأموریت‌های روزانه» → ساختِ مأموریت برای کلاس\n"
                . "• «گزارش‌ها» → تحلیلِ درس‌به‌درسِ کلاس‌ها\n"
                . "• «مطالب و محتوا» → بارگذاریِ جزوه، پادکست، تکلیف و کاربرگ\n"
                . '• «ارتباط با والدین» و «پیامک به اولیا» → پیام به خانواده‌ها';
        }
        if ($role === 'admin') {
            return "سلام {$user->name} 👋 من تحلیل‌گرِ مدرسه‌ام. از این‌جا شروع کنید:\n"
                . "• «گزارش‌ها» → وضعیتِ کلیِ مدرسه و درس‌های ضعیف\n"
                . "• «معلم‌ها و کلاس‌ها» و «دانش‌آموزان» → مدیریتِ افراد\n"
                . "• «اطلاعیه‌ها» و «سامانه‌ی پیامک» → ارتباط با اولیا\n"
                . '• «گزارش آزمون‌ها» → نتیجه‌ی آزمون‌های مدرسه';
        }

        return "سلام {$user->name} 👋 من دستیارِ ستاره‌ماه‌ام. این‌ها را می‌توانی از من بپرسی:\n"
            . "• «کارنامه» → همه‌ی نمره‌ها و تحلیلِ درس‌به‌درس\n"
            . "• «مأموریت‌های من» → کارِ امروزت و امتیازش\n"
            . "• «محتوای کلاس» → جزوه، پادکست، ویدیو، تکلیف و کاربرگ\n"
            . 'بپرس «گزارش تحلیلی بده» تا وضعیتت را کامل برایت بنویسم.';
    }

    /**
     * دستورِ سیستمیِ نقش‌محور.
     *
     * سه شخصیتِ متفاوت: برای دانش‌آموز «معلمِ راهنما»، برای معلم
     * «دستیارِ آموزشی» و برای مدیر «تحلیلگرِ مدرسه». پیش از این همه یک
     * دستور می‌گرفتند و پاسخ‌ها برای معلم و مدیر بی‌ربط درمی‌آمد.
     */
    private function systemPrompt(User $user, string $context): string
    {
        $guide = $this->siteGuide($user);

        $persona = match (true) {
            $user->isStudent() => "تو «دستیارِ ستاره‌ماه» هستی — مثلِ یک معلمِ راهنمای مهربان که کنارِ دانش‌آموز نشسته.\n"
                . "• لحن: صمیمی، تشویقی، ساده و کودکانه (نه بچگانه). هرگز سرزنش نکن.\n"
                . "• وقتی از وضعیت/رتبه/ضعف می‌پرسد: اول یک جمله ارزیابی، بعد عددهای دقیق، بعد ۲ تا ۳ کارِ عملی.\n"
                . '• خلاق باش: برای درسِ ضعیفش یک تمرینِ کوچکِ امروز پیشنهاد بده که با همین سایت شدنی باشد.',

            $user->hasRole(Roles::TEACHER) => "تو «دستیارِ آموزشیِ ستاره‌ماه» برای یک معلم هستی.\n"
                . "• لحن: حرفه‌ای، کوتاه و عمل‌گرا — مثلِ یک همکارِ باتجربه.\n"
                . "• وقتی درباره‌ی کلاس می‌پرسد: نقطه‌ضعفِ کلاس را با عدد بگو و بعد پیشنهادِ مشخص بده\n"
                . "  (چه مأموریتی بسازد، چه سؤالی از بانک بگذارد، چه محتوایی بارگذاری کند).\n"
                . "• خلاق باش: اگر خواست، طرحِ یک مأموریت یا آزمونِ آماده بنویس — عنوان، تعدادِ سؤال،\n"
                . '  سطحِ دشواری و امتیازِ پیشنهادی — طوری که مستقیم قابلِ ساخت باشد.',

            $user->hasRole(Roles::SCHOOL_ADMIN) => "تو «تحلیلگرِ مدرسه» برای مدیرِ یک مدرسه هستی.\n"
                . "• لحن: مدیریتی و موجز. عدد بده، نه توصیفِ کلی.\n"
                . "• درباره‌ی وضعیتِ کلی، درس‌های ضعیفِ مدرسه، مشارکتِ معلم‌ها و ارتباط با اولیا راهنمایی کن.\n"
                . '• خلاق باش: پیشنهادِ اقدامِ مدیریتی بده (اطلاعیه، پیامک به اولیا، جلسه‌ی گروهِ درسی).',

            default => 'تو «دستیارِ ستاره‌ماه» هستی؛ راهنمای مهربانِ این سامانه‌ی آموزشی.',
        };

        return $persona . "\n\nقواعدِ همیشگی:\n"
            . "۱) عدد و درصد را **فقط** از «اطلاعاتِ کاربر» بردار؛ هیچ عددی از خودت نساز.\n"
            . "۲) اگر داده‌ای نداریم، صادقانه بگو نداریم و بگو از کجا ساخته می‌شود.\n"
            . "۳) کوتاه بنویس و از فهرستِ گلوله‌ای استفاده کن؛ بیش از ۱۲۰ کلمه نشو مگر گزارشِ کامل خواسته شود.\n"
            . "۴) هرگز اطلاعاتِ شخصیِ کاربرانِ دیگر را فاش نکن.\n"
            . "۵) فقط درباره‌ی این سامانه و درس و مدرسه حرف بزن؛ سؤالِ بی‌ربط را مؤدبانه به موضوع برگردان.\n\n"
            . "=== راهنمای سایت ===\n{$guide}\n\n=== اطلاعاتِ کاربر ===\n{$context}";
    }

    /**
     * خلاصه‌ی واقعیِ داده‌ی کاربر (متناسب با نقش).
     *
     * هر بخش جداگانه محافظت می‌شود: اگر یک جدول/ستون روی سرور نباشد،
     * فقط همان سطر نوشته نمی‌شود و دستیار همچنان پاسخِ درست می‌دهد.
     * پیش از این یک استثنا کلِ دستیار را به پیامِ اضطراری می‌انداخت.
     */
    private function userContext(User $user): string
    {
        $lines = ["نام: {$user->name}", 'نقش: ' . $this->roleLabel($user)];
        $add = function (callable $fn, string $part = 'context') use (&$lines) {
            try {
                $fn();
            } catch (\Throwable $e) {
                $this->skipped[] = $part;
                Log::warning("assistant[{$part}] skipped: " . get_class($e) . ' — ' . $e->getMessage()
                    . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            }
        };

        if ($user->hasRole(Roles::STUDENT)) {
            // پرونده‌ی کاملِ تحلیلی: امتیاز، سطح، رتبه، درس‌به‌درس، نمره،
            // آزمون، مأموریت، حضوروغیاب، انضباط + ارزیابی و پیشنهاد.
            $add(function () use ($user, &$lines) {
                [$p, $a] = $this->student($user);
                $lines[] = $this->insight->asText($p, $a);
                $lines[] = 'پیشنهادِ گامِ بعدی: ' . implode(' | ', $a['actions']);
                foreach ($a['weak'] as $w) {
                    $lines[] = "راهکارِ درسِ ضعیف ({$w['subject']}): {$w['tip']}";
                }
            }, 'پرونده‌ی دانش‌آموز');
        } elseif ($user->hasRole(Roles::TEACHER)) {
            $studentIds = collect();
            $add(function () use ($user, &$lines, &$studentIds) {
                $classes = \App\Models\Classroom::where('teacher_id', $user->id)->count();
                $studentIds = User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->pluck('id');
                $lines[] = "تعداد کلاس: {$classes} · تعداد دانش‌آموز: {$studentIds->count()}";
            }, 'کلاس‌ها');

            $add(function () use (&$lines, $studentIds) {
                if ($studentIds->isEmpty()) {
                    return;
                }
                $data = $this->cross->forStudents($studentIds);
                $rows = collect($data['subjects'] ?? [])->filter(fn ($r) => $r['pct'] !== null);
                if ($rows->isNotEmpty()) {
                    $lines[] = 'میانگینِ کلاس‌ها: ' . ($data['overall'] ?? 0) . '٪';
                    $lines[] = 'ضعیف‌ترین درس‌ها: ' . $rows->sortBy('pct')->take(3)
                        ->map(fn ($r) => $r['subject'] . ' ' . $r['pct'] . '٪')->implode('، ');
                }
            }, 'درصدِ درس‌ها');

            // کارهای روی میز — همان چیزهایی که در زنگوله هم می‌آیند
            $add(function () use ($user, &$lines, $studentIds) {
                $pending = \App\Models\ParentNote::whereIn('student_id', $studentIds)
                    ->where('from_parent', true)->whereNull('read_at')->count();
                $subs = \App\Models\WorksheetSubmission::whereHas('worksheet', fn ($q) => $q->where('teacher_id', $user->id))
                    ->whereNotNull('file_path')->where('updated_at', '>=', now()->subDays(14))->count();
                $lines[] = "پیامِ خوانده‌نشده‌ی والدین: {$pending} · کاربرگِ ارسالیِ دو هفته‌ی اخیر: {$subs}";
            }, 'پیام و کاربرگ');
        } elseif ($user->hasRole(Roles::PARENT)) {
            $add(function () use ($user, &$lines) {
                $children = $user->children()->pluck('name')->implode('، ');
                if ($children) {
                    $lines[] = "فرزند(ان): {$children}";
                }
            }, 'فرزندان');
        }

        return implode("\n", $lines);
    }

    /** راهنمای بخش‌های سایت متناسب با نقش. */
    private function siteGuide(User $user): string
    {
        if ($user->hasRole(Roles::STUDENT)) {
            return "بخش‌های دانش‌آموز:\n"
                . "• مأموریت‌های من: هر روز مأموریت‌هایی که معلم گذاشته را انجام بده و امتیاز بگیر.\n"
                . "• دنیای بازی‌ها: بازی‌های آموزشیِ معلم؛ امتیاز فقط بارِ اول.\n"
                . "• آزمون هوشمند: آزمون‌های تطبیقی؛ بعد از پایان، پاسخنامه و کارنامه‌ی هوشمند را ببین.\n"
                . "• کارنامه: خلاصه، درس‌به‌درس، نمرات کلاسی و آزمون هوشمند در یک صفحه با تب.\n"
                . "• رقابت تیم‌ها: امتیازِ تو به تیمت اضافه می‌شود؛ جدولِ رقابت را ببین.\n"
                . "• محتوای کلاس: جزوه/پادکست/گالری؛ گوش‌دادنِ واقعیِ پادکست امتیاز دارد.\n"
                . "• کاربرگ‌ها (در تکالیف/محتوا): دانلود کن، پر کن، برای معلم بفرست؛ امتیازِ یک‌بار.\n"
                . "• ارتباط با معلم: پیام دوسویه با معلم و مدیر.\n"
                . "• امتیاز/سطح: هر ۱۵۰ امتیاز یک سطح بالاتر (قابلِ تنظیم توسط معلم).";
        }
        if ($user->hasRole(Roles::TEACHER)) {
            return "بخش‌های معلم:\n"
                . "• مأموریت‌های روزانه: مأموریت تعریف کن (سؤالِ بانک/پادکست/کاربرگ/بازی) برای تیم یا همه.\n"
                . "• استودیوی بازی و آزمون هوشمند: با بانکِ سؤال یا هوش مصنوعی بساز.\n"
                . "• مطالب و محتوا: جزوه/پادکست/گالری/تکلیف/کاربرگ بارگذاری کن؛ به دانش‌آموز اعلان می‌رود.\n"
                . "• امتیازدهی گروهی و مدیریت امتیازات: به تیم/فرد امتیاز بده، ویرایش و حذف کن.\n"
                . "• کارنامه/گزارش‌ها: تحلیلِ درس‌به‌درسِ کلاس‌ها.\n"
                . "• ارتباط با والدین/مدیر: پیام دوسویه.";
        }
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return "بخش‌های مدیرِ مدرسه: معلم‌ها و کلاس‌ها، دانش‌آموزان، برنامه، اطلاعیه‌ها، حضوروغیاب، "
                . "گزارش آزمون‌ها، بانک سؤالات، ارتباط با والدین/معلم، و گزارش‌های کلی.";
        }
        return "این سایت یک سامانه‌ی آموزشیِ گیمیفای‌شده است: مأموریت، بازی، آزمون، کاربرگ، امتیاز و کارنامه.";
    }

    /**
     * پاسخِ محلی (بدونِ کلیدِ هوش مصنوعی).
     *
     * این دیگر «متنِ آماده» نیست: برای دانش‌آموز از همان پرونده‌ی تحلیلی
     * جواب می‌سازد، پس رتبه، درصدِ درس‌ها، مأموریتِ مانده و پیشنهادها را
     * حتی بدونِ کلید هم درست می‌دهد.
     */
    private function localReply(User $user, string $message, string $context): string
    {
        $m = mb_strtolower(str_replace(['‌', 'ي', 'ك'], [' ', 'ی', 'ک'], $message));
        $has = fn (array $kw) => collect($kw)->contains(fn ($k) => str_contains($m, $k));
        $fa = fn ($n) => \App\Support\Jalali::fa((string) $n);

        if ($user->hasRole(Roles::STUDENT)) {
            [$p, $a] = $this->tryOr(fn () => $this->student($user), [null, null], 'پرونده‌ی دانش‌آموز');
            if ($p === null) {
                return $this->staticGuide($user);
            }

            // رتبه
            if ($has(['رتبه', 'چندم', 'جایگاه', 'نفر چند'])) {
                if (! $p['rank']['in_class']) {
                    return 'هنوز در کلاسی ثبت نشده‌ای یا امتیازی ثبت نشده، پس رتبه‌ای نداریم. با اولین مأموریت یا آزمون، رتبه‌ات ساخته می‌شود.';
                }
                $r = $p['rank'];
                $gap = $r['in_class'] > 1 ? "\n• برای بالا رفتن، مأموریت‌های امروز و یک آزمونِ هوشمند بیشترین امتیاز را می‌دهند." : "\n• نفرِ اولی 🎉 برای ماندن در صدر، ریتمِ روزانه‌ات را نگه دار.";
                return "🏅 رتبه‌ی تو در کلاس: **{$fa($r['in_class'])} از {$fa($r['of'])}**\n"
                    . "• امتیاز کل: {$fa($p['xp'])} · سطح {$fa($p['level'])} · تا سطحِ بعد {$fa($p['to_next'])} امتیاز\n"
                    . "• این هفته {$fa($p['week_xp'])} امتیاز گرفتی (هفته‌ی پیش {$fa($p['prev_week_xp'])}) — روند: {$a['trend']}" . $gap;
            }

            // گزارشِ تحلیلی کامل
            if ($has(['گزارش', 'تحلیل', 'کارنامه', 'وضعیت', 'عملکرد', 'چطورم', 'پیشرفت', 'خلاصه'])) {
                $out = "📊 **گزارشِ تحلیلیِ تو**\n\n" . $a['headline'] . "\n\n" . $this->insight->asText($p, $a);
                $out .= "\n\n🎯 **گامِ بعدی:**\n";
                foreach ($a['actions'] as $x) {
                    $out .= "• {$x}\n";
                }
                if ($a['weak']) {
                    $out .= "\n💡 **راهکارِ درسِ ضعیف:**\n";
                    foreach ($a['weak'] as $w) {
                        $out .= "• {$w['subject']}: {$w['tip']}\n";
                    }
                }
                return trim($out) . "\n\nجزئیاتِ بیشتر را در بخشِ «کارنامه» می‌بینی.";
            }

            // درس‌های ضعیف + راهکار
            if ($has(['ضعیف', 'ضعف', 'پایین', 'چی بخونم', 'چه بخوانم', 'کدام درس', 'کدوم درس'])) {
                if (! $a['weak']) {
                    return "هیچ درسی زیرِ ۶۰٪ نداری 👏\n" . ($p['subjects']['rows']
                        ? 'کمترین درصدت: ' . $p['subjects']['rows'][0]['subject'] . ' ' . $fa($p['subjects']['rows'][0]['pct']) . '٪ — همین را کمی بالاتر ببر.'
                        : 'هنوز فعالیتِ نمره‌داری نداری؛ یک آزمونِ هوشمند بزن تا نقشه‌ی ضعف و قوتت ساخته شود.');
                }
                $out = "📉 درس‌هایی که تمرین می‌خواهند:\n";
                foreach ($a['weak'] as $w) {
                    $out .= "• **{$w['subject']}** — {$fa($w['pct'])}٪\n  {$w['tip']}\n";
                }
                return trim($out);
            }

            // چه کار کنم؟
            if ($has(['چه کار', 'چیکار', 'چکار', 'پیشنهاد', 'توصیه', 'برنامه', 'شروع کنم'])) {
                $out = "🎯 پیشنهادِ من برای همین حالا:\n";
                foreach ($a['actions'] as $x) {
                    $out .= "• {$x}\n";
                }
                return trim($out);
            }

            // امتیاز و سطح
            if ($has(['امتیاز', 'سطح', 'لول', 'ستاره', 'نشان', 'xp'])) {
                return "⚡ امتیاز کل: {$fa($p['xp'])} · سطح {$fa($p['level'])}\n"
                    . "• تا سطحِ بعد {$fa($p['to_next'])} امتیاز مانده\n"
                    . "• این هفته {$fa($p['week_xp'])} امتیاز · نشان‌ها: {$fa($p['badges'])}\n"
                    . '• بیشترین امتیازت از: ' . (collect($p['xp_by_type'])->pluck('label')->take(2)->implode('، ') ?: '—');
            }

            // مأموریت‌ها
            if ($has(['مأموریت', 'ماموریت', 'تمرین امروز'])) {
                if ($p['missions']['pending_today'] === 0) {
                    return "همه‌ی مأموریت‌های امروزت را انجام داده‌ای ✅ (در ۳۰ روزِ اخیر {$fa($p['missions']['done_30d'])} مأموریت). فردا دوباره سر بزن تا جعبه‌ی گنج را هم بگیری.";
                }
                return "🎯 امروز {$fa($p['missions']['pending_today'])} مأموریت مانده — "
                    . "{$fa($p['missions']['pending_xp'])} امتیاز:\n• " . implode("\n• ", $p['missions']['pending_titles'])
                    . "\nاز منو برو به «مأموریت‌های من».";
            }

            // نمره‌ها
            if ($has(['نمره', 'نمرات', 'دفتر نمره'])) {
                if (! $p['grades']) {
                    return 'هنوز نمره‌ی کلاسی برایت ثبت نشده. به‌محضِ ثبت، هم اینجا و هم در تبِ «نمرات کلاسی» کارنامه می‌بینی‌اش.';
                }
                $out = "📔 نمره‌های اخیرت:\n";
                foreach (array_slice($p['grades'], 0, 6) as $g) {
                    $out .= "• {$g['title']}: {$g['value']}  ({$g['date']})\n";
                }
                return trim($out) . "\nهمه‌اش در کارنامه → تبِ «نمرات کلاسی».";
            }

            // حضور و غیاب
            if ($has(['غیبت', 'حضور', 'غایب', 'تأخیر', 'تاخیر'])) {
                $at = $p['attendance'];
                if (! $at) {
                    return 'حضور و غیابی ثبت نشده است.';
                }
                return "🗓️ ۳۰ روزِ اخیر: حاضر {$fa($at['present'])} · غایب {$fa($at['absent'])} · تأخیر {$fa($at['late'])} · موجه {$fa($at['excused'])}";
            }
        }

        // ── معلم: تحلیلِ کلاس و پیشنهادِ عملی ─────────────────────────
        if ($user->hasRole(Roles::TEACHER)) {
            // اگر پرس‌وجو شکست بخورد (جدولِ نبوده، مهاجرتِ اجرانشده) باز هم
            // باید راهنماییِ کاربردی بدهیم، نه پیامِ خطا.
            $ids = $this->tryOr(
                fn () => User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->pluck('id'),
                collect(), 'دانش‌آموزانِ کلاس');
            $rows = $ids->isEmpty() ? collect() : $this->tryOr(
                fn () => collect($this->cross->forStudents($ids)['subjects'] ?? [])->filter(fn ($r) => $r['pct'] !== null),
                collect(), 'درصدِ درس‌ها');

            if ($has(['کلاس', 'وضعیت', 'گزارش', 'تحلیل', 'عملکرد', 'ضعیف', 'ضعف'])) {
                if ($rows->isEmpty()) {
                    return 'هنوز فعالیتِ نمره‌داری در کلاس‌هایت ثبت نشده. با یک مأموریت یا آزمونِ کوتاه شروع کن تا نقشه‌ی ضعف و قوتِ کلاس ساخته شود.';
                }
                // فقط درس‌هایی که واقعاً زیرِ ۷۰٪ هستند «ضعیف»اند؛ وگرنه
                // درسِ ۱۰۰٪ هم در فهرستِ ضعف می‌نشست و گمراه‌کننده بود.
                $weak = $rows->filter(fn ($r) => $r['pct'] < 70)->sortBy('pct')->take(3);
                $out = '📊 وضعیتِ کلاس‌های تو (' . $fa($ids->count()) . " دانش‌آموز)\n"
                    . '• میانگینِ کل: ' . $fa((int) round($rows->avg('pct'))) . "٪\n";

                if ($weak->isEmpty()) {
                    return $out . '• هیچ درسی زیرِ ۷۰٪ نیست 👏 کلاس وضعیتِ خوبی دارد.'
                        . "\n\n💡 برای بالاتر بردن: یک آزمونِ هوشمند بگذار تا نقاطِ ریزتر هم مشخص شود.";
                }

                $out .= '• ضعیف‌ترین درس‌ها: ' . $weak->map(fn ($r) => $r['subject'] . ' ' . $fa($r['pct']) . '٪')->implode('، ') . "\n\n";
                $first = $weak->first();
                $out .= "💡 پیشنهادِ عملی برای «{$first['subject']}»:\n"
                    . "• یک مأموریتِ ۵ سؤالی از بانکِ سؤالِ همان درس بساز (سطح: آسان، امتیاز ۲۰)\n"
                    . "• یک پادکست یا ویدیوی کوتاه در «مطالب و محتوا» بگذار\n"
                    . '• یک کاربرگ بساز و منتشر کن تا تمرینِ نوشتاری هم داشته باشند';

                return $out;
            }
            if ($has(['مأموریت', 'ماموریت'])) {
                $weak = $rows->filter(fn ($r) => $r['pct'] < 70)->sortBy('pct')->first();
                return "🎯 ساختِ مأموریت: منو → «مأموریت‌های روزانه».\n"
                    . "• نوعش می‌تواند سؤالِ بانک، پادکست، ویدیو، کاربرگ، بازی یا آزمون باشد.\n"
                    . "• دوره‌اش را هم مشخص کن: تکرارِ روزانه، یک بازه‌ی چندروزه، یا فقط یک تاریخِ مشخص.\n"
                    . ($weak ? "• پیشنهادِ من: روی «{$weak['subject']}» ({$fa($weak['pct'])}٪) که ضعیف‌ترین درسِ کلاس است." : '');
            }
            if ($has(['کاربرگ'])) {
                return "🎨 کاربرگ: منو → «مطالب و محتوا» → تبِ کاربرگ، یا مستقیم «ساخت کاربرگ».\n"
                    . "• دستی، بارگذاریِ فایل، یا با هوش مصنوعی (به‌همراه تصویرِ تم‌دار).\n"
                    . '• هر سؤالی که بسازی خودکار در بانکِ سؤالت هم ذخیره می‌شود.';
            }
            if ($has(['پیامک', 'اس ام اس', 'sms'])) {
                return 'اگر مدیرِ مدرسه به شما دسترسیِ پیامک داده باشد، از منو → «پیامک به اولیا» می‌توانید به دانش‌آموزانِ کلاسِ خودتان و اولیای آن‌ها پیامک بدهید. سهمیه و سابقه‌ی ارسال همان‌جا دیده می‌شود.';
            }
        }

        // ── مدیرِ مدرسه: نمای کلی و اقدامِ مدیریتی ───────────────────
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            if ($has(['گزارش', 'وضعیت', 'تحلیل', 'مدرسه', 'عملکرد', 'ضعیف'])) {
                $ids = $this->tryOr(
                    fn () => User::role(Roles::STUDENT)->where('school_id', $user->school_id)->pluck('id'),
                    collect(), 'دانش‌آموزانِ مدرسه');
                $teachers = $this->tryOr(
                    fn () => User::role(Roles::TEACHER)->where('school_id', $user->school_id)->count(), 0, 'معلم‌ها');
                $rows = $ids->isEmpty() ? collect() : $this->tryOr(
                    fn () => collect($this->cross->forStudents($ids)['subjects'] ?? [])->filter(fn ($r) => $r['pct'] !== null),
                    collect(), 'درصدِ درس‌ها');
                $out = '🏫 نمای کلیِ مدرسه: ' . $fa($ids->count()) . ' دانش‌آموز · ' . $fa($teachers) . " معلم\n";
                if ($rows->isNotEmpty()) {
                    $weak = $rows->filter(fn ($r) => $r['pct'] < 70)->sortBy('pct')->take(3);
                    $out .= '• میانگینِ کل: ' . $fa((int) round($rows->avg('pct'))) . "٪\n"
                        . ($weak->isEmpty()
                            ? "• هیچ درسی زیرِ ۷۰٪ نیست 👏\n\n"
                            : '• ضعیف‌ترین درس‌ها: ' . $weak->map(fn ($r) => $r['subject'] . ' ' . $fa($r['pct']) . '٪')->implode('، ') . "\n\n")
                        . "💡 اقدامِ پیشنهادی:\n"
                        . "• برای درسِ ضعیف، از معلمِ مربوطه بخواهید یک مأموریتِ هفتگی تعریف کند\n"
                        . "• یک اطلاعیه برای اولیا بگذارید (و در صورتِ نیاز پیامک هم بفرستید)\n"
                        . '• گزارشِ آزمون‌ها را در «گزارش آزمون‌ها» ببینید';
                } else {
                    $out .= 'هنوز فعالیتِ نمره‌داری ثبت نشده تا تحلیل بسازیم.';
                }

                return $out;
            }
            if ($has(['پیامک', 'اس ام اس', 'sms'])) {
                return "📩 منو → «سامانه‌ی پیامک». آنجا سه کار می‌کنید:\n"
                    . "• ارسالِ دستی به اولیا، دانش‌آموزان یا معلم‌ها\n"
                    . "• روشن‌کردنِ اعلان‌های خودکار (نمره، غیبت، تکلیف…) به‌صورتِ پیامک\n"
                    . '• دادنِ دسترسی و سهمیه به هر معلم';
            }
            if ($has(['دستیار', 'هوش مصنوعی'])) {
                return 'دسترسیِ دستیار را از «تنظیماتِ مدرسه» کنترل می‌کنید: می‌توانید دستیار را برای معلم‌ها یا دانش‌آموزان ببندید، یا فقط پاسخ‌های آماده‌ی سامانه را فعال بگذارید تا هزینه‌ی هوش مصنوعی مصرف نشود.';
            }
        }

        // ── راهنمای بخش‌ها (همه‌ی نقش‌ها) ──────────────────────────────
        if ($has(['آزمون', 'امتحان'])) {
            return 'برای آزمون: از منو وارد «آزمون هوشمند» شو، آزمونِ باز را انتخاب و شروع کن. پس از پایان، پاسخنامه و «کارنامه‌ی هوشمند» را می‌بینی.';
        }
        if ($has(['بازی'])) {
            return 'به «دنیای بازی‌ها» برو و یک بازیِ باز را انتخاب کن. امتیاز فقط بارِ اول محاسبه می‌شود؛ دفعاتِ بعد تمرین است.';
        }
        if ($has(['کاربرگ'])) {
            return 'کاربرگ را از «محتوای کلاس» → تبِ «کاربرگ‌ها» باز کن، چاپ یا دانلود کن، پرش کن و عکسش را برای معلم بفرست. دانلود و ارسال هرکدام یک‌بار امتیاز دارند.';
        }
        if ($has(['پادکست', 'محتوا', 'جزوه', 'ویدیو', 'تکلیف'])) {
            return 'همه‌چیز در «محتوای کلاس» است: جزوه، پادکست، ویدیو، گالری، تکالیف و کاربرگ‌ها — هرکدام یک تب. گوش‌دادنِ کاملِ پادکست (بدونِ پرش) امتیاز دارد.';
        }
        if ($has(['پیام', 'ارتباط', 'معلم', 'مدیر', 'والدین'])) {
            return 'از «ارتباط با معلم» پیام بده و سوابق را ببین. هر پیامِ تازه‌ای هم در زنگوله‌ی بالای صفحه می‌آید و با کلیک مستقیم همان گفت‌وگو باز می‌شود.';
        }
        if ($has(['اعلان', 'زنگوله', 'اطلاعیه'])) {
            return 'زنگوله‌ی بالای صفحه همه‌ی اعلان‌ها را یک‌جا دارد: اطلاعیه، پیام، نمره، مأموریت و پیامِ والدین. با کلیک روی هر کدام مستقیم به صفحه‌اش می‌روی و همان‌جا «مطالعه شد» ثبت می‌شود.';
        }

        if ($user->hasRole(Roles::STUDENT)) {
            return "سلام {$user->name} 👋 من دستیارِ ستاره‌ماه‌ام — مثلِ یک معلمِ راهنما.\n"
                . "می‌توانی از من بپرسی:\n"
                . "• «رتبه‌ام چنده؟»  • «گزارش تحلیلی بده»  • «کدوم درسم ضعیفه؟»\n"
                . '• «امروز چه کار کنم؟»  • «نمره‌هام چیه؟»  • «چقدر امتیاز دارم؟»';
        }

        return "سلام {$user->name} 👋 من دستیارِ ستاره‌ماه‌ام. این خلاصه‌ی وضعیتِ توست:\n\n{$context}\n\n"
            . 'درباره‌ی هر بخشِ سایت هم بپرسی راهنمایی می‌کنم.';
    }

    /** فراخوانیِ LLM با system + تاریخچه + پیامِ جدید. */
    private function llm(string $system, array $history, string $message): string
    {
        $turns = collect($history)->filter(fn ($h) => in_array($h['role'] ?? '', ['user', 'assistant'], true))
            ->map(fn ($h) => ['role' => $h['role'], 'content' => mb_substr((string) $h['content'], 0, 1500)])
            ->take(-8)->values()->all();
        $turns[] = ['role' => 'user', 'content' => $message];

        if ($this->ai->provider() === 'openai' && $this->ai->openaiKey()) {
            $res = Http::withToken($this->ai->openaiKey())->timeout(40)->post('https://api.openai.com/v1/chat/completions', [
                'model' => Setting::get('openai_model') ?: 'gpt-4o-mini',
                'max_tokens' => 600,
                'messages' => array_merge([['role' => 'system', 'content' => $system]], $turns),
            ]);
            if (! $res->successful()) throw new \RuntimeException('openai ' . $res->status());
            return trim((string) data_get($res->json(), 'choices.0.message.content', ''));
        }

        $res = Http::withHeaders([
            'x-api-key' => $this->ai->anthropicKey(),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(40)->post('https://api.anthropic.com/v1/messages', [
            'model' => Setting::get('anthropic_model') ?: env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
            'max_tokens' => 600,
            'system' => $system,
            'messages' => $turns,
        ]);
        if (! $res->successful()) throw new \RuntimeException('anthropic ' . $res->status());
        return trim((string) data_get($res->json(), 'content.0.text', ''));
    }

    private function roleLabel(User $u): string
    {
        return match (true) {
            $u->hasRole(Roles::SCHOOL_ADMIN) => 'مدیرِ مدرسه',
            $u->hasRole(Roles::TEACHER) => 'معلم',
            $u->hasRole(Roles::PARENT) => 'والد',
            $u->hasRole(Roles::STUDENT) => 'دانش‌آموز',
            default => 'کاربر',
        };
    }
}
