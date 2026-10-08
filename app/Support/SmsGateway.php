<?php

namespace App\Support;

use App\Models\School;
use App\Models\SmsMessage;
use App\Models\StudentSmsSetting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

/**
 * دروازه‌ی پیامکِ مدرسه — سه لایه‌ی دسترسی و سهمیه، بالای درایورِ ارسال.
 *
 *   ادمینِ کل  → درگاه را روشن و کلید را وارد می‌کند
 *   ادمینِ کل  → برای هر مدرسه «اجازه» و «سهمیه‌ی ماهانه» می‌دهد
 *   مدیرِ مدرسه → به هر معلم اجازه و سهمیه می‌دهد
 *
 * هیچ ارسالی بدونِ عبور از اینجا انجام نمی‌شود؛ پس سهمیه، گزارش و لاگ
 * هرگز از واقعیت دور نمی‌افتند (مصرف از روی همان جدولِ لاگ شمرده می‌شود،
 * نه یک شمارنده‌ی جداگانه که بشود با آن ناهماهنگ شد).
 */
class SmsGateway
{
    /** پنجره‌ی سهمیه: ۳۰ روزِ اخیر (ساده، قابلِ فهم و مستقل از تقویم). */
    public const WINDOW_DAYS = 30;

    /** رویدادهایی که می‌توانند پیامک شوند. */
    public const EVENTS = [
        'welcome'      => ['label' => '👋 خوش‌آمدِ ثبت‌نام', 'hint' => 'بلافاصله پس از ثبت‌نامِ دانش‌آموز (خودش یا توسطِ معلم) — با اطلاعاتِ ورود'],
        'grade'        => ['label' => '📔 نمره‌ی کلاسی', 'hint' => 'وقتی معلم نمره‌ای ثبت می‌کند'],
        'discipline'   => ['label' => '⭐ تشویق و تذکر', 'hint' => 'ثبتِ موردِ انضباطی'],
        'absence'      => ['label' => '🗓️ غیبت و تأخیر', 'hint' => 'ثبتِ غیبت یا تأخیر در حضوروغیاب'],
        'homework'     => ['label' => '📝 تکلیفِ جدید', 'hint' => 'انتشارِ تکلیف در محتوای کلاس'],
        'worksheet'    => ['label' => '🎨 کاربرگِ جدید', 'hint' => 'انتشارِ کاربرگ برای کلاس'],
        'content'      => ['label' => '📚 محتوای درسیِ جدید', 'hint' => 'جزوه، پادکست، ویدیو و گالری'],
        'mission'      => ['label' => '🎯 مأموریتِ روزانه', 'hint' => 'ساختِ مأموریتِ تازه'],
        'exam'         => ['label' => '🧠 آزمونِ جدید', 'hint' => 'انتشارِ آزمون یا بازیِ تازه'],
        'announcement' => ['label' => '📢 اطلاعیه‌ی مدرسه', 'hint' => 'اطلاعیه‌های عمومیِ مدرسه'],
    ];

    /** گیرنده‌های ممکنِ هر رویداد. */
    public const AUDIENCES = ['parent' => 'ولی', 'student' => 'خودِ دانش‌آموز'];

    /** آیا درگاهِ سراسری آماده است؟ */
    public static function gatewayReady(): bool
    {
        return app(SmsService::class)->enabled();
    }

    /** آیا این مدرسه اجازه‌ی پیامک دارد؟ */
    public static function schoolEnabled(?School $school): bool
    {
        return $school !== null && (bool) $school->sms_enabled;
    }

    /** مصرفِ ۳۰ روزِ اخیرِ یک مدرسه (بر حسبِ قطعه). */
    public static function usedBySchool(int $schoolId): int
    {
        return (int) SmsMessage::withoutGlobalScopes()->where('school_id', $schoolId)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->sum('segments');
    }

    /** مصرفِ ۳۰ روزِ اخیرِ یک فرستنده. */
    public static function usedByUser(int $userId): int
    {
        return (int) SmsMessage::withoutGlobalScopes()->where('sender_id', $userId)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->sum('segments');
    }

    /** باقی‌مانده‌ی سهمیه‌ی مدرسه — null یعنی بدونِ سقف. */
    public static function remainingForSchool(?School $school): ?int
    {
        if (! $school || $school->sms_quota === null) {
            return null;
        }

        return max(0, (int) $school->sms_quota - self::usedBySchool($school->id));
    }

    /** باقی‌مانده‌ی سهمیه‌ی یک کاربر — null یعنی بدونِ سقفِ شخصی. */
    public static function remainingForUser(User $user): ?int
    {
        if ($user->sms_quota === null) {
            return null;
        }

        return max(0, (int) $user->sms_quota - self::usedByUser($user->id));
    }

    /**
     * آیا این کاربر می‌تواند پیامک بفرستد؟
     *
     * @return array{ok:bool,reason:string}
     */
    public static function can(User $user): array
    {
        if (! self::gatewayReady()) {
            return ['ok' => false, 'reason' => 'سامانه‌ی پیامک هنوز توسطِ ادمینِ کل فعال نشده است.'];
        }
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return ['ok' => true, 'reason' => ''];
        }

        $school = $user->school;
        if (! self::schoolEnabled($school)) {
            return ['ok' => false, 'reason' => 'برای این مدرسه دسترسیِ پیامک فعال نشده است.'];
        }
        if (self::remainingForSchool($school) === 0) {
            return ['ok' => false, 'reason' => 'سهمیه‌ی پیامکِ مدرسه در ۳۰ روزِ اخیر تمام شده است.'];
        }

        // مدیرِ مدرسه همیشه؛ معلم فقط با اجازه‌ی مدیر
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return ['ok' => true, 'reason' => ''];
        }
        if ($user->hasRole(Roles::TEACHER)) {
            if (! $user->sms_allowed) {
                return ['ok' => false, 'reason' => 'مدیرِ مدرسه هنوز به شما دسترسیِ پیامک نداده است.'];
            }
            if (self::remainingForUser($user) === 0) {
                return ['ok' => false, 'reason' => 'سهمیه‌ی شخصیِ شما در ۳۰ روزِ اخیر تمام شده است.'];
            }

            return ['ok' => true, 'reason' => ''];
        }

        return ['ok' => false, 'reason' => 'نقشِ شما اجازه‌ی ارسالِ پیامک ندارد.'];
    }

    /**
     * ارسالِ گروهی + ثبتِ لاگ + رعایتِ سهمیه.
     *
     * @param  array<int,array{user?:?User,phone:string,name?:string}>  $targets
     * @return array{ok:bool,sent:int,failed:int,skipped:int,message:string}
     */
    public static function sendMany(?User $sender, ?School $school, array $targets, string $body, string $kind = 'manual'): array
    {
        $sms = app(SmsService::class);
        $body = trim($body);

        if ($body === '') {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'message' => 'متنِ پیام خالی است.'];
        }

        // شماره‌های تکراری و نامعتبر حذف می‌شوند — و در گزارش شمرده
        $clean = [];
        $badNumbers = 0;     // شماره‌ی نامعتبر
        $overQuota = 0;      // ردشده به‌دلیلِ سهمیه
        foreach ($targets as $t) {
            $phone = $sms->normalize((string) ($t['phone'] ?? ''));
            if ($phone === '') {
                $badNumbers++;
                continue;
            }
            if (isset($clean[$phone])) {
                continue;     // تکراری — نه خطا، نه ردشده
            }
            $clean[$phone] = $t['user'] ?? null;
        }
        if (! $clean) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'skipped' => $badNumbers, 'message' => 'هیچ شماره‌ی معتبری در فهرست نبود.'];
        }

        $seg = SmsService::segments($body);

        // سهمیه: هرچه جا دارد فرستاده می‌شود، بقیه رد می‌شود (نه شکستِ کامل)
        foreach ([self::remainingForSchool($school), $sender ? self::remainingForUser($sender) : null] as $cap) {
            if ($cap !== null) {
                $max = intdiv($cap, $seg);
                if (count($clean) > $max) {
                    $overQuota += count($clean) - $max;
                    $clean = array_slice($clean, 0, max(0, $max), true);
                }
            }
        }
        if (! $clean) {
            return ['ok' => false, 'sent' => 0, 'failed' => 0, 'skipped' => $badNumbers + $overQuota, 'message' => 'سهمیه‌ی پیامک کافی نیست.'];
        }

        $line = $school?->sms_sender ?: null;
        $res = $sms->send(array_keys($clean), $body, $line);

        $rows = [];
        foreach ($clean as $phone => $user) {
            $rows[] = [
                'school_id'    => $school?->id,
                'sender_id'    => $sender?->id,
                'recipient_id' => $user?->id,
                'phone'        => $phone,
                'body'         => $body,
                'kind'         => $kind,
                'status'       => $res['ok'] ? 'sent' : 'failed',
                'provider_id'  => $res['ids'][$phone] ?? null,
                'error'        => $res['ok'] ? null : mb_substr($res['message'], 0, 250),
                'segments'     => $seg,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }
        SmsMessage::insert($rows);

        $n = count($rows);
        $fa = fn ($x) => Jalali::fa((string) $x);

        // دو دلیلِ «ارسال نشد» جداگانه گزارش می‌شوند، وگرنه شماره‌ی خرابِ
        // کاربر به گردنِ سهمیه می‌افتد و او دنبالِ مشکلِ اشتباه می‌گردد.
        $notes = [];
        if ($badNumbers) {
            $notes[] = $fa($badNumbers) . ' شماره‌ی نامعتبر';
        }
        if ($overQuota) {
            $notes[] = $fa($overQuota) . ' مورد خارج از سهمیه';
        }

        return [
            'ok'      => $res['ok'],
            'sent'    => $res['ok'] ? $n : 0,
            'failed'  => $res['ok'] ? 0 : $n,
            'skipped' => $badNumbers + $overQuota,
            'message' => $res['ok']
                ? 'پیامک برای ' . $fa($n) . ' شماره ارسال شد'
                    . ($notes ? ' · ' . implode(' و ', $notes) . ' ارسال نشد' : '') . ' ✅'
                : $res['message'],
        ];
    }

    /** تنظیماتِ رویدادهای یک مدرسه. */
    public static function events(?School $school): array
    {
        $raw = is_array($school?->sms_events) ? $school->sms_events : [];
        $out = [];
        foreach (array_keys(self::EVENTS) as $key) {
            // خوش‌آمد تا وقتی مدیر صریحاً خاموشش نکرده روشن است؛ بقیه پیش‌فرض خاموش‌اند
            $default = $key === 'welcome';
            $out[$key] = [
                'parent'  => (bool) ($raw[$key]['parent'] ?? $default),
                'student' => (bool) ($raw[$key]['student'] ?? $default),
            ];
        }

        return $out;
    }

    /**
     * پیامکِ خودکارِ یک رویداد برای یک دانش‌آموز.
     *
     * best-effort است: هر خطایی بلعیده می‌شود تا ثبتِ نمره یا انتشارِ
     * محتوا به‌خاطرِ پیامک شکست نخورد.
     */
    public static function event(string $key, User $student, string $body, ?User $sender = null): void
    {
        try {
            if (! isset(self::EVENTS[$key]) || ! self::gatewayReady()) {
                return;
            }
            $school = $student->school;
            if (! self::schoolEnabled($school)) {
                return;
            }
            $cfg = self::events($school)[$key] ?? null;
            if (! $cfg || (! $cfg['parent'] && ! $cfg['student'])) {
                return;
            }

            // تنظیمِ اختصاصیِ همین دانش‌آموز (کارِ معلمِ کلاس).
            // قاعده: ماتریسِ مدرسه «سقف» است — می‌گوید کدام رویداد و کدام
            // کانال اصلاً مجاز است؛ تنظیمِ دانش‌آموز درونِ همان سقف
            // تصمیم می‌گیرد. پس معلم می‌تواند کم کند یا ببندد، ولی
            // نمی‌تواند چیزی را که مدیر بسته باز کند.
            $pref = self::studentSetting($student);
            if ($pref && ! $pref->allowsEvent($key)) {
                return;
            }
            $toParent = $cfg['parent'] && (! $pref || $pref->to_parent);
            $toStudent = $cfg['student'] && (! $pref || $pref->to_student);
            if (! $toParent && ! $toStudent) {
                return;
            }

            $targets = [];
            if ($toStudent && $student->phone) {
                $targets[] = ['user' => $student, 'phone' => $student->phone];
            }
            if ($toParent) {
                foreach (self::parentPhones($student) as $p) {
                    $targets[] = ['user' => null, 'phone' => $p];
                }
            }
            if ($targets) {
                self::sendMany($sender, $school, $targets, $body, $key);
            }
        } catch (\Throwable $e) {
            Log::warning('SMS event failed: ' . $e->getMessage());
        }
    }

    /**
     * متنِ پیامکِ خوش‌آمد — یکی برای دانش‌آموز، یکی برای ولی.
     *
     * امضا سه‌لایه است: نامِ مدرسه، نامِ معلمِ کلاس و «ستاره ماه»؛ تا خانواده
     * بداند پیام از طرفِ مدرسه‌ی خودش است، نه یک سرویسِ ناشناس.
     *
     * @param array{teacher?:?User, classroom?:?\App\Models\Classroom, password?:?string, pin?:?string} $o
     */
    public static function welcomeTexts(User $student, array $o = []): array
    {
        $first = trim(explode(' ', trim($student->name))[0] ?? $student->name);
        $school = $student->school?->name ?: 'مدرسه';
        $class = $o['classroom']?->name ?? null;
        $teacher = $o['teacher']?->name ?? null;
        $fa = fn ($x) => Jalali::fa((string) $x);
        $user = $fa($student->phone ?: '');
        $pass = ! empty($o['password']) ? $fa($o['password']) : null;
        $pin = ! empty($o['pin']) ? $fa($o['pin']) : null;
        $where = trim(($class ? "کلاسِ {$class}" : '') . ($teacher ? " با {$teacher}" : ''));

        $studentText = "{$first} عزیز، به ستاره ماه خوش آمدی!\n"
            . ($where ? "از امروز عضوِ {$where} در {$school} هستی.\n" : "از امروز عضوِ {$school} در ستاره ماه هستی.\n")
            . "ورود: starmah.ir\nنام کاربری: {$user}"
            . ($pass ? "\nرمزِ موقت: {$pass} (بعد از ورود عوضش کن)" : '')
            . "\nبا آرزوی موفقیت\n{$school}";

        $parentText = "ولیِّ گرامیِ {$student->name}، سلام.\n"
            . "ثبت‌نامِ فرزندتان در سامانه‌ی آموزشیِ ستاره ماه"
            . ($where ? " برای {$where}" : '') . " در {$school} انجام شد.\n"
            . "ورودِ دانش‌آموز: starmah.ir\nنام کاربری: {$user}"
            . ($pass ? " | رمزِ موقت: {$pass}" : '')
            . ($pin ? "\nرمزِ «بخشِ والدین»: {$pin} — این رمز را فقط نزدِ خودتان نگه دارید." : '')
            . "\nپیامِ نمره، غیبت و تکلیف به همین شماره می‌آید.\n"
            . ($teacher ? "{$teacher} — " : '') . $school;

        return ['student' => $studentText, 'parent' => $parentText];
    }

    /**
     * پیامکِ خوش‌آمدِ ثبت‌نام به دانش‌آموز و ولی.
     *
     * همان قواعدِ بقیه‌ی رویدادها را دارد (درگاه، اجازه‌ی مدرسه، ماتریسِ
     * رویدادها، سهمیه) ولی متنِ دانش‌آموز و ولی جداست. اگر شماره‌ی دانش‌آموز
     * همان شماره‌ی ولی باشد، فقط نسخه‌ی ولی فرستاده می‌شود. هیچ خطایی به
     * ثبت‌نام سرایت نمی‌کند.
     *
     * @return array{sent:int, message:?string}
     */
    public static function welcome(User $student, array $o = []): array
    {
        try {
            if (! self::gatewayReady()) {
                return ['sent' => 0, 'message' => null];
            }
            $school = $student->school;
            if (! self::schoolEnabled($school)) {
                return ['sent' => 0, 'message' => null];
            }
            $cfg = self::events($school)['welcome'];
            $texts = self::welcomeTexts($student, $o);
            $sms = app(SmsService::class);

            $parents = $cfg['parent'] ? array_map(fn ($p) => $sms->normalize($p), self::parentPhones($student)) : [];
            $parents = array_values(array_filter(array_unique($parents)));
            $mine = $cfg['student'] ? $sms->normalize((string) $student->phone) : '';

            $sent = 0;
            if ($mine !== '' && ! in_array($mine, $parents, true)) {
                $sent += self::sendMany(null, $school, [['user' => $student, 'phone' => $mine]], $texts['student'], 'welcome')['sent'];
            }
            if ($parents) {
                $sent += self::sendMany(null, $school, array_map(fn ($p) => ['user' => null, 'phone' => $p], $parents), $texts['parent'], 'welcome')['sent'];
            }

            return ['sent' => $sent, 'message' => $sent ? 'پیامکِ خوش‌آمد برای ' . Jalali::fa((string) $sent) . ' شماره فرستاده شد 📩' : null];
        } catch (\Throwable $e) {
            Log::warning('SMS welcome failed: ' . $e->getMessage());
            return ['sent' => 0, 'message' => null];
        }
    }

    /**
     * تنظیمِ پیامکِ اختصاصیِ یک دانش‌آموز — یا null اگر پیش‌فرضِ مدرسه باشد.
     *
     * در هر درخواست یک‌بار خوانده می‌شود؛ ثبتِ گروهیِ نمره برای یک کلاس
     * وگرنه به‌ازای هر دانش‌آموز یک پرس‌وجوی تکراری می‌زد.
     */
    private static array $prefCache = [];

    public static function studentSetting(User $student): ?StudentSmsSetting
    {
        if (array_key_exists($student->id, self::$prefCache)) {
            return self::$prefCache[$student->id];
        }
        try {
            $row = StudentSmsSetting::where('student_id', $student->id)->first();
        } catch (\Throwable) {
            $row = null;     // جدول هنوز ساخته نشده — پیش‌فرضِ مدرسه
        }

        return self::$prefCache[$student->id] = $row;
    }

    /** شماره‌ی ولی‌های یک دانش‌آموز (حسابِ والد + فیلدِ شماره‌ی ولی). */
    public static function parentPhones(User $student): array
    {
        // شماره‌ای که معلم برای همین دانش‌آموز ثبت کرده جایگزینِ بقیه
        // می‌شود، نه اضافه بر آن‌ها: وقتی خانواده می‌گوید «به این شماره
        // بفرست»، فرستادن به شماره‌های قدیمی هم نقضِ همان خواسته است.
        $override = self::studentSetting($student)?->phone_override;
        if ($override) {
            return [$override];
        }

        $phones = [];
        if ($student->parent_phone) {
            $phones[] = $student->parent_phone;
        }
        // فرمِ ثبت‌نام شماره‌ی ولی را در settings.guardian نگه می‌دارد
        $g = is_array($student->settings) ? ($student->settings['guardian']['phone'] ?? null) : null;
        if ($g) {
            $phones[] = $g;
        }
        try {
            foreach ($student->parents()->pluck('phone') as $p) {
                if ($p) {
                    $phones[] = $p;
                }
            }
        } catch (\Throwable) {
            // رابطه‌ی والد در این نسخه نیست — فقط از فیلدِ شماره استفاده می‌کنیم
        }

        return array_values(array_unique(array_filter($phones)));
    }
}
