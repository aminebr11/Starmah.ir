<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\Roles;
use App\Support\StudentInsight;
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
        private StudentInsight $insight,
    ) {}

    /** پرونده‌ی تحلیلیِ دانش‌آموز — یک‌بار در هر درخواست ساخته می‌شود. */
    private ?array $cachedProfile = null;
    private ?array $cachedAnalysis = null;

    private function student(User $user): array
    {
        if ($this->cachedProfile === null) {
            $this->cachedProfile = $this->insight->profile($user);
            $this->cachedAnalysis = $this->insight->analysis($this->cachedProfile);
        }

        return [$this->cachedProfile, $this->cachedAnalysis];
    }

    /** @param array<int,array{role:string,content:string}> $history */
    public function reply(User $user, array $history, string $message): array
    {
        $context = $this->userContext($user);
        $guide = $this->siteGuide($user);

        // با کلیدِ هوش مصنوعی → پاسخِ واقعی
        if ($this->ai->isConfigured()) {
            try {
                $system = "تو «دستیارِ ستاره‌ماه» هستی — مثلِ یک معلمِ راهنمای مهربان که کنارِ کاربر نشسته. فارسیِ ساده و صمیمی حرف بزن.\n"
                    . "قواعد:\n"
                    . "۱) همیشه از «اطلاعاتِ کاربر» که پایین آمده استفاده کن؛ عدد و درصد را دقیقاً از همان بردار، چیزی از خودت نساز.\n"
                    . "۲) اگر داده‌ای نداریم، صادقانه بگو نداریم و بگو از کجا ساخته می‌شود.\n"
                    . "۳) وقتی از وضعیت/رتبه/ضعف می‌پرسد: اول یک جمله ارزیابیِ کلی، بعد عددها، بعد ۲ تا ۳ پیشنهادِ **عملی** که با بخش‌های همین سایت انجام‌شدنی باشد.\n"
                    . "۴) کوتاه بنویس؛ از فهرستِ گلوله‌ای استفاده کن. بیش از ۱۲۰ کلمه نشو مگر کاربر گزارشِ کامل بخواهد.\n"
                    . "۵) هرگز اطلاعاتِ دانش‌آموزانِ دیگر را نگو؛ فقط رتبه‌ی خودِ کاربر مجاز است.\n"
                    . "۶) لحن تشویقی باشد، نه سرزنشگر.\n\n"
                    . "=== راهنمای سایت ===\n{$guide}\n\n=== اطلاعاتِ کاربر ===\n{$context}";
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
            // پرونده‌ی کاملِ تحلیلی: امتیاز، سطح، رتبه، درس‌به‌درس، نمره،
            // آزمون، مأموریت، حضوروغیاب، انضباط + ارزیابی و پیشنهاد.
            [$p, $a] = $this->student($user);
            $lines[] = $this->insight->asText($p, $a);
            $lines[] = 'پیشنهادِ گامِ بعدی: ' . implode(' | ', $a['actions']);
            foreach ($a['weak'] as $w) {
                $lines[] = "راهکارِ درسِ ضعیف ({$w['subject']}): {$w['tip']}";
            }
        } elseif ($user->hasRole(Roles::TEACHER)) {
            $classes = \App\Models\Classroom::where('teacher_id', $user->id)->count();
            $studentIds = User::whereHas('classrooms', fn ($q) => $q->where('teacher_id', $user->id))->pluck('id');
            $lines[] = "تعداد کلاس: {$classes} · تعداد دانش‌آموز: {$studentIds->count()}";

            if ($studentIds->isNotEmpty()) {
                $data = $this->cross->forStudents($studentIds);
                $rows = collect($data['subjects'] ?? [])->filter(fn ($r) => $r['pct'] !== null);
                if ($rows->isNotEmpty()) {
                    $lines[] = 'میانگینِ کلاس‌ها: ' . ($data['overall'] ?? 0) . '٪';
                    $lines[] = 'ضعیف‌ترین درس‌ها: ' . $rows->sortBy('pct')->take(3)
                        ->map(fn ($r) => $r['subject'] . ' ' . $r['pct'] . '٪')->implode('، ');
                }
            }

            // کارهای روی میز — همان چیزهایی که در زنگوله هم می‌آیند
            $pending = \App\Models\ParentNote::whereIn('student_id', $studentIds)
                ->where('from_parent', true)->whereNull('read_at')->count();
            $subs = \App\Models\WorksheetSubmission::whereHas('worksheet', fn ($q) => $q->where('teacher_id', $user->id))
                ->whereNotNull('file_path')->where('updated_at', '>=', now()->subDays(14))->count();
            $lines[] = "پیامِ خوانده‌نشده‌ی والدین: {$pending} · کاربرگِ ارسالیِ دو هفته‌ی اخیر: {$subs}";
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
            [$p, $a] = $this->student($user);

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
