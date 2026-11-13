<?php

namespace App\Services\Bot;

use App\Models\User;
use App\Support\AiConfig;
use App\Support\Jalali;
use Illuminate\Support\Facades\Http;

/**
 * 🧠 مغزِ دستیار با «ابزار» (function calling).
 *
 * به‌جای این‌که هوش مصنوعی در یک حرکت حدس بزند، اول با ابزارهای خواندنی (BotData) داده‌ی واقعی را
 * می‌بیند — حضور، نمره، کارهای فرستاده‌شده، آزمون، امتیاز، پیام… — و بعد یا جواب می‌دهد یا یک
 * «کار» را پیشنهاد می‌کند. کارهای ثبت‌کننده (حضور، نمره، امتیاز، اطلاعیه، تصحیح…) هرگز مستقیم
 * انجام نمی‌شوند: به بات برمی‌گردند تا پیش‌نمایش و تأییدِ معلم بگیرند.
 *
 * برای ChatGPT و سازگارها (DeepSeek، Gemini، OpenRouter، واسطه‌های ایرانی) و Claude.
 * اگر سرویس ابزار را نپذیرفت، BotAgent (روشِ قدیمیِ JSON) جایگزین می‌شود.
 *
 * خروجی: ['type' => 'answer', 'text'] | ['type' => 'action', 'tool', 'args', 'text'] | ['type' => 'error', 'text']
 */
class BotBrain
{
    public const ACTIONS = ['record_attendance', 'record_grades', 'give_points', 'record_discipline', 'send_announcement', 'grade_submissions', 'create_quiz',
        'build_from_items', 'open_item', 'open_screen'];

    private const ROUNDS = 5;

    /** برای آزمایش: ردِ ابزارهای خوانده‌شده. */
    public array $trace = [];

    public function __construct(private User $user, private TeacherBot $bot) {}

    public static function available(User $user): bool
    {
        return BotAgent::available($user);
    }

    /* ═════════════════════════ ابزارها ═════════════════════════ */

    private static function actionTools(): array
    {
        $str = ['type' => 'string'];
        $int = ['type' => 'integer'];
        $names = ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'نام‌ها دقیقاً با املای فهرستِ کلاس'];

        return [
            'record_attendance' => ['ثبتِ حضور و غیاب (هر کس نامش نیامده حاضر است). پیش‌نمایش و تأیید می‌گیرد.', [
                'date' => $str + ['description' => 'YYYY-MM-DD؛ خالی = امروز'], 'class' => $str, 'absent' => $names, 'late' => $names, 'excused' => $names]],
            'record_grades' => ['ثبتِ نمره در دفترِ نمره (ستونِ تازه). توصیفی: خیلی خوب، خوب، قابل قبول، نیاز به تلاش. تکلیف: کامل، ناقص، انجام نداده.', [
                'class' => $str, 'lesson' => $str, 'topic' => $str, 'type' => ['type' => 'string', 'enum' => ['descriptive', 'numeric', 'homework']], 'max' => $int,
                'grades' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['name' => $str, 'value' => $str]]],
                'rest' => $str + ['description' => 'مقدار برای بقیه‌ی کلاس، یا خالی']]],
            'give_points' => ['دادن (یا کم‌کردنِ منفی) امتیاز به بچه‌ها. حداکثر ۵۰.', ['class' => $str, 'names' => $names + ['description' => 'یا ["همه"]'], 'amount' => $int, 'reason' => $str]],
            'record_discipline' => ['ثبتِ مورد انضباطی (تشویق/تذکر) طبقِ موضوع‌های مدرسه', ['names' => $names, 'topic' => $str, 'note' => $str]],
            'send_announcement' => ['فرستادنِ اطلاعیه به کلاس یا پیام به یک دانش‌آموز', ['to' => $str + ['description' => '"class" یا نامِ دانش‌آموز'], 'class' => $str, 'text' => $str]],
            'grade_submissions' => ['تصحیح/نمره‌دادنِ گروهی به کارهای فرستاده‌شده (روخوانی، املا، کاربرگ). فهرستِ دقیق را پیش‌نمایش می‌دهد.', [
                'kind' => ['type' => 'string', 'enum' => ['reading', 'dictation', 'worksheet', 'any']], 'date' => $str, 'after_hour' => $int + ['description' => '«امشب» = 16'],
                'days' => $int + ['description' => '«این هفته» = 7'], 'task' => $str + ['description' => 'بخشی از عنوانِ تکلیف'], 'names' => $names + ['description' => 'فقط این‌ها'],
                'except' => $names + ['description' => 'به‌جز این‌ها'], 'regrade' => ['type' => 'boolean'],
                'value' => $str + ['description' => 'خیلی خوب | خوب | قابل قبول | نیاز به تلاش | عالی (کاربرگ) | عددِ نمره از ۲۰'], 'feedback' => $str]],
            'create_quiz' => ['ساختِ آزمون، بازیِ آموزشی یا مسابقه‌ی زنده با هوش مصنوعی (معلم بعد تنظیمات و سؤال‌ها را می‌بیند)', [
                'kind' => ['type' => 'string', 'enum' => ['exam', 'game', 'live']], 'class' => $str, 'lesson' => $str, 'topic' => $str, 'count' => $int,
                'difficulty' => ['type' => 'string', 'enum' => ['easy', 'medium', 'hard', 'mixed']]]],
            'build_from_items' => ['ساختنِ آزمون/بازی/مسابقه‌ی زنده از سؤال‌های آزمون‌ها، بازی‌ها یا مسابقه‌های قبلیِ خودِ معلم (همان سؤال‌ها یا با هوش مصنوعی سؤال‌های تازه‌ی مشابه). معلم پیش از انتشار بازبینی می‌کند.', [
                'kind' => ['type' => 'string', 'enum' => ['exam', 'game', 'live'], 'description' => 'چه چیزی ساخته شود'],
                'from_type' => ['type' => 'string', 'enum' => ['exam', 'game', 'live'], 'description' => 'سؤال‌ها از کجا'],
                'titles' => ['type' => 'array', 'items' => $str, 'description' => 'عنوان‌ها (اگر معلم نام برد)'],
                'last_n' => $int + ['description' => 'یا: چند موردِ آخر («سه بازیِ آخر» = 3)'],
                'count' => $int + ['description' => 'تعدادِ سؤالِ نهایی (اختیاری)'], 'shuffle' => ['type' => 'boolean'],
                'mode' => ['type' => 'string', 'enum' => ['copy', 'similar'], 'description' => 'copy = همان سؤال‌ها؛ similar = سؤال‌های تازه‌ی مشابه با هوش مصنوعی'],
                'title' => $str, 'class' => $str]],
            'open_item' => ['باز کردنِ کارتِ یک آزمون/بازی/مسابقه (برای انتشار، بستن، حذف، نتیجه و سؤال‌ها با دکمه)', [
                'type' => ['type' => 'string', 'enum' => ['exam', 'game', 'live']], 'title' => $str]],
            'open_screen' => ['باز کردنِ یک صفحه‌ی بات برای معلم', ['screen' => ['type' => 'string', 'enum' => [
                'library', 'grading_queue', 'messages', 'students', 'question_bank', 'attendance_board', 'reports', 'settings', 'menu']],
                'kind' => ['type' => 'string', 'enum' => ['reading', 'dictation', 'worksheet']]]],
        ];
    }

    /** [name => [description, properties]] → قالبِ هر سرویس */
    private static function schemas(string $family): array
    {
        $out = [];
        foreach (BotData::tools() + self::actionTools() as $name => [$desc, $props]) {
            $schema = ['type' => 'object', 'properties' => (object) $props];
            $out[] = $family === 'anthropic'
                ? ['name' => $name, 'description' => $desc, 'input_schema' => $schema]
                : ['type' => 'function', 'function' => ['name' => $name, 'description' => $desc, 'parameters' => $schema]];
        }

        return $out;
    }

    /* ═════════════════════════ پرامپت ═════════════════════════ */

    public function system(bool $voice): string
    {
        $now = now();
        $days = [];
        for ($i = 0; $i <= 6; $i++) {
            $d = $now->copy()->subDays($i);
            $days[] = ($i === 0 ? 'امروز' : ($i === 1 ? 'دیروز' : ($i === 2 ? 'پریروز' : "{$i} روز پیش"))) . ' = ' . Jalali::format($d, true) . ' = ' . $d->toDateString();
        }
        $classes = $this->bot->classes($this->user);
        $total = 0;
        $cls = [];
        foreach ($classes as $c) {
            $names = $c->students()->orderBy('name')->pluck('name')->all();
            $total += count($names);
            $subjects = collect(rescue(fn () => $c->subjectNames(), [], false))->pluck('name')->filter()->implode('، ');
            $cls[] = ['name' => $c->name, 'grade' => $c->grade, 'names' => $names, 'subjects' => $subjects];
        }
        // فهرستِ نام‌ها برای تطبیقِ نامِ شنیده‌شده (اگر خیلی زیاد است، با ابزار list_students)
        $clsTxt = collect($cls)->map(fn ($c) => '• ' . $c['name'] . ($c['grade'] ? " (پایه‌ی {$c['grade']})" : '') . ': '
            . ($total <= 220 ? implode('، ', $c['names']) : count($c['names']) . ' دانش‌آموز (فهرست با list_students)')
            . ($c['subjects'] ? "\n  درس‌ها: " . $c['subjects'] : ''))->implode("\n") ?: '(کلاسی ثبت نشده)';
        $topics = rescue(fn () => \App\Models\DisciplineTopic::where('school_id', $this->user->school_id)->where('is_active', true)->pluck('name')->implode('، '), '', false) ?: '—';

        return <<<TXT
تو «ماهو» هستی؛ دستیارِ هوشمند، دقیق و مهربانِ {$this->user->name} (معلمِ دبستان) در پیام‌رسان. معلم می‌خواهد همه‌ی کارهای کلاس را از همین‌جا انجام دهد و به سایت نرود.
ساعتِ الان: {$now->format('H:i')}
{$this->daysBlock($days)}
کلاس‌ها و دانش‌آموزان (فقط همین‌ها وجود دارند):
{$clsTxt}
موضوع‌های انضباطیِ مدرسه: {$topics}

قواعد (خیلی مهم):
۱) هیچ عدد، نام، نمره یا وضعیتی را حدس نزن و از خودت نساز. برای هر سؤال درباره‌ی بچه‌ها، حضور، نمره‌ها، کارهای فرستاده‌شده، آزمون‌ها، بازی‌ها، امتیاز، انضباط، پیام‌ها یا برنامه، اول ابزارِ خواندنیِ مناسب را صدا بزن و فقط بر اساسِ خروجیِ آن جواب بده. اگر داده نبود، صادقانه بگو.
۲) هر تغییری (حضور و غیاب، نمره، امتیاز، انضباط، اطلاعیه/پیام، تصحیح و نمره‌دادن به کارهای فرستاده‌شده، ساختِ آزمون/بازی/مسابقه) فقط با ابزارِ «کار»ِ مربوط. برای «از سؤال‌های بازی/آزمون/مسابقه‌های قبلی … بساز» از build_from_items؛ برای انتشار، بستن یا حذفِ یک آزمون/بازی از open_item (دکمه‌ی تأیید دارد). سامانه پیش‌نمایش نشان می‌دهد و معلم تأیید می‌کند؛ پس هرگز نگو «انجام شد».
۳) دقیقاً همان کاری را بکن که معلم خواست؛ نه بیشتر، نه کمتر. فیلترهایش (امشب، دیروز، فقط روخوانی، به‌جز فلانی، فقط کسانی که فرستادند…) را دقیق در بخش‌های ابزار بگذار.
۴) نام‌ها را همیشه با املای دقیقِ فهرستِ بالا بنویس. {$this->voiceRule($voice)} اگر یک نام به دو دانش‌آموز می‌خورد، یا کلاس معلوم نیست و چند کلاس هست، کوتاه بپرس.
۵) جوابِ نهایی: فارسیِ روان، کوتاه و کاربردی (حداکثر حدودِ ۱۲ خط)، با فهرستِ نقطه‌دار (•) برای چند مورد. از Markdown (ستاره‌ی ** یا # یا جدول) استفاده نکن؛ پیام‌رسان آن را نشان نمی‌دهد. اگر کاری لازم است، یک پیشنهادِ عملیِ کوتاه بده (مثلاً «بگو تا برایشان … بفرستم»).
۶) «امشب» یعنی امروز از ساعتِ ۱۶ به بعد؛ «این هفته» یعنی ۷ روزِ اخیر. تاریخ‌ها را از جدولِ بالا بردار.
۷) اگر حرفِ معلم ربطی به کلاس ندارد (احوال‌پرسی، سؤالِ آموزشی، ایده برای تدریس)، کوتاه و گرم جواب بده؛ ولی هیچ داده‌ای درباره‌ی بچه‌ها از خودت نگو.
TXT;
    }

    private function daysBlock(array $days): string
    {
        return "تاریخ‌ها:\n" . implode("\n", $days);
    }

    private function voiceRule(bool $voice): string
    {
        return $voice
            ? 'این پیام از «صدا به متن» آمده و ممکن است کلمه یا نامی را اشتباه شنیده باشد (مثلاً «ظهرا» به‌جای «زهرا»، «سنا» به‌جای «ثنا»، یا کلمه‌ی جاافتاده)؛ منظورِ معلم را بفهم و نام را به نزدیک‌ترین نامِ فهرست برگردان.'
            : 'اگر نامی کمی غلط نوشته شده، به نزدیک‌ترین نامِ فهرست برگردان.';
    }

    /* ═════════════════════════ اجرا ═════════════════════════ */

    /**
     * @param  array<int,array{0:string,1:string}>  $history  [['u'|'a', متن], …]
     */
    public function think(string $text, array $history = [], bool $voice = false): array
    {
        $p = AiConfig::provider();
        $family = AiConfig::family($p);
        $key = AiConfig::key($p);
        if ($p === 'off' || ! $key) {
            return ['type' => 'error', 'text' => 'هوش مصنوعی برای سامانه فعال نیست.'];
        }
        \App\Support\AiUsage::$feature = 'bot';
        $data = new BotData($this->user, $this->bot);
        $sys = $this->system($voice);
        $msgs = $this->history($history);
        $msgs[] = ['role' => 'user', 'content' => ($voice ? '[پیامِ صوتی] ' : '') . $text];
        $started = microtime(true);

        try {
            for ($round = 0; $round < self::ROUNDS; $round++) {
                $last = $round === self::ROUNDS - 1 || microtime(true) - $started > 70;
                $r = $family === 'anthropic' ? $this->callAnthropic($key, $sys, $msgs, $last) : $this->callOpenAi($key, $p, $sys, $msgs, $last);
                if (! $r['ok']) {
                    // سرویسی که ابزار نمی‌فهمد → روشِ قدیمی
                    if ($round === 0 && $r['status'] === 400) {
                        return $this->legacy($text, $history, $voice);
                    }

                    return ['type' => 'error', 'text' => $r['error']];
                }
                if (! $r['calls']) {
                    return $this->final($r['text']);
                }
                // اولین «کار» → به بات برمی‌گردد (پیش‌نمایش و تأیید)
                foreach ($r['calls'] as $c) {
                    if (in_array($c['name'], self::ACTIONS, true)) {
                        $this->trace[] = $c['name'];

                        return ['type' => 'action', 'tool' => $c['name'], 'args' => $c['args'], 'text' => trim((string) $r['text'])];
                    }
                }
                $results = [];
                foreach ($r['calls'] as $c) {
                    $this->trace[] = $c['name'];
                    $out = $data->run($c['name'], $c['args']);
                    $results[] = ['id' => $c['id'], 'name' => $c['name'], 'content' => mb_substr(json_encode($out, JSON_UNESCAPED_UNICODE), 0, 9000)];
                }
                $this->append($family, $msgs, $r, $results);
            }
        } catch (\Throwable $e) {
            report($e);

            return ['type' => 'error', 'text' => 'به سرویسِ هوش مصنوعی وصل نشد؛ دوباره بگو.'];
        }

        return ['type' => 'error', 'text' => 'جوابی آماده نشد؛ کوتاه‌تر و دقیق‌تر بپرس.'];
    }

    /** متنِ نهایی؛ اگر مدل به‌جای ابزار JSONِ قدیمی نوشت، همان را «کار» حساب کن. */
    private function final(string $text): array
    {
        $t = trim($text);
        if (preg_match('/\{.*"tool"\s*:.*\}/su', $t, $m) && is_array($j = json_decode($m[0], true)) && isset($j['tool'])) {
            $conv = self::fromLegacy((string) $j['tool'], is_array($j['args'] ?? null) ? $j['args'] : []);
            if ($conv) {
                return $conv + ['text' => trim((string) ($j['reply'] ?? ''))];
            }

            return ['type' => 'answer', 'text' => trim((string) ($j['reply'] ?? '')) ?: '🙂'];
        }

        return ['type' => 'answer', 'text' => $t !== '' ? self::plain($t) : '🤔 منظورت را دقیق نفهمیدم؛ کمی بیشتر توضیح بده.'];
    }

    /** Markdown → متنِ ساده (بله و تلگرام بدونِ parse_mode ستاره‌ها را نشان می‌دهند). */
    public static function plain(string $t): string
    {
        $t = preg_replace('/\*\*(.+?)\*\*/su', '$1', $t) ?? $t;
        $t = preg_replace('/__(.+?)__/su', '$1', $t) ?? $t;
        $t = preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', $t) ?? $t;
        $t = preg_replace('/^\s*[-*]\s+/mu', '• ', $t) ?? $t;
        $t = preg_replace('/`([^`]+)`/u', '$1', $t) ?? $t;

        return trim($t);
    }

    /** ابزارهای روشِ قدیمی → ابزارهای تازه. */
    public static function fromLegacy(string $tool, array $a): ?array
    {
        return match ($tool) {
            'attendance' => ['type' => 'action', 'tool' => 'record_attendance', 'args' => $a],
            'grades' => ['type' => 'action', 'tool' => 'record_grades', 'args' => $a],
            'award' => ['type' => 'action', 'tool' => 'give_points', 'args' => $a],
            'discipline' => ['type' => 'action', 'tool' => 'record_discipline', 'args' => $a],
            'announce' => ['type' => 'action', 'tool' => 'send_announcement', 'args' => $a],
            'grade_work' => ['type' => 'action', 'tool' => 'grade_submissions', 'args' => $a],
            'game', 'exam', 'live' => ['type' => 'action', 'tool' => 'create_quiz', 'args' => ['kind' => $tool] + $a],
            'messages' => ['type' => 'action', 'tool' => 'open_screen', 'args' => ['screen' => 'messages']],
            'report' => ['type' => 'action', 'tool' => 'legacy_report', 'args' => $a],
            'student' => ['type' => 'action', 'tool' => 'legacy_student', 'args' => $a],
            default => null,
        };
    }

    private function legacy(string $text, array $history, bool $voice): array
    {
        $classes = $this->bot->classes($this->user);
        $ctx = $classes->map(fn ($c) => ['name' => $c->name, 'students' => $c->students()->orderBy('name')->pluck('name')->all(),
            'subjects' => collect(rescue(fn () => $c->subjectNames(), [], false))->pluck('name')->all()])->values()->all();
        $topics = rescue(fn () => \App\Models\DisciplineTopic::where('school_id', $this->user->school_id)->where('is_active', true)->get(['id', 'name', 'kind', 'points'])->toArray(), [], false);
        $d = BotAgent::decide($this->user, $ctx, $topics, $history, ($voice ? '[پیامِ صوتی؛ ممکن است نامی اشتباه شنیده شده باشد] ' : '') . $text);
        if (! $d['ok']) {
            return ['type' => 'error', 'text' => $d['reply']];
        }

        $conv = self::fromLegacy($d['tool'], $d['args']);

        return $conv ? $conv + ['text' => $d['reply']] : ['type' => 'answer', 'text' => $d['reply'] ?: '🙂'];
    }

    /** گفتگوی اخیر → پیام‌ها (نوبت‌ها یکی‌درمیان، با پیامِ کاربر شروع). */
    private function history(array $h): array
    {
        $out = [];
        foreach (array_slice($h, -8) as [$who, $t]) {
            $role = $who === 'u' ? 'user' : 'assistant';
            if ($out && end($out)['role'] === $role) {
                $out[count($out) - 1]['content'] .= "\n" . $t;
            } else {
                $out[] = ['role' => $role, 'content' => (string) $t];
            }
        }
        while ($out && $out[0]['role'] !== 'user') {
            array_shift($out);
        }
        if ($out && end($out)['role'] === 'user') {
            $out[] = ['role' => 'assistant', 'content' => '(باشه)'];
        }

        return $out;
    }

    /* ─────────────── OpenAI و سازگارها ─────────────── */

    private function callOpenAi(string $key, string $p, string $sys, array $msgs, bool $last): array
    {
        $body = ['model' => AiConfig::model($p), 'max_tokens' => 1200, 'temperature' => 0.2,
            'messages' => array_merge([['role' => 'system', 'content' => $sys]], $msgs),
            'tools' => self::schemas('openai'), 'tool_choice' => $last ? 'none' : 'auto'];
        $res = AiConfig::postChat($key, $body, 40, $p);
        if (! $res->successful()) {
            return ['ok' => false, 'status' => $res->status(), 'error' => \App\Support\AiChat::friendly($res->status(), (string) (data_get($res->json(), 'error.message') ?: $res->body()))];
        }
        $m = (array) data_get($res->json(), 'choices.0.message', []);
        $calls = [];
        foreach ((array) ($m['tool_calls'] ?? []) as $tc) {
            $args = json_decode((string) data_get($tc, 'function.arguments', '{}'), true);
            $calls[] = ['id' => (string) ($tc['id'] ?? uniqid('t')), 'name' => (string) data_get($tc, 'function.name', ''), 'args' => is_array($args) ? $args : []];
        }

        return ['ok' => true, 'text' => (string) ($m['content'] ?? ''), 'calls' => $calls, 'raw' => $m];
    }

    /* ─────────────── Claude ─────────────── */

    private function callAnthropic(string $key, string $sys, array $msgs, bool $last): array
    {
        $body = ['model' => AiConfig::model('anthropic'), 'max_tokens' => 1200, 'temperature' => 0.2, 'system' => $sys,
            'messages' => $msgs, 'tools' => self::schemas('anthropic')] + ($last ? ['tool_choice' => ['type' => 'none']] : []);
        $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'])
            ->timeout(40)->post((AiConfig::base('anthropic') ?: 'https://api.anthropic.com/v1') . '/messages', $body);
        if ($res->status() === 400 && str_contains(strtolower($res->body()), 'temperature')) {
            unset($body['temperature']);
            $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json'])
                ->timeout(40)->post((AiConfig::base('anthropic') ?: 'https://api.anthropic.com/v1') . '/messages', $body);
        }
        if (! $res->successful()) {
            return ['ok' => false, 'status' => $res->status(), 'error' => \App\Support\AiChat::friendly($res->status(), (string) (data_get($res->json(), 'error.message') ?: $res->body()))];
        }
        $blocks = (array) data_get($res->json(), 'content', []);
        $calls = [];
        $text = '';
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'tool_use') {
                $calls[] = ['id' => (string) $b['id'], 'name' => (string) $b['name'], 'args' => (array) ($b['input'] ?? [])];
            } elseif (($b['type'] ?? '') === 'text') {
                $text .= $b['text'];
            }
        }

        return ['ok' => true, 'text' => $text, 'calls' => $calls, 'raw' => $blocks];
    }

    /** افزودنِ نوبتِ مدل و نتیجه‌ی ابزارها به گفتگو. */
    private function append(string $family, array &$msgs, array $r, array $results): void
    {
        if ($family === 'anthropic') {
            $msgs[] = ['role' => 'assistant', 'content' => $r['raw']];
            $msgs[] = ['role' => 'user', 'content' => array_map(fn ($x) => ['type' => 'tool_result', 'tool_use_id' => $x['id'], 'content' => $x['content']], $results)];

            return;
        }
        $assistant = ['role' => 'assistant', 'content' => $r['raw']['content'] ?? null, 'tool_calls' => $r['raw']['tool_calls'] ?? []];
        $msgs[] = $assistant;
        foreach ($results as $x) {
            $msgs[] = ['role' => 'tool', 'tool_call_id' => $x['id'], 'content' => $x['content']];
        }
    }
}
