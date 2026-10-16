<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\EduGame;
use App\Models\User;
use App\Support\Jalali;
use App\Support\MahooAbilities;
use App\Support\Roles;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * «ماهو، این کار را بکن» — فهمیدنِ درخواست، ساختنِ پیشنهادِ کار و انجامِ آن پس از تأیید.
 *
 * دو راه برای فهمیدن: الگوهای فارسیِ روشن (بی‌هزینه و فوری)، و اگر هوش مصنوعی روشن است،
 * خطِ «<<ACTION {...}>>» در پاسخِ آن. هر دو به یک «پیشنهاد» می‌رسند که دوباره اعتبارسنجی
 * می‌شود. کارهای سمتِ سرور (امتیاز، پیام، اطلاعیه، آزمون) فقط با «✅ انجام بده»ِ خودِ کاربر
 * و یک توکنِ رمزشده‌ی کوتاه‌عمر اجرا می‌شوند؛ هر بار دوباره دسترسی بررسی می‌شود.
 */
class MahooActions
{
    private const TTL = 900;   // توکنِ تأیید ۱۵ دقیقه معتبر است

    public function __construct(private GamificationService $game, private RemedialGameService $remedial) {}

    /** عدد و حروفِ عربی → فارسیِ یکدست؛ برای مقایسه‌ی نام‌ها. */
    public static function norm(string $s): string
    {
        $s = strtr($s, ['ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', "\u{200C}" => ' ', 'ۀ' => 'ه', 'أ' => 'ا', 'إ' => 'ا']);
        $digits = array_map('strval', range(0, 9));
        $s = strtr($s, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), $digits));
        $s = strtr($s, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), $digits));
        $s = preg_replace('/[\x{064B}-\x{0652}]/u', '', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    private static function fa($n): string
    {
        return Jalali::fa((string) $n);
    }

    /**
     * درخواستِ روشنِ کاربر (بدونِ هوش مصنوعی). null یعنی «کار نیست، گفت‌وگوی معمولی است».
     *
     * @return array{reply:string,action?:array}|null
     */
    public function understand(User $user, string $message): ?array
    {
        $m = self::norm($message);

        // ⏱️ تایمر: «تایمر ۲۰ دقیقه» / «۲۵ دقیقه درس بخونم» / «پومودورو»
        if (preg_match('/(تایمر|زمان ?سنج|پومودورو|کرنومتر)/u', $m) || preg_match('/(\d{1,2}) ?دقیقه.{0,20}(درس|مطالعه|تمرین|بخونم|بخوانم|استراحت)/u', $m)) {
            if (! MahooAbilities::allowed($user, 'timer')) {
                return null;
            }
            preg_match('/(\d{1,2}) ?دقیقه/u', $m, $mm);
            $min = max(1, min(90, (int) ($mm[1] ?? (str_contains($m, 'پومودورو') ? 25 : 15))));
            $rest = str_contains($m, 'استراحت');

            return ['reply' => $rest
                ? "⏱️ تایمرِ استراحتِ **".self::fa($min)." دقیقه‌ای** را روشن کردم. بدن را کش بده و آب بخور 💧 سرِ وقت صدایت می‌کنم."
                : "⏱️ تایمرِ **".self::fa($min)." دقیقه‌ای** شروع شد! با تمرکز بخوان؛ من این‌جا مراقبم و سرِ وقت خبرت می‌کنم 💪",
                'action' => ['type' => 'timer', 'minutes' => $min, 'label' => $rest ? 'استراحت' : 'درس']];
        }

        // 🧩 چیستان و جمله‌ی انرژی
        if (preg_match('/(چیستان|معما|جوک|لطیفه)/u', $m) && MahooAbilities::allowed($user, 'fun')) {
            [$q, $a] = self::RIDDLES[array_rand(self::RIDDLES)];

            return ['reply' => "🧩 **چیستان:** {$q}\n\nفکر کن، بعد دکمه‌ی «جواب» را بزن!", 'action' => ['type' => 'riddle', 'answer' => $a]];
        }
        if (preg_match('/(انرژی|انگیزه|حوصله ندارم|خسته ام|خسته‌ام|ناامید)/u', $m) && MahooAbilities::allowed($user, 'fun')) {
            return ['reply' => self::BOOSTS[array_rand(self::BOOSTS)]];
        }

        // 🧭 باز کردنِ صفحه: «برو به کارنامه» / «کارنامه را باز کن»
        if (MahooAbilities::allowed($user, 'navigate')
            && (preg_match('/^(برو|ببر|بریم|منو ببر|مرا ببر|نشون بده|نشان بده|باز کن)\s*(به|سراغ)?\s*(صفحه ی|صفحه|بخش)?\s*(.{2,40}?)[.!؟?]*$/u', $m, $nm)
                || preg_match('/^(صفحه ی|صفحه|بخش)?\s*(.{2,40}?)\s*(را|رو)\s*(باز کن|نشون بده|نشان بده|بیار)[.!؟?]*$/u', $m, $nm2))) {
            $target = trim($nm[4] ?? $nm2[2] ?? '');
            if ($target !== '' && count(preg_split('/\s+/u', $target)) <= 4) {
                return ['reply' => "🧭 الان «{$target}» را برایت باز می‌کنم…", 'action' => ['type' => 'navigate', 'target' => $target]];
            }
        }

        $role = MahooAbilities::roleOf($user);

        // ⭐ امتیاز: «به سارا ۵ امتیاز بده» / «۳ امتیاز از علی کم کن»
        if ($role === 'teacher' && preg_match('/(\d{1,3}) ?(امتیاز|ستاره)/u', $m) && preg_match('/(بده|اضافه کن|ثبت کن|کم کن|کسر کن|بزن)/u', $m)) {
            if (! MahooAbilities::allowed($user, 'award')) {
                return ['reply' => 'امتیازدادن از طریقِ ماهو در مدرسه‌ی شما بسته است؛ از «مرکزِ امتیاز» استفاده کنید.'];
            }
            preg_match('/(\d{1,3}) ?(امتیاز|ستاره)/u', $m, $am);
            $minus = (bool) preg_match('/(کم کن|کسر کن|منفی)/u', $m);
            $name = '';
            if (preg_match('/(?<!\p{L})(?:به|از|برای)\s+(.{2,40}?)\s+(?:\d{1,3}\s?(?:امتیاز|ستاره)|امتیاز|ستاره|بده|کم کن|کسر کن)/u', $m, $nn)) {
                $name = $nn[1];
            } elseif (preg_match('/(?:امتیاز|ستاره)\s+(?:به|از|برای)\s+(.{2,40}?)\s+(?:بده|کم کن|کسر کن|بزن|ثبت کن)/u', $m, $nn)) {
                $name = $nn[1];
            }
            $reason = preg_match('/(?:بابت|برای اینکه|به خاطر|بخاطر)\s+(.{2,60})$/u', $m, $rm) ? trim($rm[1]) : null;

            return $this->proposeAward($user, $name, ((int) $am[1]) * ($minus ? -1 : 1), $reason);
        }

        // 📣 پیام به کلاس (معلم): «به کلاس بگو: فردا کتاب علوم بیارید»
        if ($role === 'teacher' && preg_match('/^(به )?(بچه ها|کلاس|دانش ?آموزا?ن?ها?)(ی?\s?.{0,20}?)\s*(بگو|پیام بده|پیام بفرست|اطلاع بده|اعلان بده|بنویس)\s*(که|:|：)?\s*(.+)$/u', $m, $cm)) {
            if (! MahooAbilities::allowed($user, 'class_notice')) {
                return ['reply' => 'فرستادنِ پیام از طریقِ ماهو در مدرسه‌ی شما بسته است.'];
            }

            return $this->proposeClassNotice($user, trim($cm[6]), trim($cm[3]));
        }

        // 📢 اطلاعیه‌ی مدرسه (مدیر): «اطلاعیه بده برای معلم‌ها: جلسه ساعت ۱۰»
        if ($role === 'school_admin' && preg_match('/اطلاعیه\s*(بده|بذار|بگذار|بفرست|منتشر کن|بزن)\s*(برای\s*(همه|معلم ?ها|دانش ?آموزان|دانش ?آموزها|بچه ها))?\s*(که|:|：)?\s*(.+)$/u', $m, $sm)) {
            if (! MahooAbilities::allowed($user, 'school_notice')) {
                return ['reply' => 'انتشارِ اطلاعیه از طریقِ ماهو بسته است.'];
            }
            $aud = match (true) {
                str_contains($sm[3] ?? '', 'معلم') => 'teachers',
                str_contains($sm[3] ?? '', 'آموز') || str_contains($sm[3] ?? '', 'بچه') => 'students',
                default => 'all',
            };

            return $this->proposeSchoolNotice($user, trim($sm[5]), $aud);
        }

        // 🔁 آزمونِ جبرانی (معلم)
        if ($role === 'teacher' && preg_match('/(آزمون|بازی)\s*(ی)?\s*جبرانی/u', $m) && preg_match('/(بساز|درست کن|ایجاد کن|آماده کن)/u', $m)) {
            if (! MahooAbilities::allowed($user, 'remedial')) {
                return ['reply' => 'ساختِ آزمونِ جبرانی از طریقِ ماهو بسته است؛ از «گزارشِ بازی» استفاده کنید.'];
            }

            return $this->proposeRemedial($user);
        }

        return null;
    }

    /**
     * پیشنهادی که هوش مصنوعی در پاسخش گذاشته: «<<ACTION {"type":"award","student":"سارا","amount":5}>>».
     *
     * @return array{0:string,1:?array} [متنِ بدونِ خطِ کار، پیشنهاد یا پیام]
     */
    public function fromAi(User $user, string $reply): array
    {
        if (! preg_match('/<<ACTION\s+(\{.*?\})\s*>>/su', $reply, $m)) {
            return [$reply, null];
        }
        $text = trim(str_replace($m[0], '', $reply));
        $p = json_decode($m[1], true);
        if (! is_array($p)) {
            return [$text, null];
        }
        $res = match ($p['type'] ?? '') {
            'navigate' => MahooAbilities::allowed($user, 'navigate') && ! empty($p['target']) ? ['action' => ['type' => 'navigate', 'target' => (string) $p['target']]] : null,
            'timer' => MahooAbilities::allowed($user, 'timer') ? ['action' => ['type' => 'timer', 'minutes' => max(1, min(90, (int) ($p['minutes'] ?? 15))), 'label' => 'درس']] : null,
            'award' => MahooAbilities::allowed($user, 'award') ? $this->proposeAward($user, (string) ($p['student'] ?? ''), (int) ($p['amount'] ?? 0), $p['reason'] ?? null) : null,
            'class_notice' => MahooAbilities::allowed($user, 'class_notice') ? $this->proposeClassNotice($user, (string) ($p['text'] ?? ''), (string) ($p['classroom'] ?? '')) : null,
            'school_notice' => MahooAbilities::allowed($user, 'school_notice') ? $this->proposeSchoolNotice($user, (string) ($p['text'] ?? ''), (string) ($p['audience'] ?? 'all')) : null,
            'remedial' => MahooAbilities::allowed($user, 'remedial') ? $this->proposeRemedial($user) : null,
            default => null,
        };
        if (! $res) {
            return [$text, null];
        }
        // اگر پیشنهاد به‌جای کار، پیامِ «نشد» داد، همان را به متن اضافه کن
        if (empty($res['action'])) {
            return [trim($text . "\n\n" . ($res['reply'] ?? '')), null];
        }

        return [$text !== '' ? $text : ($res['reply'] ?? ''), $res['action']];
    }

    // ── ساختِ پیشنهادها ────────────────────────────────────────────────

    private function token(User $user, string $type, array $params): string
    {
        return Crypt::encryptString(json_encode(['u' => $user->id, 't' => $type, 'p' => $params, 'x' => time() + self::TTL]));
    }

    private function confirmCard(User $user, string $type, string $title, array $lines, array $params, string $button = '✅ انجام بده'): array
    {
        return ['type' => 'confirm', 'kind' => $type, 'title' => $title, 'lines' => $lines, 'button' => $button, 'token' => $this->token($user, $type, $params)];
    }

    /** @return array<int,int> شناسه‌ی دانش‌آموزانِ کلاس‌های این معلم */
    private function myStudentIds(User $teacher): array
    {
        return Classroom::where('teacher_id', $teacher->id)->with('students:id')->get()
            ->flatMap(fn ($c) => $c->students->pluck('id'))->unique()->values()->all();
    }

    private function proposeAward(User $user, string $name, int $amount, ?string $reason): array
    {
        $name = trim(preg_replace('/^(دانش ?آموز|خانم|آقای?)\s+/u', '', self::norm($name)) ?? '');
        if ($amount === 0 || abs($amount) > 500) {
            return ['reply' => 'چند امتیاز؟ عددی بین ۱ تا ۵۰۰ بگویید، مثلاً «به سارا ۵ امتیاز بده».'];
        }
        if ($name === '') {
            return ['reply' => 'به کدام دانش‌آموز؟ مثلاً بگویید «به سارا محمدی ۵ امتیاز بده».'];
        }
        $students = User::whereIn('id', $this->myStudentIds($user))->get(['id', 'name']);
        $hits = $students->filter(fn ($s) => str_contains(self::norm($s->name), $name))->values();
        if ($hits->count() > 1) {
            $exact = $hits->filter(fn ($s) => self::norm($s->name) === $name)->values();
            $hits = $exact->count() === 1 ? $exact : $hits;
        }
        if ($hits->isEmpty()) {
            return ['reply' => "دانش‌آموزی به نامِ «{$name}» در کلاس‌های شما پیدا نکردم. نامِ کامل‌تر را بگویید."];
        }
        if ($hits->count() > 1) {
            return ['reply' => "چند نفر با این نام هست: " . $hits->take(6)->pluck('name')->map(fn ($n) => "«{$n}»")->implode('، ') . ". نامِ کامل را بگویید."];
        }
        $s = $hits->first();
        $reason = trim((string) $reason) ?: ($amount > 0 ? '⭐ تشویقِ معلم' : 'کسرِ امتیاز توسطِ معلم');

        return [
            'reply' => $amount > 0 ? "حاضر است! این امتیاز را برای **{$s->name}** ثبت کنم؟" : "این کسرِ امتیاز را برای **{$s->name}** ثبت کنم؟",
            'action' => $this->confirmCard($user, 'award', $amount > 0 ? '⭐ دادنِ امتیاز' : '➖ کسرِ امتیاز', [
                "دانش‌آموز: {$s->name}", 'امتیاز: ' . ($amount > 0 ? '+' : '−') . self::fa(abs($amount)), "بابتِ: {$reason}",
            ], ['student' => $s->id, 'amount' => $amount, 'reason' => mb_substr($reason, 0, 120)]),
        ];
    }

    private function proposeClassNotice(User $user, string $text, string $classHint): array
    {
        $text = trim($text, " :：\t\n");
        if (mb_strlen($text) < 3) {
            return ['reply' => 'متنِ پیام را بگویید، مثلاً «به کلاس بگو: فردا کتابِ علوم را بیاورید».'];
        }
        $rooms = Classroom::where('teacher_id', $user->id)->get(['id', 'name']);
        if ($rooms->isEmpty()) {
            return ['reply' => 'شما هنوز کلاسی ندارید که برایش پیام بفرستم.'];
        }
        $hint = self::norm($classHint);
        $pick = $hint !== '' ? $rooms->filter(fn ($r) => str_contains(self::norm($r->name), $hint) || str_contains($hint, self::norm($r->name)))->values() : collect();
        $pick = $pick->isNotEmpty() ? $pick : $rooms;
        $n = \Illuminate\Support\Facades\DB::table('classroom_student')->whereIn('classroom_id', $pick->pluck('id'))->distinct()->count('student_id');

        return [
            'reply' => 'این پیام را برای بچه‌ها بفرستم؟',
            'action' => $this->confirmCard($user, 'class_notice', '📣 پیام به کلاس', [
                'کلاس: ' . $pick->pluck('name')->implode('، ') . ' (' . self::fa($n) . ' دانش‌آموز)', "متن: {$text}",
            ], ['classes' => $pick->pluck('id')->all(), 'text' => mb_substr($text, 0, 1500)], '📨 بفرست'),
        ];
    }

    private function proposeSchoolNotice(User $user, string $text, string $audience): array
    {
        $text = trim($text, " :：\t\n");
        if (mb_strlen($text) < 3) {
            return ['reply' => 'متنِ اطلاعیه را بگویید، مثلاً «اطلاعیه بده برای معلم‌ها: جلسه ساعت ۱۰».'];
        }
        $audience = in_array($audience, ['all', 'teachers', 'students'], true) ? $audience : 'all';
        $fa = ['all' => 'همه', 'teachers' => 'معلم‌ها', 'students' => 'دانش‌آموزان'][$audience];

        return [
            'reply' => 'این اطلاعیه را منتشر کنم؟',
            'action' => $this->confirmCard($user, 'school_notice', '📢 اطلاعیه‌ی مدرسه', ["برای: {$fa}", "متن: {$text}"],
                ['audience' => $audience, 'text' => mb_substr($text, 0, 3000)], '📢 منتشر کن'),
        ];
    }

    private function proposeRemedial(User $user): array
    {
        $games = EduGame::where('teacher_id', $user->id)->whereHas('attempts')->latest('id')->limit(10)->get();
        foreach ($games as $g) {
            $ids = $this->remedial->hardQuestionIds($g);
            if ($ids) {
                $n = count(array_unique(array_merge(...array_values($this->remedial->wrongMap($g)))));

                return [
                    'reply' => "در بازیِ «{$g->title}» " . self::fa(count($ids)) . ' سؤال را دستِ‌کم یک نفر اشتباه زده. از همین‌ها آزمونِ جبرانی بسازم؟',
                    'action' => $this->confirmCard($user, 'remedial', '🔁 آزمونِ جبرانی', [
                        "از بازی: {$g->title}", 'سؤال‌ها: ' . self::fa(count($ids)) . ' سؤالِ دشوار', 'برای: ' . self::fa($n) . ' دانش‌آموزی که اشتباه زده‌اند',
                        'به‌صورتِ پیش‌نویس ساخته می‌شود تا بازبینی و منتشر کنید.',
                    ], ['game' => $g->id, 'questions' => $ids], '🔁 بساز'),
                ];
            }
        }

        return ['reply' => 'در بازی‌های اخیرِ شما سؤالی نیست که کسی اشتباه زده باشد 🎉 آزمونِ جبرانی لازم نیست.'];
    }

    // ── اجرا ────────────────────────────────────────────────────────────

    /** @return array{ok:bool,reply:string,href?:string} */
    public function execute(User $user, string $token): array
    {
        try {
            $d = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return ['ok' => false, 'reply' => 'این کار دیگر معتبر نیست؛ دوباره از من بخواهید.'];
        }
        if (! is_array($d) || (int) ($d['u'] ?? 0) !== (int) $user->id || ($d['x'] ?? 0) < time()) {
            return ['ok' => false, 'reply' => 'زمانِ تأییدِ این کار گذشته است؛ دوباره از من بخواهید.'];
        }
        $type = (string) ($d['t'] ?? '');
        if (! MahooAbilities::allowed($user, $type)) {
            return ['ok' => false, 'reply' => 'اجازه‌ی این کار برای ماهو بسته شده است.'];
        }
        $p = (array) ($d['p'] ?? []);

        return match ($type) {
            'award' => $this->doAward($user, $p),
            'class_notice' => $this->doClassNotice($user, $p),
            'school_notice' => $this->doSchoolNotice($user, $p),
            'remedial' => $this->doRemedial($user, $p),
            default => ['ok' => false, 'reply' => 'این کار را نمی‌شناسم.'],
        };
    }

    private function doAward(User $user, array $p): array
    {
        $sid = (int) ($p['student'] ?? 0);
        if (! in_array($sid, $this->myStudentIds($user), true)) {
            return ['ok' => false, 'reply' => 'این دانش‌آموز در کلاس‌های شما نیست.'];
        }
        $student = User::findOrFail($sid);
        $want = (int) ($p['amount'] ?? 0);
        $amount = \App\Support\XpRules::apply($student, 'teacher_manual', $want);
        if ($amount === 0) {
            return ['ok' => false, 'reply' => '⛔ امتیازدهیِ دستی در مدرسه‌ی شما توسطِ مدیر غیرفعال شده است.'];
        }
        $this->game->award($student, $amount, (string) ($p['reason'] ?? '⭐ تشویقِ معلم'), $user, null, null, true);
        $cap = $amount !== $want ? ' (سقفِ مدرسه ' . self::fa(abs($amount)) . ' امتیاز است)' : '';

        return ['ok' => true, 'reply' => ($amount > 0 ? "🎉 ثبت شد! " . self::fa($amount) . " امتیاز به {$student->name} رسید." : "✅ " . self::fa(abs($amount)) . " امتیاز از {$student->name} کم شد.") . $cap,
            'href' => '/teacher/points'];
    }

    private function doClassNotice(User $user, array $p): array
    {
        $classes = Classroom::where('teacher_id', $user->id)->whereIn('id', array_map('intval', (array) ($p['classes'] ?? [])))->pluck('id');
        $ids = \Illuminate\Support\Facades\DB::table('classroom_student')->whereIn('classroom_id', $classes)->distinct()->pluck('student_id')->all();
        $text = trim((string) ($p['text'] ?? ''));
        if (! $ids || $text === '') {
            return ['ok' => false, 'reply' => 'کلاس یا متنِ پیام پیدا نشد.'];
        }
        $ann = Announcement::create([
            'school_id' => $user->school_id, 'sender_id' => $user->id, 'audience' => 'personal',
            'title' => '📣 ' . Str::limit($text, 60), 'body' => $text,
        ]);
        $ann->recipients()->sync($ids);

        return ['ok' => true, 'reply' => '📨 فرستاده شد! پیام برای ' . self::fa(count($ids)) . ' دانش‌آموز رفت.'];
    }

    private function doSchoolNotice(User $user, array $p): array
    {
        $text = trim((string) ($p['text'] ?? ''));
        if ($text === '' || ! $user->school_id) {
            return ['ok' => false, 'reply' => 'متنِ اطلاعیه خالی است.'];
        }
        Announcement::create([
            'school_id' => $user->school_id, 'sender_id' => $user->id, 'audience' => in_array($p['audience'] ?? '', ['all', 'teachers', 'students'], true) ? $p['audience'] : 'all',
            'title' => '📢 ' . Str::limit($text, 60), 'body' => $text,
        ]);

        return ['ok' => true, 'reply' => '📢 اطلاعیه منتشر شد.', 'href' => '/school/announcements'];
    }

    private function doRemedial(User $user, array $p): array
    {
        $g = EduGame::where('teacher_id', $user->id)->find((int) ($p['game'] ?? 0));
        if (! $g) {
            return ['ok' => false, 'reply' => 'بازی پیدا نشد.'];
        }
        $res = $this->remedial->build($g, (array) ($p['questions'] ?? []), true);
        if (is_string($res)) {
            return ['ok' => false, 'reply' => $res];
        }

        return ['ok' => true, 'reply' => '🔁 آزمونِ جبرانی با ' . self::fa($res['questions']) . ' سؤال برای ' . self::fa($res['students']) . ' دانش‌آموز ساخته شد (پیش‌نویس). بازش کن، نگاهی بینداز و منتشر کن.',
            'href' => route('teacher.studio.edit', $res['game']->id, false)];
    }

    /** راهنمای کارها برای پرامپتِ هوش مصنوعی. */
    public static function aiGuide(User $user): string
    {
        $ab = array_column(MahooAbilities::forUser($user), 'key');
        if (! $ab) {
            return '';
        }
        $lines = [
            'navigate' => '• باز کردنِ صفحه: <<ACTION {"type":"navigate","target":"نامِ بخش دقیقاً مثلِ راهنما"}>>',
            'timer' => '• تایمرِ درس: <<ACTION {"type":"timer","minutes":20}>>',
            'award' => '• امتیاز به دانش‌آموز: <<ACTION {"type":"award","student":"نامِ دانش‌آموز","amount":5,"reason":"بابتِ ..."}>> (amount منفی = کسر)',
            'class_notice' => '• پیام به کلاس: <<ACTION {"type":"class_notice","text":"متنِ پیام","classroom":"نامِ کلاس یا خالی"}>>',
            'school_notice' => '• اطلاعیه‌ی مدرسه: <<ACTION {"type":"school_notice","text":"متن","audience":"all|teachers|students"}>>',
            'remedial' => '• آزمونِ جبرانی از سؤال‌های دشوارِ آخرین بازی: <<ACTION {"type":"remedial"}>>',
        ];
        $out = array_values(array_intersect_key($lines, array_flip($ab)));

        return $out ? "کارهایی که می‌توانی برای کاربر انجام بدهی (فقط وقتی صریحاً خواست، حداکثر یک خطِ ACTION در پایانِ پاسخ؛ اجرا بعد از تأییدِ خودِ کاربر است، پس بگو «تأیید کنید»):\n" . implode("\n", $out) : '';
    }

    public const RIDDLES = [
        ['آن چیست که هرچه از آن برداری، بزرگ‌تر می‌شود؟', 'چاله 🕳️'],
        ['آن چیست که دندان دارد ولی گاز نمی‌گیرد؟', 'شانه 🪮'],
        ['آن چیست که پر از سوراخ است ولی آب را نگه می‌دارد؟', 'اسفنج 🧽'],
        ['آن چیست که همیشه جلوی توست ولی نمی‌بینی‌اش؟', 'آینده 🔮'],
        ['کدام ماه از سال ۲۸ روز دارد؟', 'همه‌ی ماه‌ها ۲۸ روز دارند! 😄'],
        ['آن چیست که شب‌ها می‌آید بی‌آنکه صدایش کنی و صبح‌ها می‌رود بی‌آنکه بدزدندش؟', 'ستاره‌ها ✨'],
        ['آن چیست که پا ندارد ولی راه می‌رود؟', 'ساعت ⏰'],
        ['چه چیزی را می‌توانی بشکنی بی‌آنکه دست بزنی؟', 'قول 🤝'],
        ['آن چیست که هر چه خشک‌تر می‌کند، خیس‌تر می‌شود؟', 'حوله 🧺'],
        ['سه تا سیب داری، دو تا را برمی‌داری؛ چند سیب داری؟', 'دو تا! همان‌هایی که برداشتی 🍎'],
        ['آن چیست که گوش ندارد ولی می‌شنود و دهان ندارد ولی جواب می‌دهد؟', 'پژواک (انعکاسِ صدا) 🏔️'],
        ['عددی که اگر دو برابرش کنی و ۴ تا اضافه کنی، ۱۰ می‌شود؟', '۳ 🔢'],
    ];

    public const BOOSTS = [
        "🌟 **تو می‌تونی!** هر قهرمانی یک روز اولین قدم را برداشته. فقط ۵ دقیقه شروع کن؛ بقیه‌اش خودش می‌آید 💪",
        "🌙 خستگی یعنی داری تلاش می‌کنی! یک لیوان آب بخور، ۱۰ تا نفسِ عمیق بکش و دوباره بیا. من منتظرتم ✨",
        "🚀 مغزِ تو مثلِ عضله است؛ هر تمرین قوی‌ترش می‌کند. امروز فقط یک مأموریتِ کوچک انجام بده و جشن بگیر 🎉",
        "⭐ اشتباه کردن یعنی داری یاد می‌گیری. ستاره‌ها هم یک‌شبه نمی‌درخشند! یک قدمِ کوچک بردار 🌈",
        "🎯 یک هدفِ کوچک برای امروز بگذار: فقط یک درس، فقط ۱۵ دقیقه. بگو «تایمر ۱۵ دقیقه» تا با هم شروع کنیم ⏱️",
    ];
}
