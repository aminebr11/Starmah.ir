<?php

namespace App\Services\Bot;

use App\Models\User;
use App\Support\AiChat;
use App\Support\AiConfig;
use App\Support\Jalali;
use Illuminate\Support\Collection;

/**
 * 🧠 مغزِ گفتگوییِ بات: حرفِ آزادِ معلم (نوشته یا صوتی) + چند پیامِ اخیر → یک «کار» با بخش‌هایش.
 *
 * هوش مصنوعی فقط تصمیم و متنِ پاسخ را می‌دهد؛ هیچ‌وقت مستقیم چیزی ثبت نمی‌کند. هر کارِ ثبت‌کننده
 * پیش‌نمایش و دکمه‌ی تأیید دارد، و نام‌ها دوباره با فهرستِ واقعیِ کلاس تطبیق داده می‌شوند.
 */
class BotAgent
{
    public const TOOLS = ['attendance', 'grades', 'award', 'discipline', 'announce', 'report', 'student', 'game', 'exam', 'live', 'grade_work', 'messages',
        'show_items', 'show_questions', 'build_from_items', 'open_item', 'open_screen', 'none'];

    public static function available(User $user): bool
    {
        return AiConfig::provider() !== 'off' && AiConfig::configured() && \App\Support\AssistantAccess::aiAllowed($user);
    }

    /**
     * @param  array<int,array{name:string,students:array<int,string>,subjects:array}>  $classes
     * @param  array<int,array{0:string,1:string}>  $history  [[نقش, متن], …]
     * @return array{ok:bool, reply:string, tool:string, args:array}
     */
    public static function decide(User $user, array $classes, array $topics, array $history, string $text, string $data = ''): array
    {
        $now = now();
        $days = [];
        for ($i = 0; $i <= 6; $i++) {
            $d = $now->copy()->subDays($i);
            $days[] = ($i === 0 ? 'امروز' : ($i === 1 ? 'دیروز' : ($i === 2 ? 'پریروز' : "{$i} روز پیش"))) . ' = ' . Jalali::format($d, true) . ' = ' . $d->toDateString();
        }
        $cls = collect($classes)->map(fn ($c) => "• {$c['name']}: " . implode('، ', array_slice($c['students'], 0, 45))
            . ($c['subjects'] ? "\n  درس‌ها: " . implode('، ', $c['subjects']) : ''))->implode("\n");
        $daysTxt = implode("\n", $days);
        $tp = $topics ? collect($topics)->map(fn ($t) => "{$t['name']} (" . ($t['kind'] === 'positive' ? 'تشویق' : 'تذکر') . ", {$t['points']})")->implode('، ') : '(موضوعی تعریف نشده)';

        $sys = <<<TXT
تو «ماهو»، دستیارِ هوشمند و مهربانِ معلمِ دبستان در سامانه‌ی «ستاره ماه» هستی و در پیام‌رسان با معلم گفتگو می‌کنی.
معلم: {$user->name}
ساعتِ الان: {$now->format('H:i')}
تاریخ‌ها (برای «دیروز»، «شنبه»، «۲۲ آبان» و…):
{$daysTxt}
کلاس‌ها و دانش‌آموزان (فقط همین نام‌ها وجود دارند؛ هرگز نامِ دیگری نساز):
{$cls}
موضوع‌های انضباطیِ مدرسه: {$tp}

فقط یک شیءِ JSON برگردان، بدونِ هیچ متنِ دیگری، با کلیدهای:
"reply": پاسخِ کوتاه، گرم و فارسی به معلم (حداکثر ۳ جمله؛ اگر کاری پیشنهاد می‌دهی بگو که پیش‌نمایشش را می‌بیند و با دکمه تأیید می‌کند)
"tool": یکی از attendance|grades|award|discipline|announce|report|student|game|exam|live|grade_work|messages|show_items|show_questions|build_from_items|open_item|open_screen|none
"args": بخش‌های همان کار:
- attendance: {"date":"YYYY-MM-DD (میلادی، از جدولِ تاریخ‌ها)","class":"نامِ کلاس","absent":[نام‌ها],"late":[نام‌ها],"excused":[نام‌ها]} — هر کس نامش نیامده حاضر است.
- grades: {"class","lesson","topic","type":"descriptive|numeric|homework","max":عدد,"grades":[{"name","value"}],"rest":"مقدار برای بقیه یا null"} — توصیفی فقط: خیلی خوب، خوب، قابل قبول، نیاز به تلاش. تکلیف: کامل، ناقص، انجام نداده.
- award: {"class","names":[نام‌ها] یا ["همه"],"amount":عدد (منفی یعنی کم کردن، حداکثر ۵۰),"reason":"دلیل"}
- discipline: {"names":[نام‌ها],"topic":"دقیقاً یکی از موضوع‌های انضباطیِ بالا","note":"توضیحِ کوتاه"}
- announce: {"to":"class یا نامِ یک دانش‌آموز","class","text":"متنِ پیام"}
- report: {"kind":"today|attendance|grades|risk|pending|xp"} — xp = امتیازهای بچه‌ها؛ pending = کارهای منتظرِ تصحیح
- grade_work: تصحیح/نمره دادن به کارهای فرستاده‌شده (روخوانی، املا، کاربرگ) — {"kind":"reading|dictation|worksheet|any","date":"YYYY-MM-DD یا null (امروز)","after_hour":عدد یا null («امشب» = 16),"days":عدد یا null («این هفته» = 7),"task":"بخشی از عنوانِ تکلیف یا null","names":[فقط این نام‌ها],"except":[به‌جز این نام‌ها],"regrade":false (true فقط اگر گفت تصحیح‌شده‌ها را هم عوض کن),"value":"خیلی خوب|خوب|قابل قبول|نیاز به تلاش|عالی یا عددِ نمره از ۲۰","feedback":"توضیح برای بچه‌ها یا null"} — مثال: «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن» → kind=reading, after_hour=16, value=خیلی خوب. پیش از ثبت، فهرستِ دقیقِ همان کارها نشان داده و تأیید گرفته می‌شود.
- live: مسابقه‌ی زنده (کوییزِ هم‌زمانِ کلاس) {"class","lesson","topic","count":۵ تا ۲۰,"difficulty"} — تنظیمِ زمان و قوانین با دکمه انجام می‌شود.
- messages: نشان‌دادنِ پیام‌ها و سؤال‌های تازه‌ی بچه‌ها و والدین (با دکمه‌ی پاسخ)
- show_items: فهرستِ آزمون/بازی/مسابقه‌ها {"type":"exam|game|live","date":"YYYY-MM-DD یا null","days":عدد یا null,"status":"published|draft|closed یا null"}
- show_questions: متنِ کاملِ سؤال‌ها {"type":"exam|game|live","titles":[...],"last":عدد («سه بازیِ آخر» = 3),"date","days","status","per_item":عدد («از هر کدام ۴ تا»),"count":عدد («مجموعاً ۲۰ تا»),"shuffle":true/false}
  مثال: «از بازی‌هایی که دیروز منتشر کردم از هر کدام ۴ سؤال بده، مجموعاً ۲۰ تا» → {"type":"game","date":"(دیروز)","status":"published","per_item":4,"count":20}
- build_from_items: ساختن از سؤال‌های قبلی {"kind":"exam|game|live","from_type":"exam|game|live","titles":[],"last_n":عدد,"date":"YYYY-MM-DD","days":عدد,"status":"published",
  "count":عدد,"per_item":عدد,"shuffle":true/false,"mode":"copy|similar","start":"HH:MM یا now یا board (فقط مسابقه)","seconds":عدد,"end_minutes":عدد,
  "from_shown":true اگر معلم گفت «با همین‌ها / با این سؤال‌ها» بعد از نشان‌دادنِ سؤال‌ها}
  مثال: «از بازی‌هایی که دیروز منتشر کردم یک مسابقه‌ی زنده بساز، امروز ساعت ۱۷» → {"kind":"live","from_type":"game","date":"(دیروز)","status":"published","start":"17:00"}
- open_item: کارتِ یک مورد برای انتشار/بستن/حذف/نتیجه {"type":"exam|game|live","title":"…"}
- open_screen: {"screen":"library|grading_queue|messages|students|question_bank|reports|settings|menu"}
- student: {"name":"نامِ دانش‌آموز","question":"سؤالِ معلم دربارهٔ او"}
- game: بازیِ آموزشی {"class","lesson","topic","count":۵ تا ۱۵,"difficulty":"easy|medium|hard|mixed"}
- exam: آزمون/امتحان/سؤال‌طراحی {"class","lesson","topic","count":۵ تا ۲۰,"difficulty":"easy|medium|hard|mixed"} — بات بعد از این، تنظیمات (نوعِ سؤال، تم، قوانین) و بازبینیِ سؤال‌ها را با دکمه نشان می‌دهد؛ در reply بگو سؤال‌ها پیش از انتشار قابلِ ویرایش‌اند.
- none: گفتگوی معمولی، سلام، راهنما، یا وقتی چیزی مبهم است (در reply کوتاه بپرس؛ مثلاً کدام کلاس یا کدام دانش‌آموز).
معلم می‌تواند عکس یا PDFِ برگه‌ی نمونه‌سؤال یا صفحه‌ی کتاب را هم بفرستد تا از رویش سؤال ساخته شود؛ اگر درباره‌اش پرسید، همین را بگو.
قواعد: اگر نامی دو دانش‌آموز را می‌رساند و از متن معلوم نیست، tool=none و بپرس. برای حضور و غیاب اگر تاریخ گفته نشد امروز است. اگر معلم «همه حاضرن» گفت، absent خالی است.
TXT;

        $conv = collect(array_slice($history, -8))->map(fn ($h) => ($h[0] === 'u' ? 'معلم: ' : 'ماهو: ') . $h[1])->implode("\n");
        $prompt = ($data !== '' ? "داده‌ی واقعیِ سامانه (فقط از همین بگو):\n{$data}\n\n" : '') . ($conv !== '' ? "گفتگوی اخیر:\n{$conv}\n\n" : '') . "پیامِ تازه‌ی معلم:\n{$text}";

        \App\Support\AiUsage::$feature = 'bot';
        $r = AiChat::send($sys, $prompt, 900, null, 40);
        if (! $r['ok']) {
            return ['ok' => false, 'reply' => (string) ($r['error'] ?? 'هوش مصنوعی پاسخ نداد.'), 'tool' => 'none', 'args' => []];
        }
        $j = preg_match('/\{.*\}/su', $r['text'], $m) ? json_decode($m[0], true) : null;
        if (! is_array($j)) {
            // پاسخِ متنیِ ساده (بدونِ JSON) هم پذیرفته می‌شود
            return ['ok' => true, 'reply' => trim(mb_substr($r['text'], 0, 900)), 'tool' => 'none', 'args' => []];
        }
        // نامِ ناشناخته هم نگه داشته می‌شود تا BotBrain مترادف‌ها را بفهمد (به‌جای «هیچ‌کار»)
        $tool = is_string($j['tool'] ?? null) && $j['tool'] !== '' ? $j['tool'] : 'none';

        return ['ok' => true, 'reply' => trim((string) ($j['reply'] ?? '')), 'tool' => $tool, 'args' => is_array($j['args'] ?? null) ? $j['args'] : []];
    }

    /** پاسخِ طبیعی به سؤالِ معلم دربارهٔ یک دانش‌آموز، فقط بر اساسِ داده‌های واقعیِ سامانه. */
    public static function answerAboutStudent(string $question, string $facts): ?string
    {
        \App\Support\AiUsage::$feature = 'bot';
        $r = AiChat::send(
            'تو دستیارِ معلمِ دبستان هستی. فقط بر اساسِ داده‌های زیر و کوتاه (حداکثر ۶ خط)، دقیق و مهربان به سؤالِ معلم جواب بده و در پایان یک پیشنهادِ عملیِ کوتاه بده. اگر داده کافی نیست، صادقانه بگو.',
            "داده‌های سامانه:\n{$facts}\n\nسؤالِ معلم: {$question}", 600, null, 35);

        return $r['ok'] ? trim($r['text']) : null;
    }
}
