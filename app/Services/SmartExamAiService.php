<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SmartExamAiRequest;
use App\Models\SmartQuestionBank;
use App\Support\Curriculum;
use Illuminate\Support\Facades\Http;

/**
 * طراحِ هوشمندِ سؤال — منبعِ مشترکِ آزمون‌ساز، بازی‌ساز، مأموریت، کاربرگ و بانک.
 *
 * ── چه چیزی عوض شد و چرا ──────────────────────────────────────────────
 *  • خروجیِ ساخت‌یافته: به‌جای «یک آرایه‌ی JSON برگردان» و بیرون‌کشیدنِ آن با
 *    regex، از ابزار (Claude) و json_schema (OpenAI) استفاده می‌شود؛ پس خروجی
 *    همیشه با همان شکلِ موردِ انتظار برمی‌گردد.
 *  • پرامپتِ دولایه: یک «نقش و قواعدِ طراحیِ سؤال» ثابت (system) و یک «مشخصاتِ
 *    درخواست» که از فرمِ معلم ساخته می‌شود: پایه و سن، درس، فصل و درس‌های فصل،
 *    مبحث، هدف، نوعِ آزمون، ترکیبِ نوع و دشواری، فضای داستانی و سؤال‌هایی که
 *    نباید تکرار شوند.
 *  • اعتبارسنجیِ سخت‌گیرانه: چهارگزینه‌ای دقیقاً یک پاسخِ درست و گزینه‌های
 *    یکتا دارد، درست/نادرست با فیلدِ صریح تعیین می‌شود (قبلاً همه‌ی این
 *    سؤال‌ها با پاسخِ «نادرست» ذخیره می‌شدند)، جای خالی واقعاً جای خالی دارد.
 *  • جای پاسخِ درست بینِ گزینه‌ها پخش می‌شود (مدل‌ها پاسخ را معمولاً گزینه‌ی
 *    اول یا دوم می‌گذارند).
 *  • تکراری‌ها — چه داخلِ همان دسته، چه نسبت به سؤال‌های موجودِ آزمون/بازی و
 *    بانکِ همان فصل — کنار گذاشته می‌شوند و اگر کم آمد یک بار تکمیل می‌شود.
 *
 * قراردادِ خروجی برای همه‌ی فراخوان‌ها ثابت مانده است:
 *   ['ok'=>bool, 'mode'=>ai|sample|unavailable|invalid|error, 'questions'=>[], 'message'=>?string, 'stats'=>[]]
 */
class SmartExamAiService
{
    public const TYPES = ['mc', 'tf', 'blank', 'desc'];
    public const BLOOM = ['remember', 'understand', 'apply', 'analyze'];
    public const BLANK = '……';

    private float $started = 0;

    public function generate(array $opts): array
    {
        $this->started = microtime(true);
        @set_time_limit(150);

        $o = $this->normalizeOptions($opts);
        $provider = Setting::get('ai_provider', 'anthropic');
        $key = $provider === 'openai'
            ? (Setting::get('openai_key') ?: env('OPENAI_API_KEY'))
            : (Setting::get('anthropic_key') ?: env('ANTHROPIC_API_KEY'));

        if ($o['sample'] || $provider === 'off' || ! $key) {
            if (! $o['sample']) {
                return [
                    'ok' => false, 'mode' => 'unavailable', 'questions' => [], 'stats' => [],
                    'message' => $provider === 'off'
                        ? 'سرویس هوش مصنوعی توسط ادمین غیرفعال است.'
                        : 'کلید هوش مصنوعی تنظیم نشده — برای تولید نمونه‌ی آزمایشی، گزینه‌ی «حالت نمونه» را بزنید.',
                ];
            }
            return ['ok' => true, 'mode' => 'sample', 'questions' => $this->sample($o), 'stats' => [],
                'message' => 'این‌ها سؤال‌های نمونه‌ی آزمایشی‌اند (نه تولید واقعیِ هوش مصنوعی).'];
        }

        $avoid = $this->avoidList($o);
        $accepted = [];
        $dropped = [];
        $calls = 0;
        $error = null;
        $target = $this->plan($o, $o['count'])['types'];

        try {
            // حداکثر دو فراخوانی: اصلی + یک بارِ تکمیل اگر چیزی کنار گذاشته شد
            while (count($accepted) < $o['count'] && $calls < 2) {
                if ($calls > 0 && (microtime(true) - $this->started) > 55) {
                    break;
                }
                $need = $o['count'] - count($accepted);
                $plan = $this->plan($o, $need, $calls === 0 ? null : $this->shortfall($target, $accepted));
                $raw = $provider === 'openai'
                    ? $this->viaOpenAi($key, $o, $plan, $avoid)
                    : $this->viaAnthropic($key, $o, $plan, $avoid);
                $calls++;

                [$good, $bad] = $this->validate($raw, $o, $avoid);
                foreach ($bad as $reason) {
                    $dropped[$reason] = ($dropped[$reason] ?? 0) + 1;
                }
                foreach ($good as $q) {
                    if (count($accepted) >= $o['count']) {
                        break;
                    }
                    $accepted[] = $q;
                    $avoid[] = $q['prompt'];
                }
                if (! $good && $calls === 1 && ! $raw) {
                    break; // خروجیِ خالی — تکرار فایده ندارد
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $accepted = $this->balanceAnswers($accepted);
        $stats = [
            'requested' => $o['count'], 'produced' => count($accepted),
            'dropped' => array_sum($dropped), 'reasons' => $dropped, 'calls' => $calls,
            'seconds' => round(microtime(true) - $this->started, 1),
        ];
        $this->log($o, $provider, $o['count'], count($accepted), $error === null, $error);

        if (! $accepted) {
            return [
                'ok' => false, 'mode' => $error ? 'error' : 'invalid', 'questions' => [], 'stats' => $stats,
                'message' => $error
                    ? 'خطا در ارتباط با سرویس هوش مصنوعی: ' . $this->friendlyError($error)
                    : 'هوش مصنوعی سؤالِ قابلِ قبولی برنگرداند؛ مبحث یا فصل را دقیق‌تر کنید و دوباره تلاش کنید.',
            ];
        }

        $msg = null;
        if (count($accepted) < $o['count']) {
            $msg = \App\Support\Jalali::fa((string) count($accepted)) . ' سؤال از ' . \App\Support\Jalali::fa((string) $o['count'])
                . ' آماده شد؛ بقیه به‌دلیلِ کیفیتِ پایین یا تکراری بودن کنار گذاشته شدند.';
        }
        return ['ok' => true, 'mode' => 'ai', 'questions' => $accepted, 'message' => $msg, 'stats' => $stats];
    }

    /* ═══════════════════════ ورودی ═══════════════════════ */

    private function normalizeOptions(array $o): array
    {
        $types = array_values(array_intersect((array) ($o['types'] ?? []), self::TYPES));
        if (! $types) {
            $t = $o['type'] ?? 'mc';
            $types = [$t === 'short' ? 'blank' : (in_array($t, self::TYPES, true) ? $t : 'mc')];
        }
        $difficulty = in_array($o['difficulty'] ?? null, ['easy', 'medium', 'hard', 'mixed'], true) ? $o['difficulty'] : 'medium';
        $bloom = in_array($o['bloom'] ?? null, [...self::BLOOM, 'mixed'], true) ? $o['bloom'] : 'mixed';
        $grade = trim((string) ($o['grade'] ?? '')) ?: null;
        $subject = trim((string) ($o['subject'] ?? ''));

        return [
            'count' => max(1, min(30, (int) ($o['count'] ?? 5))),
            'types' => $types,
            'difficulty' => $difficulty,
            'bloom' => $bloom,
            'level' => $o['level'] ?? Curriculum::levelOf($grade),
            'grade' => $grade,
            'subject' => $subject,
            'book' => trim((string) ($o['book'] ?? '')),
            'chapter' => trim((string) ($o['chapter'] ?? '')),
            'chapter_id' => $o['chapter_id'] ?? null,
            'lessons' => array_values(array_filter(array_map('trim', (array) ($o['lessons'] ?? [])))),
            'topic' => trim((string) ($o['topic'] ?? '')),
            'goal' => trim((string) ($o['goal'] ?? '')),
            'kind' => (string) ($o['kind'] ?? ''),
            'audience' => in_array($o['audience'] ?? null, ['exam', 'game', 'mission', 'worksheet', 'bank'], true) ? $o['audience'] : 'exam',
            'flavor' => trim((string) ($o['flavor'] ?? '')),
            'instructions' => mb_substr(trim((string) ($o['instructions'] ?? '')), 0, 500),
            'avoid' => array_values(array_filter(array_map(fn ($p) => is_string($p) ? trim($p) : '', (array) ($o['avoid'] ?? [])))),
            'sample' => (bool) ($o['sample'] ?? false),
            'school_id' => $o['school_id'] ?? null,
            'teacher_id' => $o['teacher_id'] ?? null,
        ];
    }

    /** سؤال‌هایی که نباید تکرار شوند: سؤال‌های فعلیِ فرم + تازه‌ترین سؤال‌های بانکِ همان فصل/درس. */
    private function avoidList(array $o): array
    {
        $list = $o['avoid'];
        if ($o['school_id'] && $o['subject'] && $o['grade']) {
            $bank = SmartQuestionBank::withoutGlobalScopes()
                ->where('school_id', $o['school_id'])->where('grade', $o['grade'])->where('subject', $o['subject'])
                ->when($o['chapter_id'], fn ($q) => $q->where('chapter_id', $o['chapter_id']))
                ->latest('id')->limit(25)->pluck('prompt')->all();
            $list = array_merge($list, $bank);
        }
        $seen = [];
        $out = [];
        foreach ($list as $p) {
            $f = Curriculum::fingerprint($p);
            if (! isset($seen[$f])) {
                $seen[$f] = true;
                $out[] = mb_substr($p, 0, 160);
            }
        }
        return array_slice($out, 0, 40);
    }

    /** تقسیمِ تعداد بینِ نوع‌ها و سطح‌های دشواری. */
    private function plan(array $o, int $count, ?array $types = null): array
    {
        if (! $types || array_sum($types) !== $count) {
            $types = [];
            $n = count($o['types']);
            foreach ($o['types'] as $i => $t) {
                $types[$t] = intdiv($count, $n) + ($i < $count % $n ? 1 : 0);
            }
        }
        $types = array_filter($types);

        if ($o['difficulty'] === 'mixed') {
            $easy = (int) round($count * 0.3);
            $hard = (int) round($count * 0.2);
            $diff = ['easy' => $easy, 'medium' => max(0, $count - $easy - $hard), 'hard' => $hard];
        } else {
            $diff = [$o['difficulty'] => $count];
        }
        return ['count' => $count, 'types' => $types, 'difficulty' => array_filter($diff)];
    }

    /** کسریِ هر نوع نسبت به هدف — برای فراخوانیِ تکمیلی. */
    private function shortfall(array $target, array $accepted): array
    {
        $have = array_count_values(array_column($accepted, 'type'));
        $out = [];
        foreach ($target as $t => $n) {
            if (($d = $n - ($have[$t] ?? 0)) > 0) {
                $out[$t] = $d;
            }
        }
        return $out;
    }

    /* ═══════════════════════ پرامپت ═══════════════════════ */

    public function systemPrompt(array $o): string
    {
        $age = Curriculum::ageOf($o['grade']);
        $isGame = $o['audience'] === 'game';

        $rules = [
            'تو طراحِ حرفه‌ایِ سؤال و کارشناسِ برنامه‌ی درسیِ رسمیِ آموزش‌وپرورشِ ایران هستی و برای معلمِ کلاس سؤال می‌سازی.',
            '',
            '## محتوا',
            '- فقط از محدوده‌ی همان پایه، درس، فصل و مبحثی که در درخواست آمده سؤال بساز؛ مطابقِ کتابِ درسیِ رسمیِ همان پایه در ایران.',
            '- از مفاهیمِ فصل‌های بعدی یا پایه‌های بالاتر استفاده نکن. عددها، واحدها و واژه‌ها در سطحِ همان پایه باشند.',
            '- اطلاعات باید از نظرِ علمی کاملاً دقیق باشد. اگر درباره‌ی درستیِ مطلبی مطمئن نیستی، آن سؤال را نساز.',
            '- اگر درس فارسی، علوم، مطالعات یا دینی است، سؤالِ ریاضی نساز (و برعکس).',
            '',
            '## زبان',
            '- فارسیِ معیار، با جمله‌های کوتاه و روشن' . ($age ? " برای دانش‌آموزِ حدودِ {$age}" : '') . '.',
            '- نیم‌فاصله را درست بنویس (می‌شود، کتاب‌ها) و عددها را با رقمِ فارسی بنویس.',
            '- صورتِ سؤال پاسخ را لو ندهد و به یک سؤالِ دیگر وابسته نباشد.',
            '- از صورتِ منفی پرهیز کن؛ اگر ناگزیر بود، واژه‌ی «نیست» یا «نمی‌باشد» در متن روشن باشد.',
            '',
            '## قواعدِ هر نوع سؤال',
            '- چهارگزینه‌ای (mc): دقیقاً ۴ گزینه و دقیقاً یک گزینه‌ی درست. گزینه‌های نادرست باورپذیر باشند و از اشتباه‌های رایجِ دانش‌آموزانِ همین پایه ساخته شوند. طول و ساختارِ گزینه‌ها نزدیک به هم باشد. گزینه‌های «همه‌ی موارد»، «هیچ‌کدام» یا «الف و ب» ممنوع است. گزینه‌ها تکراری یا هم‌معنی نباشند.',
            '- درست/نادرست (tf): یک جمله‌ی خبری که قطعاً درست یا قطعاً نادرست است؛ is_true را تعیین کن. قیدهای فریبنده مثل «همیشه» و «هرگز» فقط اگر از نظر علمی دقیق است. تعدادِ جمله‌های درست و نادرست تقریباً برابر باشد. choices را خالی بگذار.',
            '- جای خالی (blank): یک جمله با دقیقاً یک جای خالیِ «' . self::BLANK . '»؛ پاسخ (answer) یک واژه، عدد یا عبارتِ کوتاه و یکتا باشد. choices را خالی بگذار.',
            '- تشریحی (desc): سؤالِ باز؛ در answer یک پاسخِ نمونه‌ی کامل ولی کوتاه بنویس. choices را خالی بگذار.',
            '',
            '## بخش‌های آموزشی',
            '- explanation: یک یا دو جمله با لحنِ مهربان: چرا پاسخ درست است و (در چهارگزینه‌ای) اشتباهِ رایج کدام است.',
            '- hint: یک راهنماییِ کوتاه که فکرِ دانش‌آموز را هدایت کند ولی پاسخ را لو ندهد.',
            '- difficulty و bloom را صادقانه برچسب بزن (remember=یادآوری، understand=درک، apply=کاربرد، analyze=تحلیل).',
            '- topic: مبحثِ دقیقِ همان سؤال در چند واژه.',
            '',
            '## تنوع',
            '- هر سؤال مفهوم یا زاویه‌ی متفاوتی را بسنجد؛ مثال‌ها، نام‌ها و عددها تکراری نباشند.',
            '- با سؤال‌های فهرستِ «تکراری نساز» یکسان یا هم‌معنی نباشد.',
        ];

        if ($isGame) {
            $rules[] = '';
            $rules[] = '## این سؤال‌ها داخلِ بازی نمایش داده می‌شوند';
            $rules[] = '- صورتِ سؤال حداکثر حدودِ ۱۲۰ نویسه و هر گزینه حداکثر حدودِ ۳۵ نویسه باشد تا روی صفحه‌ی بازی جا شود.';
            $rules[] = '- لحن شاد، کوتاه و چالشی باشد. hint برای همه‌ی سؤال‌ها الزامی است.';
        }
        $rules[] = '';
        $rules[] = 'خروجی را فقط از راهِ ابزارِ submit_questions برگردان.';

        return implode("\n", $rules);
    }

    public function userPrompt(array $o, array $plan, array $avoid): string
    {
        $fa = fn ($n) => \App\Support\Jalali::fa((string) $n);
        $typeFa = ['mc' => 'چهارگزینه‌ای', 'tf' => 'درست/نادرست', 'blank' => 'جای خالی', 'desc' => 'تشریحی'];
        $diffFa = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار'];
        $bloomFa = ['remember' => 'یادآوری', 'understand' => 'درک', 'apply' => 'کاربرد', 'analyze' => 'تحلیل'];
        $kindFa = [
            'diagnostic' => 'تشخیصی — مفاهیمِ کلیدیِ فصل را گسترده پوشش بده و از آسان به دشوار برو تا کج‌فهمی‌ها آشکار شود.',
            'practice' => 'تمرینی — تمرینِ متعادل برای تثبیتِ یادگیری.',
            'class' => 'کلاسی — مناسبِ پرسش‌وپاسخِ سرِ کلاس، کوتاه و روشن.',
            'formal' => 'رسمی — به سبکِ امتحانِ رسمی و کتابِ کار، دقیق و استاندارد.',
            'remedial' => 'جبرانی — روی اشتباه‌های رایج تمرکز کن، سؤال‌ها ساده‌تر و توضیح‌ها کامل‌تر.',
            'game' => 'بازی‌محور — کوتاه، شاد و چالشی.',
        ];
        $audienceFa = [
            'game' => 'سؤالِ بازیِ آموزشی', 'mission' => 'مأموریتِ روزانه‌ی کوتاه و انگیزشی',
            'worksheet' => 'کاربرگِ چاپی', 'exam' => 'آزمون', 'bank' => 'بانکِ سؤالِ مدرسه',
        ];

        $L = ['## مشخصاتِ درخواست'];
        $L[] = '- کاربرد: ' . $audienceFa[$o['audience']];
        if ($o['level'] || $o['grade']) {
            $L[] = '- مقطع و پایه: ' . trim(($o['level'] ? $o['level'] . ' — ' : '') . ($o['grade'] ? 'پایه‌ی ' . $o['grade'] : ''))
                . (($age = Curriculum::ageOf($o['grade'])) ? " (حدودِ {$age})" : '');
        }
        if ($o['subject']) {
            $L[] = '- درس: ' . $o['subject'] . ($o['book'] && $o['book'] !== $o['subject'] ? " (کتابِ «{$o['book']}»)" : '');
        }
        if ($o['chapter']) {
            $L[] = '- فصل: ' . $o['chapter'];
        }
        if ($o['lessons']) {
            $L[] = '- درس‌ها/مبحث‌های این فصل: ' . implode('، ', $o['lessons']);
        }
        if ($o['topic']) {
            $L[] = '- مبحثِ دقیق (سؤال‌ها فقط درباره‌ی همین): ' . $o['topic'];
        }
        if ($o['goal']) {
            $L[] = '- هدفِ آموزشی: ' . $o['goal'];
        }
        if ($o['kind'] && isset($kindFa[$o['kind']])) {
            $L[] = '- نوعِ آزمون: ' . $kindFa[$o['kind']];
        }

        $L[] = '';
        $L[] = '## چه بسازی';
        $L[] = '- تعدادِ کل: ' . $fa($plan['count']) . ' سؤال';
        $L[] = '- ترکیبِ نوع: ' . implode('، ', array_map(fn ($t, $n) => $fa($n) . ' ' . $typeFa[$t] . " ({$t})", array_keys($plan['types']), $plan['types']));
        $L[] = '- دشواری: ' . implode('، ', array_map(fn ($d, $n) => $fa($n) . ' ' . $diffFa[$d], array_keys($plan['difficulty']), $plan['difficulty']));
        $L[] = '- سطحِ شناختی: ' . ($o['bloom'] === 'mixed'
            ? 'ترکیبی (بیشتر درک و کاربرد، کمی یادآوری و تحلیل)'
            : $bloomFa[$o['bloom']] . ' (' . $o['bloom'] . ')');
        if ($o['flavor']) {
            $L[] = "- فضای داستانی: موقعیت‌ها، نام‌ها و مثال‌ها را از دنیای «{$o['flavor']}» بساز؛ ولی مفهومِ درسی و پاسخِ درست دقیق بماند.";
        }
        if ($o['instructions']) {
            $L[] = '- توضیحِ معلم: ' . $o['instructions'];
        }

        if ($avoid) {
            $L[] = '';
            $L[] = '## تکراری نساز — این سؤال‌ها از قبل وجود دارند (نه یکسان، نه هم‌معنی):';
            foreach (array_slice($avoid, 0, 40) as $p) {
                $L[] = '- ' . $p;
            }
        }
        return implode("\n", $L);
    }

    /** اسکیمای خروجی — برای هر دو سرویس یکسان (سازگار با حالتِ strictِ OpenAI). */
    public function schema(): array
    {
        $str = ['type' => ['string', 'null']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['questions'],
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'prompt', 'choices', 'is_true', 'answer', 'explanation', 'hint', 'difficulty', 'bloom', 'topic'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => self::TYPES],
                            'prompt' => ['type' => 'string', 'description' => 'متنِ سؤال'],
                            'choices' => [
                                'type' => 'array',
                                'description' => 'فقط برای mc: دقیقاً ۴ گزینه با یک گزینه‌ی درست؛ برای بقیه آرایه‌ی خالی',
                                'items' => [
                                    'type' => 'object', 'additionalProperties' => false,
                                    'required' => ['text', 'correct'],
                                    'properties' => ['text' => ['type' => 'string'], 'correct' => ['type' => 'boolean']],
                                ],
                            ],
                            'is_true' => ['type' => ['boolean', 'null'], 'description' => 'فقط برای tf'],
                            'answer' => $str + ['description' => 'برای blank و desc: پاسخ'],
                            'explanation' => ['type' => 'string'],
                            'hint' => $str,
                            'difficulty' => ['type' => 'string', 'enum' => ['easy', 'medium', 'hard']],
                            'bloom' => ['type' => 'string', 'enum' => self::BLOOM],
                            'topic' => $str,
                        ],
                    ],
                ],
            ],
        ];
    }

    /* ═══════════════════════ سرویس‌ها ═══════════════════════ */

    private function maxTokens(array $plan): int
    {
        $per = isset($plan['types']['desc']) ? 750 : 520;
        return min(16000, 900 + $plan['count'] * $per);
    }

    private function viaAnthropic(string $key, array $o, array $plan, array $avoid): array
    {
        $body = [
            'model' => Setting::get('anthropic_model') ?: 'claude-haiku-4-5-20251001',
            'max_tokens' => $this->maxTokens($plan),
            'temperature' => 0.7,
            'system' => $this->systemPrompt($o),
            'tools' => [[
                'name' => 'submit_questions',
                'description' => 'ثبتِ سؤال‌های طراحی‌شده برای معلم',
                'input_schema' => $this->schema(),
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => 'submit_questions'],
            'messages' => [['role' => 'user', 'content' => $this->userPrompt($o, $plan, $avoid)]],
        ];
        $send = fn ($b) => Http::withHeaders([
            'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json',
        ])->timeout(100)->post('https://api.anthropic.com/v1/messages', $b);

        $res = $send($body);
        if ($res->status() === 400 && str_contains(strtolower($res->body()), 'temperature')) {
            unset($body['temperature']);   // برخی مدل‌ها دما را نمی‌پذیرند
            $res = $send($body);
        }
        if ($res->failed()) {
            throw new \RuntimeException('HTTP ' . $res->status() . ' ' . mb_substr((string) data_get($res->json(), 'error.message', ''), 0, 160));
        }
        foreach ((array) data_get($res->json(), 'content', []) as $block) {
            if (($block['type'] ?? '') === 'tool_use') {
                return (array) data_get($block, 'input.questions', []);
            }
        }
        // مدلی که ابزار را نادیده گرفته باشد: تلاش برای خواندنِ JSON از متن
        return $this->extractJson((string) data_get($res->json(), 'content.0.text', ''));
    }

    private function viaOpenAi(string $key, array $o, array $plan, array $avoid): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($o)],
            ['role' => 'user', 'content' => $this->userPrompt($o, $plan, $avoid)],
        ];
        $model = Setting::get('openai_model') ?: 'gpt-4o-mini';
        $attempts = [
            ['temperature' => 0.7, 'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'questions', 'strict' => true, 'schema' => $this->schema()]]],
            ['response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'questions', 'strict' => true, 'schema' => $this->schema()]]],
            ['response_format' => ['type' => 'json_object']],
        ];
        $res = null;
        foreach ($attempts as $i => $extra) {
            $msgs = $messages;
            if ($i === 2) {
                $msgs[0]['content'] .= "\n\nخروجی فقط یک شیءِ JSON با کلیدِ questions باشد، با همان فیلدهای اسکیما: "
                    . json_encode($this->schema(), JSON_UNESCAPED_UNICODE);
            }
            $res = Http::withToken($key)->timeout(100)->post('https://api.openai.com/v1/chat/completions',
                ['model' => $model, 'messages' => $msgs] + $extra);
            if ($res->status() !== 400) {
                break;   // فقط خطای «پارامترِ پشتیبانی‌نشده» را با حالتِ ساده‌تر تکرار می‌کنیم
            }
        }
        if ($res->failed()) {
            throw new \RuntimeException('HTTP ' . $res->status() . ' ' . mb_substr((string) data_get($res->json(), 'error.message', ''), 0, 160));
        }
        $text = (string) data_get($res->json(), 'choices.0.message.content', '');
        $data = json_decode($text, true);
        return is_array($data) && isset($data['questions']) ? (array) $data['questions'] : $this->extractJson($text);
    }

    private function extractJson(string $text): array
    {
        $data = json_decode(trim($text), true);
        if (is_array($data)) {
            return isset($data['questions']) ? (array) $data['questions'] : (array_is_list($data) ? $data : []);
        }
        if (preg_match('/\{.*\}/su', $text, $m) && is_array($d = json_decode($m[0], true)) && isset($d['questions'])) {
            return (array) $d['questions'];
        }
        if (preg_match('/\[.*\]/su', $text, $m) && is_array($d = json_decode($m[0], true))) {
            return $d;
        }
        return [];
    }

    private function friendlyError(string $e): string
    {
        if (str_contains($e, 'HTTP 401') || str_contains($e, 'HTTP 403')) {
            return 'کلیدِ API نامعتبر است یا دسترسی ندارد (ادمین کل → تنظیمات).';
        }
        if (str_contains($e, 'HTTP 429')) {
            return 'سقفِ استفاده از سرویس پر شده؛ چند دقیقه بعد دوباره تلاش کنید.';
        }
        if (str_contains($e, 'timed out') || str_contains($e, 'cURL error 28')) {
            return 'پاسخ طول کشید؛ تعدادِ سؤال را کمتر کنید و دوباره تلاش کنید.';
        }
        return mb_substr($e, 0, 200);
    }

    /* ═══════════════════════ اعتبارسنجی ═══════════════════════ */

    /** @return array{0: array, 1: array} [سؤال‌های سالم, دلیل‌های کنارگذاشتن] */
    public function validate(array $raw, array $o, array $avoid = []): array
    {
        $isGame = $o['audience'] === 'game';
        $maxPrompt = $isGame ? 400 : 600;
        $maxChoice = $isGame ? 120 : 200;
        $allowed = $o['types'];
        $seen = [];
        foreach ($avoid as $p) {
            $seen[Curriculum::fingerprint($p)] = true;
        }

        $good = [];
        $bad = [];
        foreach ($raw as $q) {
            if (! is_array($q)) {
                $bad[] = 'شکلِ نامعتبر';
                continue;
            }
            $type = $q['type'] ?? $allowed[0];
            $type = $type === 'short' ? 'blank' : $type;
            if (! in_array($type, self::TYPES, true)) {
                $bad[] = 'نوعِ نامعتبر';
                continue;
            }
            if (! in_array($type, $allowed, true)) {
                $bad[] = 'نوعِ ناخواسته';
                continue;
            }
            $prompt = $this->clean($q['prompt'] ?? '');
            if (mb_strlen($prompt) < 6) {
                $bad[] = 'متنِ سؤال خالی یا کوتاه';
                continue;
            }
            if (mb_strlen($prompt) > $maxPrompt) {
                $bad[] = 'متنِ سؤال بیش از حد بلند';
                continue;
            }
            $fp = Curriculum::fingerprint($prompt);
            if (isset($seen[$fp])) {
                $bad[] = 'تکراری';
                continue;
            }

            $item = [
                'type' => $type, 'prompt' => $prompt, 'choices' => [], 'answer' => null,
                'explanation' => $this->clean($q['explanation'] ?? '') ?: null,
                'hint' => $this->clean($q['hint'] ?? '') ?: null,
                'difficulty' => in_array($q['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium',
                'bloom' => in_array($q['bloom'] ?? null, self::BLOOM, true) ? $q['bloom'] : null,
                'topic' => $this->clean($q['topic'] ?? '') ?: ($o['topic'] ?: null),
                'goal' => $o['goal'] ?: null,
            ];

            if ($type === 'mc') {
                $choices = [];
                $cfp = [];
                foreach ((array) ($q['choices'] ?? []) as $c) {
                    $text = $this->clean(is_array($c) ? ($c['text'] ?? $c['value'] ?? '') : (string) $c);
                    if ($text === '') {
                        continue;
                    }
                    $f = Curriculum::fingerprint($text);
                    if (isset($cfp[$f])) {
                        continue;   // گزینه‌ی تکراری
                    }
                    $cfp[$f] = true;
                    $choices[] = ['value' => $text, 'correct' => (bool) (is_array($c) ? ($c['correct'] ?? false) : false)];
                }
                $correct = count(array_filter($choices, fn ($c) => $c['correct']));
                if ($correct !== 1) {
                    $bad[] = $correct === 0 ? 'بدونِ گزینه‌ی درست' : 'چند گزینه‌ی درست';
                    continue;
                }
                if (count($choices) > 4) {
                    // نگه‌داشتنِ درست + سه گزینه‌ی نادرستِ اول
                    $right = array_values(array_filter($choices, fn ($c) => $c['correct']));
                    $wrong = array_slice(array_values(array_filter($choices, fn ($c) => ! $c['correct'])), 0, 3);
                    $choices = array_merge($right, $wrong);
                }
                if (count($choices) < 3) {
                    $bad[] = 'گزینه‌ی کم';
                    continue;
                }
                if (collect($choices)->contains(fn ($c) => mb_strlen($c['value']) > $maxChoice
                    || preg_match('/^(همه\s?ی?\s?موارد|هیچ\s?کدام|هر\s?دو|الف\s?و\s?ب|همه‌ی گزینه)/u', $c['value']))) {
                    $bad[] = 'گزینه‌ی نامناسب';
                    continue;
                }
                $item['choices'] = $choices;
            } elseif ($type === 'tf') {
                $isTrue = $q['is_true'] ?? null;
                if ($isTrue === null && isset($q['answer'])) {
                    $a = $this->clean((string) $q['answer']);
                    $isTrue = in_array($a, ['درست', 'صحیح', 'true', '1'], true) ? true
                        : (in_array($a, ['نادرست', 'غلط', 'false', '0'], true) ? false : null);
                }
                if ($isTrue === null && ! empty($q['choices'])) {
                    foreach ((array) $q['choices'] as $c) {
                        if (! empty($c['correct'])) {
                            $isTrue = str_starts_with($this->clean($c['text'] ?? $c['value'] ?? ''), 'درست');
                        }
                    }
                }
                if (! is_bool($isTrue)) {
                    $bad[] = 'پاسخِ درست/نادرست نامشخص';
                    continue;
                }
                $item['choices'] = [['value' => 'درست', 'correct' => $isTrue], ['value' => 'نادرست', 'correct' => ! $isTrue]];
                $item['answer'] = $isTrue ? 'درست' : 'نادرست';
            } else {
                $answer = $this->clean((string) ($q['answer'] ?? ''));
                if ($answer === '') {
                    $bad[] = 'بدونِ پاسخ';
                    continue;
                }
                if ($type === 'blank') {
                    $marked = preg_replace('/(\.{3,}|…+|_{3,}|ـ{3,}|-{3,}|\[\s*\]|\(\s*\))/u', self::BLANK, $prompt, 1, $n);
                    if (! $n) {
                        $pos = mb_strpos($prompt, $answer);
                        if ($pos === false) {
                            $bad[] = 'جای خالی ندارد';
                            continue;
                        }
                        $marked = mb_substr($prompt, 0, $pos) . self::BLANK . mb_substr($prompt, $pos + mb_strlen($answer));
                    }
                    $item['prompt'] = $marked;
                    if (mb_strlen($answer) > 60) {
                        $bad[] = 'پاسخِ جای خالی بلند';
                        continue;
                    }
                }
                $item['answer'] = $answer;
            }

            $fp2 = Curriculum::fingerprint($item['prompt']);
            if ($fp2 !== $fp && isset($seen[$fp2])) {
                $bad[] = 'تکراری';
                continue;
            }
            $seen[$fp] = $seen[$fp2] = true;
            $good[] = $item;
        }
        return [$good, $bad];
    }

    /** پاک‌سازی: فاصله‌ها، حروفِ عربی، ارقامِ فارسی. */
    private function clean(mixed $s): string
    {
        $s = is_string($s) ? $s : (is_scalar($s) ? (string) $s : '');
        $s = strtr($s, ['ي' => 'ی', 'ك' => 'ک', '٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴', '٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹']);
        // ارقامِ لاتین به فارسی — به‌جز داخلِ واژه‌های لاتین (مثل CO2)
        $s = preg_replace_callback('/(?<![A-Za-z])\d+/u', fn ($m) => strtr($m[0], ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']), $s);
        return trim(preg_replace('/[ \t]+/u', ' ', $s));
    }

    /**
     * پخشِ جای گزینه‌ی درست: در هر دسته، پاسخِ درستِ سؤالِ iام در جایگاهِ
     * (شروعِ تصادفی + i) mod 4 می‌نشیند و گزینه‌های نادرست جابه‌جا می‌شوند.
     */
    public function balanceAnswers(array $questions): array
    {
        $slot = random_int(0, 3);
        foreach ($questions as &$q) {
            if ($q['type'] !== 'mc' || count($q['choices']) < 2) {
                continue;
            }
            $right = array_values(array_filter($q['choices'], fn ($c) => $c['correct']))[0];
            $wrong = array_values(array_filter($q['choices'], fn ($c) => ! $c['correct']));
            shuffle($wrong);
            $pos = $slot % count($q['choices']);
            array_splice($wrong, $pos, 0, [$right]);
            $q['choices'] = $wrong;
            $slot++;
        }
        return $questions;
    }

    private function log(array $o, string $provider, int $requested, int $produced, bool $ok, ?string $err): void
    {
        try {
            SmartExamAiRequest::create([
                'school_id' => $o['school_id'], 'teacher_id' => $o['teacher_id'],
                'provider' => $provider,
                'model' => $provider === 'openai' ? Setting::get('openai_model') : Setting::get('anthropic_model'),
                'subject' => $o['subject'] ?: null,
                'requested' => $requested, 'produced' => $produced, 'ok' => $ok,
                'error' => $err ? mb_substr($err, 0, 240) : null,
            ]);
        } catch (\Throwable $e) {
            // ثبت آمار نباید مسیر اصلی را بشکند
        }
    }

    /* ═══════════════════════ حالتِ نمونه ═══════════════════════ */

    /** سؤال‌های نمونه‌ی موضوع‌آگاه برای وقتی کلید نیست — صریحاً برچسبِ «نمونه» دارند. */
    private function sample(array $o): array
    {
        $topic = $o['topic'] ?: ($o['chapter'] ?: ($o['subject'] ?: 'درس'));
        $fx = $o['flavor'] ? " (در فضای «{$o['flavor']}»)" : '';
        $isMath = (bool) preg_match('/ریاض|حساب|هندسه|math/u', $o['subject']);
        $plan = $this->plan($o, $o['count']);
        $out = [];
        foreach ($plan['types'] as $type => $n) {
            for ($i = 0; $i < $n; $i++) {
                $base = ['type' => $type, 'explanation' => 'این یک سؤالِ نمونه است؛ پیش از استفاده ویرایشش کنید.',
                    'hint' => 'به مثال‌های کتاب فکر کن.', 'difficulty' => $o['difficulty'] === 'mixed' ? 'medium' : $o['difficulty'],
                    'bloom' => 'understand', 'topic' => $topic, 'goal' => $o['goal'] ?: null, 'choices' => [], 'answer' => null];
                if ($type === 'mc' && $isMath) {
                    $a = random_int(2, 12);
                    $b = random_int(2, 12);
                    $ans = $a * $b;
                    $out[] = ['prompt' => $this->clean("حاصلِ {$a} × {$b} چند می‌شود؟{$fx} (نمونه)"),
                        'choices' => array_map(fn ($v) => ['value' => $this->clean((string) $v), 'correct' => $v === $ans], [$ans, $ans + $a, $ans - $b, $ans + 1])] + $base;
                } elseif ($type === 'mc') {
                    $out[] = ['prompt' => "کدام گزینه درباره‌ی «{$topic}»{$fx} درست است؟ (نمونه — ویرایش کنید)",
                        'choices' => [['value' => 'گزینه‌ی درست', 'correct' => true], ['value' => 'گزینه‌ی نادرستِ ۱', 'correct' => false],
                            ['value' => 'گزینه‌ی نادرستِ ۲', 'correct' => false], ['value' => 'گزینه‌ی نادرستِ ۳', 'correct' => false]]] + $base;
                } elseif ($type === 'tf') {
                    $t = $i % 2 === 0;
                    $out[] = ['prompt' => "یک جمله‌ی " . ($t ? 'درست' : 'نادرست') . " درباره‌ی «{$topic}»{$fx}. (نمونه — ویرایش کنید)",
                        'choices' => [['value' => 'درست', 'correct' => $t], ['value' => 'نادرست', 'correct' => ! $t]], 'answer' => $t ? 'درست' : 'نادرست'] + $base;
                } elseif ($type === 'blank') {
                    $out[] = ['prompt' => "در مبحثِ «{$topic}»، واژه‌ی کلیدی " . self::BLANK . " است. (نمونه)", 'answer' => 'پاسخ'] + $base;
                } else {
                    $out[] = ['prompt' => "درباره‌ی «{$topic}»{$fx} یک توضیحِ کوتاه بنویس. (نمونه)", 'answer' => 'پاسخِ نمونه'] + $base;
                }
            }
        }
        return $this->balanceAnswers($out);
    }
}
