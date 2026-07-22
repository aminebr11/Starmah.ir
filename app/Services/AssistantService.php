<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\LevelConfig;
use App\Support\Roles;
use Illuminate\Support\Facades\Http;

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
    ) {}

    /** @param array<int,array{role:string,content:string}> $history */
    public function reply(User $user, array $history, string $message): array
    {
        $context = $this->userContext($user);
        $guide = $this->siteGuide($user);

        // با کلیدِ هوش مصنوعی → پاسخِ واقعی
        if ($this->ai->isConfigured()) {
            try {
                $system = "تو «دستیارِ ستاره‌ماه» هستی؛ یک راهنمای مهربان و کوتاه‌گو به زبانِ فارسی برای این وب‌سایتِ آموزشیِ گیمیفای‌شده. "
                    . "به سؤالِ کاربر بر اساسِ «راهنمای سایت» و «اطلاعاتِ کاربر» پاسخِ دقیق، صمیمی و کوتاه بده. اگر داده‌ای نبود صادق باش. "
                    . "هرگز اطلاعاتِ کاربرانِ دیگر را افشا نکن.\n\n=== راهنمای سایت ===\n{$guide}\n\n=== اطلاعاتِ کاربر ===\n{$context}";
                $reply = $this->llm($system, $history, $message);
                if ($reply !== '') {
                    return ['reply' => $reply, 'mode' => 'ai'];
                }
            } catch (\Throwable $e) {
                // به fallback محلی می‌رویم
            }
        }

        return ['reply' => $this->localReply($user, $message, $context), 'mode' => 'local'];
    }

    /** خلاصه‌ی واقعیِ داده‌ی کاربر (متناسب با نقش). */
    private function userContext(User $user): string
    {
        $lines = ["نام: {$user->name}", 'نقش: ' . $this->roleLabel($user)];

        if ($user->hasRole(Roles::STUDENT)) {
            $xp = $user->totalXp();
            $level = LevelConfig::levelOf($xp, LevelConfig::xpPerLevel($user->school_id));
            $classroom = $user->classrooms()->with('teacher')->first();
            $lines[] = "امتیاز کل: {$xp} · سطح: {$level}";
            if ($classroom) {
                $lines[] = "کلاس: {$classroom->name}" . ($classroom->teacher ? " · معلم: {$classroom->teacher->name}" : '');
            }
            if ($user->theme) {
                $lines[] = "تیم: {$user->theme->name}";
            }
            $lines[] = 'نشان‌ها: ' . $user->badges()->count();

            $data = $this->cross->forStudent($user);
            $subjects = collect($data['subjects'] ?? []);
            if ($subjects->isNotEmpty()) {
                $lines[] = 'میانگین کلِ درس‌ها: ' . ($data['overall'] ?? 0) . '٪';
                $weak = $subjects->filter(fn ($s) => $s['pct'] !== null && $s['pct'] < 60)->pluck('subject')->take(3)->implode('، ');
                $strong = $subjects->filter(fn ($s) => ($s['pct'] ?? 0) >= 70)->pluck('subject')->take(3)->implode('، ');
                if ($strong) $lines[] = "نقاط قوت: {$strong}";
                if ($weak) $lines[] = "نیاز به تمرین: {$weak}";
            } else {
                $lines[] = 'هنوز فعالیتِ نمره‌داری ثبت نشده.';
            }
        } elseif ($user->hasRole(Roles::TEACHER)) {
            $classes = \App\Models\Classroom::where('teacher_id', $user->id)->count();
            $students = User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->count();
            $lines[] = "تعداد کلاس: {$classes} · تعداد دانش‌آموز: {$students}";
        } elseif ($user->hasRole(Roles::PARENT)) {
            $children = $user->children()->pluck('name')->implode('، ');
            if ($children) $lines[] = "فرزند(ان): {$children}";
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

    /** پاسخِ قاعده‌مندِ محلی (بدونِ کلید) — راهنما + خلاصه‌ی داده. */
    private function localReply(User $user, string $message, string $context): string
    {
        $m = mb_strtolower($message);
        $has = fn (array $kw) => collect($kw)->contains(fn ($k) => str_contains($m, $k));

        if ($has(['ضعف', 'قوت', 'عملکرد', 'چطورم', 'پیشرفت', 'نمره', 'امتیاز من', 'کارنامه', 'وضعیت'])) {
            return "این خلاصه‌ی وضعیتِ توست:\n\n{$context}\n\nبرای جزئیاتِ بیشتر به بخشِ «کارنامه» برو.";
        }
        if ($has(['آزمون', 'امتحان'])) {
            return "برای آزمون: از منو وارد «آزمون هوشمند» شو، آزمونِ باز را انتخاب و شروع کن. پس از پایان، پاسخنامه و «کارنامه‌ی هوشمند» را می‌بینی.";
        }
        if ($has(['مأموریت', 'ماموریت', 'تمرین'])) {
            return "هر روز به «مأموریت‌های من» برو؛ مأموریت‌هایی که معلمت گذاشته را انجام بده. مأموریتِ سؤالی را همان‌جا حل می‌کنی؛ مأموریتِ پادکست/کاربرگ/بازی را در بخشِ مربوطه انجام بده و بعد «دریافتِ جایزه» را بزن.";
        }
        if ($has(['بازی'])) {
            return "به «دنیای بازی‌ها» برو و یک بازیِ باز را انتخاب کن. امتیاز فقط بارِ اول محاسبه می‌شود؛ دفعاتِ بعد فقط تمرین است.";
        }
        if ($has(['کاربرگ'])) {
            return "کاربرگ را از بخشِ محتوا/تکالیف دانلود یا چاپ کن، پرش کن و عکس/فایلش را برای معلم بفرست. برای دانلود و ارسال، هرکدام یک‌بار امتیاز می‌گیری.";
        }
        if ($has(['پادکست', 'محتوا', 'جزوه'])) {
            return "به «محتوای کلاس» برو. گوش‌دادنِ واقعیِ پادکست (بدونِ پرش) امتیاز دارد. جزوه و گالری هم همان‌جاست.";
        }
        if ($has(['پیام', 'ارتباط', 'معلم', 'مدیر'])) {
            return "از «ارتباط با معلم» می‌توانی به معلم و مدیرِ مدرسه پیام بدهی و سوابقِ گفت‌وگو را ببینی.";
        }
        if ($has(['سطح', 'لِوِل', 'ستاره', 'نشان'])) {
            return "با کسبِ امتیاز (XP) سطحت بالا می‌رود (هر ۱۵۰ امتیاز یک سطح، قابلِ تنظیم توسط معلم). نشان‌ها را هم از انجامِ مأموریت‌ها و موفقیت‌ها می‌گیری.";
        }
        // پاسخِ عمومی
        return "سلام {$user->name} 👋 من دستیارِ ستاره‌ماه‌ام. می‌توانم درباره‌ی مأموریت‌ها، بازی‌ها، آزمون هوشمند، کاربرگ‌ها، امتیاز/سطح، کارنامه و ارتباط با معلم راهنمایی‌ات کنم — یا وضعیتِ خودت را برایت خلاصه کنم. چه می‌خواهی بدانی؟";
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
