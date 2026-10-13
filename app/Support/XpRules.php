<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * قاعده‌های امتیاز (XP) هر مدرسه — مدیرِ مدرسه تعیین می‌کند کدام منبع امتیاز بدهد و چقدر.
 *
 * سه نوع قاعده:
 *  - fixed  : مقدارِ ثابتِ سیستمی (مثلاً ارسالِ کاربرگ ۱۵) → روشن/خاموش + مقدارِ دلخواه (۰ تا ۱۰۰۰).
 *             جریمه‌ها (sign = -1) هم با «مقدارِ کسر» تنظیم می‌شوند و علامتشان هرگز برعکس نمی‌شود.
 *  - scale  : مقدار را معلم یا خودِ سیستم تعیین می‌کند (مأموریت، بازی، نمره…) → روشن/خاموش + ضریبِ درصدی (۰ تا ۳۰۰٪).
 *  - manual : امتیازِ دستیِ معلم → روشن/خاموش + سقفِ اختیاریِ هر بار (اندازه‌ی مثبت یا منفی).
 *
 * در ستونِ schools.xp_rules فقط «تفاوت با پیش‌فرض» ذخیره می‌شود؛ مدرسه‌ی بی‌تنظیم دقیقاً رفتارِ قبلی را دارد.
 */
class XpRules
{
    public const GROUPS = [
        'missions'   => ['label' => 'مأموریت و تمرین', 'icon' => '🎯'],
        'worksheets' => ['label' => 'کاربرگ و تکلیف', 'icon' => '🎨'],
        'games'      => ['label' => 'بازی و مسابقه', 'icon' => '🎮'],
        'exams'      => ['label' => 'آزمون و نمره', 'icon' => '📝'],
        'attendance' => ['label' => 'حضور و انضباط', 'icon' => '✅'],
        'content'    => ['label' => 'محتوا', 'icon' => '📚'],
        'online'     => ['label' => 'کلاسِ آنلاین', 'icon' => '🎥'],
        'manual'     => ['label' => 'امتیازِ دستیِ معلم', 'icon' => '✋'],
    ];

    /**
     * فهرستِ کاملِ منابعِ XP. ex = نمونه‌ی محاسبه برای قاعده‌های درصدی ({n} = عدد).
     */
    public const RULES = [
        // ── مأموریت و تمرین ──
        'mission' => ['group' => 'missions', 'kind' => 'scale', 'icon' => '🎯', 'label' => 'مأموریت‌های روزانه',
            'desc' => 'امتیازی که معلم برای هر مأموریت (سؤالی یا فعالیت‌محور) تعیین کرده.', 'ex' => [20, 'مأموریتِ {n} امتیازی']],
        'daily_review' => ['group' => 'missions', 'kind' => 'fixed', 'default' => 15, 'icon' => '🔁', 'label' => 'مرورِ امروز',
            'desc' => 'امتیازِ کاملِ کارتِ مرورِ روزانه؛ به نسبتِ درستیِ پاسخ‌ها داده می‌شود.'],
        'treasure_chest' => ['group' => 'missions', 'kind' => 'scale', 'icon' => '🎁', 'label' => 'جعبه‌ی گنجِ روزانه',
            'desc' => 'پاداشِ تمام‌کردنِ همه‌ی مأموریت‌های یک روز، همراه با جایزه‌ی روزهای پیاپی.', 'ex' => [30, 'جعبه‌ی {n} امتیازی']],
        'growth_personal_best' => ['group' => 'missions', 'kind' => 'fixed', 'default' => 5, 'icon' => '🚀', 'label' => 'رکوردِ شخصیِ تازه',
            'desc' => 'وقتی دانش‌آموز در یک مأموریت از بهترین نتیجه‌ی قبلی‌اش بهتر شود.'],
        'growth_retry_fix' => ['group' => 'missions', 'kind' => 'scale', 'icon' => '🛠️', 'label' => 'درست‌کردنِ اشتباه با تلاشِ دوم',
            'desc' => 'برای هر سؤالی که بارِ دوم درست جواب داده شود (۱ امتیاز، حداکثر ۵).', 'ex' => [5, 'پاداشِ {n} امتیازی']],
        'growth_level_up' => ['group' => 'missions', 'kind' => 'fixed', 'default' => 10, 'icon' => '⬆️', 'label' => 'بالا رفتنِ سطحِ یک مهارت',
            'desc' => 'وقتی تسلطِ دانش‌آموز بر یک هدفِ آموزشی یک پله بالا برود.'],
        'remediation' => ['group' => 'missions', 'kind' => 'scale', 'icon' => '🩹', 'label' => 'جبرانِ اشتباه‌ها',
            'desc' => 'امتیازِ ازدست‌رفته‌ای که با مرورِ دوباره‌ی سؤال‌های غلط پس گرفته می‌شود.', 'ex' => [10, 'جبرانِ {n} امتیازی']],

        // ── کاربرگ و تکلیف ──
        'worksheet_download' => ['group' => 'worksheets', 'kind' => 'fixed', 'default' => 5, 'icon' => '📥', 'label' => 'دریافت و دیدنِ کاربرگ',
            'desc' => 'یک‌بار برای هر کاربرگ، وقتی دانش‌آموز آن را باز یا دانلود کند.'],
        'worksheet_submit' => ['group' => 'worksheets', 'kind' => 'fixed', 'default' => 15, 'icon' => '📤', 'label' => 'ارسالِ کاربرگِ پرشده',
            'desc' => 'یک‌بار برای هر کاربرگ، وقتی عکس یا فایلِ پرشده را بفرستد.'],
        'worksheet_grade' => ['group' => 'worksheets', 'kind' => 'scale', 'icon' => '✍️', 'label' => 'تصحیحِ کاربرگ',
            'desc' => 'امتیازی که معلم هنگامِ تصحیحِ کاربرگ می‌دهد.', 'ex' => [20, 'تصحیحِ {n} امتیازی']],
        'audio_submit' => ['group' => 'worksheets', 'kind' => 'fixed', 'default' => 5, 'icon' => '🎙️', 'label' => 'ارسالِ املا و روخوانی',
            'desc' => 'یک‌بار برای هر تکلیفِ املا یا روخوانی، وقتی فرستاده شود (نمره‌اش جدا در دفترِ نمره حساب می‌شود).'],

        // ── بازی و مسابقه ──
        'edu_game' => ['group' => 'games', 'kind' => 'scale', 'icon' => '🎮', 'label' => 'بازی‌های آموزشی',
            'desc' => 'امتیازِ بازی‌های استودیوی بازی (بارِ اولِ تمام‌کردن، به اندازه‌ی امتیازِ بازی).', 'ex' => [40, 'بازیِ {n} امتیازی']],
        'class_game' => ['group' => 'games', 'kind' => 'scale', 'icon' => '🕹️', 'label' => 'بازی‌های کلاسیِ معلم',
            'desc' => 'بازی‌های سؤالیِ بخشِ «بازی‌ها» که معلم ساخته؛ به نسبتِ پاسخِ درست.', 'ex' => [10, 'بازیِ {n} امتیازی']],
        'live_contest' => ['group' => 'games', 'kind' => 'scale', 'icon' => '🏆', 'label' => 'مسابقه‌ی زنده',
            'desc' => 'شرکت ۲، هر پاسخِ درست ۳ و سکوی اول تا سوم ۱۵/۱۰/۵ امتیاز.', 'ex' => [25, 'رتبه‌ی اولِ {n} امتیازی']],

        // ── آزمون و نمره ──
        'smart_exam' => ['group' => 'exams', 'kind' => 'scale', 'icon' => '🧪', 'label' => 'آزمونِ هوشمند',
            'desc' => 'تا ۱۰۰ امتیاز به نسبتِ درصدِ پاسخِ درست (فقط یک‌بار).', 'ex' => [80, 'نتیجه‌ی {n} امتیازی']],
        'online_exam' => ['group' => 'exams', 'kind' => 'scale', 'icon' => '💻', 'label' => 'آزمونِ آنلاین',
            'desc' => 'به اندازه‌ی درصدِ پاسخِ درست در اولین شرکت.', 'ex' => [80, 'نتیجه‌ی {n} امتیازی']],
        'exam_descriptive' => ['group' => 'exams', 'kind' => 'scale', 'icon' => '🖊️', 'label' => 'تصحیحِ تشریحیِ آزمون',
            'desc' => 'تا ۲۰ امتیاز وقتی معلم سؤال‌های تشریحی را تصحیح می‌کند.', 'ex' => [15, 'تصحیحِ {n} امتیازی']],
        'grade_numeric' => ['group' => 'exams', 'kind' => 'scale', 'icon' => '🔢', 'label' => 'نمره‌ی عددیِ دفترِ نمره',
            'desc' => 'نمره به مقیاسِ ۲۰ تبدیل و همان‌قدر امتیاز داده می‌شود (نمره‌ی املا و روخوانی هم).', 'ex' => [18, 'نمره‌ی {n}']],
        'grade_descriptive' => ['group' => 'exams', 'kind' => 'scale', 'icon' => '🌟', 'label' => 'نمره‌ی توصیفی',
            'desc' => 'خیلی خوب ۲۰، خوب ۱۵، قابل قبول ۱۰، نیاز به تلاش ۵ (غایب ۵-).', 'ex' => [20, '«خیلی خوب» با {n} امتیاز']],
        'grade_homework' => ['group' => 'exams', 'kind' => 'scale', 'icon' => '📒', 'label' => 'تکلیفِ دفترِ نمره',
            'desc' => 'کامل ۱۵، ناقص ۸ (انجام نداده یا غایب ۵-).', 'ex' => [15, 'تکلیفِ کاملِ {n} امتیازی']],

        // ── حضور و انضباط ──
        'attendance_present' => ['group' => 'attendance', 'kind' => 'fixed', 'default' => 2, 'icon' => '🙋', 'label' => 'حضور در کلاس',
            'desc' => 'برای هر روزِ حضورِ ثبت‌شده.'],
        'attendance_late' => ['group' => 'attendance', 'kind' => 'fixed', 'default' => 1, 'sign' => -1, 'icon' => '⏰', 'label' => 'تأخیر',
            'desc' => 'امتیازی که برای تأخیر کم می‌شود.'],
        'attendance_absent' => ['group' => 'attendance', 'kind' => 'fixed', 'default' => 3, 'sign' => -1, 'icon' => '🚪', 'label' => 'غیبت',
            'desc' => 'امتیازی که برای غیبتِ بدونِ مرخصی کم می‌شود.'],
        'discipline_positive' => ['group' => 'attendance', 'kind' => 'scale', 'icon' => '⭐', 'label' => 'تشویق‌های دفترِ انضباط',
            'desc' => 'امتیازِ مثبتِ موضوع‌های تشویقی که معلم ثبت می‌کند.', 'ex' => [5, 'تشویقِ {n} امتیازی']],
        'discipline_negative' => ['group' => 'attendance', 'kind' => 'scale', 'sign' => -1, 'icon' => '⚠️', 'label' => 'تذکرهای انضباطی',
            'desc' => 'امتیازی که برای موضوع‌های انضباطی کم می‌شود.', 'ex' => [5, 'تذکرِ {n} امتیازی']],

        // ── محتوا ──
        'content' => ['group' => 'content', 'kind' => 'scale', 'icon' => '🎬', 'label' => 'دیدنِ ویدیو، پادکست و جزوه',
            'desc' => 'یک‌بار برای هر محتوا؛ مقدار را معلم تعیین می‌کند یا از روی مدت حساب می‌شود.', 'ex' => [10, 'ویدیوی {n} امتیازی']],

        // ── کلاسِ آنلاین ──
        'online_attend' => ['group' => 'online', 'kind' => 'fixed', 'default' => 10, 'icon' => '🎥', 'label' => 'حضور در کلاسِ آنلاین',
            'desc' => 'برای کسی که دستِ‌کم نیمی از کلاسِ آنلاین حاضر بوده.'],
        'online_star' => ['group' => 'online', 'kind' => 'fixed', 'default' => 5, 'icon' => '🌟', 'label' => 'ستاره‌ی معلم در کلاسِ آنلاین',
            'desc' => 'هر ستاره‌ای که معلم وسطِ کلاسِ آنلاین به دانش‌آموز می‌دهد.'],
        'online_poll' => ['group' => 'online', 'kind' => 'scale', 'icon' => '📊', 'label' => 'پاسخِ درست به نظرسنجیِ کلاس',
            'desc' => 'امتیازی که معلم برای پاسخِ درستِ نظرسنجیِ کلاسِ آنلاین گذاشته.', 'ex' => [5, 'سؤالِ {n} امتیازی']],

        // ── امتیازِ دستیِ معلم ──
        'teacher_manual' => ['group' => 'manual', 'kind' => 'manual', 'icon' => '✋', 'label' => 'امتیازِ دستی به یک دانش‌آموز',
            'desc' => 'افزودن یا کم‌کردنِ امتیاز از صفحه‌ی امتیازِ دانش‌آموز.'],
        'point_batch' => ['group' => 'manual', 'kind' => 'manual', 'icon' => '⚡', 'label' => 'مرکزِ امتیاز (گروهی و تیمی)',
            'desc' => 'امتیازی که معلم یک‌جا به چند نفر، تیم یا کلِ کلاس می‌دهد.'],
        'class_activity' => ['group' => 'manual', 'kind' => 'manual', 'icon' => '🏅', 'label' => 'فعالیت‌های کلاسی',
            'desc' => 'فعالیت‌های آماده‌ای که معلم تعریف کرده و امتیازش را می‌دهد.'],
        'birthday_gift' => ['group' => 'manual', 'kind' => 'manual', 'icon' => '🎂', 'label' => 'هدیه‌ی تولد',
            'desc' => 'امتیازِ هدیه همراهِ پیامِ تبریکِ تولد.'],
    ];

    public const MAX_FIXED = 1000;
    public const MAX_PCT = 300;
    public const MAX_CAP = 1000;

    /** @var array<int,array> حافظه‌ی همین درخواست */
    private static array $cache = [];
    private static ?int $appId = null;

    /** مقدارِ تنظیم‌شده برای یک دانش‌آموز (۰ یعنی خاموش — امتیازی ثبت نشود). */
    public static function apply(?User $student, string $key, int $amount): int
    {
        return self::forSchool($student?->school_id, $key, $amount);
    }

    public static function forSchool(?int $schoolId, string $key, int $amount): int
    {
        $def = self::RULES[$key] ?? null;
        if ($def === null || ! $schoolId || $amount === 0) {
            return $amount;
        }

        return self::adjust($key, self::rulesFor((int) $schoolId)[$key], $amount);
    }

    /** آیا این منبع در مدرسه روشن است؟ */
    public static function enabled(?int $schoolId, string $key): bool
    {
        return ! $schoolId || ! isset(self::RULES[$key]) || self::rulesFor((int) $schoolId)[$key]['on'];
    }

    /** اعمالِ یک قاعده‌ی حل‌شده روی مقدار؛ علامت هرگز برعکس نمی‌شود. */
    public static function adjust(string $key, array $rule, int $amount): int
    {
        $def = self::RULES[$key];
        if (! $rule['on'] || $amount === 0) {
            return 0;
        }
        $abs = abs($amount);
        $v = match ($def['kind']) {
            'fixed'  => ($def['default'] ?? 0) > 0 ? (int) round($abs * $rule['amount'] / $def['default']) : $abs,
            'scale'  => (int) round($abs * $rule['pct'] / 100),
            'manual' => $rule['cap'] !== null ? min($abs, (int) $rule['cap']) : $abs,
            default  => $abs,
        };

        return $amount < 0 ? -$v : $v;
    }

    /** مقدارهای پیش‌فرضِ یک قاعده. */
    public static function defaults(string $key): array
    {
        $def = self::RULES[$key];

        return match ($def['kind']) {
            'fixed'  => ['on' => true, 'amount' => (int) $def['default']],
            'scale'  => ['on' => true, 'pct' => 100],
            default  => ['on' => true, 'cap' => null],
        };
    }

    /** قاعده‌های حل‌شده‌ی یک مدرسه (پیش‌فرض + تفاوت‌ها). */
    public static function rulesFor(int $schoolId): array
    {
        $app = spl_object_id(app());
        if (self::$appId !== $app) {
            self::$cache = [];
            self::$appId = $app;
        }

        return self::$cache[$schoolId] ??= self::resolve(self::overridesFor($schoolId));
    }

    public static function forget(?int $schoolId = null): void
    {
        if ($schoolId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$schoolId]);
        }
    }

    /** تفاوت‌های ذخیره‌شده‌ی مدرسه (اگر ستون هنوز ساخته نشده، خالی). */
    public static function overridesFor(int $schoolId): array
    {
        try {
            if (! DbSchema::hasColumn('schools', 'xp_rules')) {
                return [];
            }
            $raw = DB::table('schools')->where('id', $schoolId)->value('xp_rules');
        } catch (\Throwable $e) {
            return [];
        }
        $data = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($data) ? $data : [];
    }

    /** پیش‌فرض‌ها + تفاوت‌ها (مقدارهای خارج از بازه اصلاح می‌شوند). */
    public static function resolve(array $overrides): array
    {
        $out = [];
        foreach (self::RULES as $key => $def) {
            $r = self::defaults($key);
            $o = is_array($overrides[$key] ?? null) ? $overrides[$key] : [];
            if (array_key_exists('on', $o)) {
                $r['on'] = (bool) $o['on'];
            }
            if ($def['kind'] === 'fixed' && isset($o['amount']) && is_numeric($o['amount'])) {
                $r['amount'] = max(0, min(self::MAX_FIXED, (int) $o['amount']));
            }
            if ($def['kind'] === 'scale' && isset($o['pct']) && is_numeric($o['pct'])) {
                $r['pct'] = max(0, min(self::MAX_PCT, (int) $o['pct']));
            }
            if ($def['kind'] === 'manual' && array_key_exists('cap', $o)) {
                $r['cap'] = is_numeric($o['cap']) && (int) $o['cap'] > 0 ? min(self::MAX_CAP, (int) $o['cap']) : null;
            }
            $out[$key] = $r;
        }

        return $out;
    }

    /**
     * ورودیِ فرم → فقط تفاوت‌ها با پیش‌فرض (برای ذخیره در schools.xp_rules).
     * کلیدهای ناشناخته کنار گذاشته می‌شوند.
     */
    public static function diff(array $input): array
    {
        $resolved = self::resolve($input);
        $out = [];
        foreach ($resolved as $key => $r) {
            $d = self::defaults($key);
            $delta = [];
            foreach ($r as $field => $value) {
                if ($value !== $d[$field]) {
                    $delta[$field] = $value;
                }
            }
            if ($delta) {
                $out[$key] = $delta;
            }
        }

        return $out;
    }

    /** آیا این کلید در فهرستِ قاعده‌ها هست؟ */
    public static function known(string $key): bool
    {
        return isset(self::RULES[$key]);
    }
}
