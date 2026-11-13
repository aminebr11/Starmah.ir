<?php

namespace App\Services\Bot;

use App\Models\BotLog;
use App\Models\Classroom;
use App\Models\User;
use App\Services\SmartExamAiService;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use App\Support\Bot\BotFile;
use App\Support\Bot\BotParser as P;
use App\Support\Jalali;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 🧪 استودیوی سؤال در بات — آزمون و بازی از روی «موضوع» یا «فایل».
 *
 * معلم عکسِ برگه‌ی نمونه‌سؤال / کاربرگ / صفحه‌ی کتاب (یا PDF) را می‌فرستد و می‌گوید:
 *   📋 دقیقاً همین سؤال‌ها · 🔁 سؤال‌های مشابه · 📖 از روی متنِ این صفحه سؤال بساز
 * بعد کلاس، آزمون یا بازی، تعداد، دشواری، نوعِ سؤال و فضای داستانی را انتخاب می‌کند. هیچ سؤالی
 * پیش از بازبینی منتشر نمی‌شود: هر سؤال را می‌شود دید، ✏️ ویرایش، 🔄 جایگزین یا 🗑 حذف کرد و
 * گزینه‌ی درست را با یک دکمه عوض کرد. ⚙️ قوانین (مدت، تلاش، قبولی، نمایشِ پاسخ…) و 🎨 تم/قالبِ
 * بازی هم انتخاب می‌شود و در پایان با همان کنترلرهای سایت ساخته و (به انتخاب) منتشر می‌شود.
 *
 * دکمه‌ها همه با «q:» شروع می‌شوند و وضعیت در Cache (۳ ساعت) نگه داشته می‌شود.
 */
class BotQuiz
{
    public const MAX_PAGES = 6;

    public const TYPE_FA = ['mc' => 'چهارگزینه‌ای', 'tf' => 'درست/نادرست', 'blank' => 'جای خالی', 'desc' => 'تشریحی'];

    private const DIFF_FA = ['easy' => 'آسان', 'medium' => 'متوسط', 'hard' => 'دشوار', 'mixed' => 'ترکیبی'];

    private const MODE_FA = ['same' => '📋 دقیقاً همین سؤال‌ها', 'similar' => '🔁 سؤال‌های مشابهِ برگه', 'content' => '📖 سؤال از روی متنِ صفحه', 'topic' => '✍️ از روی موضوع'];

    private const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];

    public const EXAM_RULES = ['duration' => 20, 'attempts' => 1, 'pass' => 50, 'show_answer' => true, 'show_result' => true, 'shuffle' => false, 'shuffle_choices' => false, 'one_per_page' => true];

    public const GAME_RULES = ['lives' => 3, 'retry' => true, 'show_answer' => true, 'shuffle' => false, 'pass' => 50, 'group_race' => false];

    /** مسابقه‌ی زنده: ثانیه‌ی هر سؤال، شروع («board» = روی تخته‌ی کلاس، عدد = چند دقیقه‌ی دیگر خودکار، «HH:MM»)، پایان (دقیقه پس از شروع؛ ۰ = بعد از آخرین سؤال) */
    public const LIVE_RULES = ['seconds' => 20, 'start' => 'board', 'end' => 0];

    private const CYCLES = ['duration' => [10, 15, 20, 30, 45, 60], 'attempts' => [1, 2, 3], 'pass' => [40, 50, 60, 70, 80], 'lives' => [1, 2, 3, 4, 5],
        'seconds' => [10, 15, 20, 30, 45, 60], 'start' => ['board', 0, 5, 15, 30, 60, 120], 'end' => [0, 10, 20, 30, 60]];

    public const KIND_FA = ['exam' => '📝 آزمون', 'game' => '🎮 بازی', 'live' => '🏆 مسابقه‌ی زنده'];

    private const RULE_FA = [
        'duration' => '⏱ مدت', 'attempts' => '🔁 دفعاتِ مجاز', 'pass' => '🎯 حدِ قبولی', 'lives' => '❤️ جان',
        'show_answer' => 'نمایشِ پاسخِ درست', 'show_result' => 'نمایشِ نتیجه به دانش‌آموز', 'shuffle' => 'ترتیبِ تصادفیِ سؤال‌ها',
        'shuffle_choices' => 'جابه‌جاییِ گزینه‌ها', 'one_per_page' => 'یک سؤال در هر صفحه', 'retry' => 'امکانِ تلاشِ دوباره', 'group_race' => '🏆 رقابتِ گروهی',
        'seconds' => '⏱ زمانِ هر سؤال', 'start' => '🚀 شروع', 'end' => '⏳ پایان',
    ];

    public function __construct(private TeacherBot $bot, private BotApi $api, private string $platform, private string $chat) {}

    /* ═════════════════════════ ورودی‌ها ═════════════════════════ */

    /** پیامِ عکس یا فایل (PDF/عکس). */
    public function onFile(User $user, array $msg): void
    {
        $f = null;
        if (! empty($msg['photo']) && is_array($msg['photo'])) {
            $p = collect($msg['photo'])->filter(fn ($x) => is_array($x) && ! empty($x['file_id']))
                ->sortBy(fn ($x) => ($x['width'] ?? 0) * ($x['height'] ?? 0) ?: ($x['file_size'] ?? 0))->last();
            $f = $p ? ['fid' => $p['file_id'], 'mime' => 'image/jpeg'] : null;
        } elseif (! empty($msg['document']['file_id'])) {
            $mime = strtolower((string) ($msg['document']['mime_type'] ?? ''));
            $name = (string) ($msg['document']['file_name'] ?? '');
            if ($mime === 'application/pdf' || str_ends_with(strtolower($name), '.pdf')) {
                $mime = 'application/pdf';
            } elseif (! str_starts_with($mime, 'image/')) {
                $this->api->send($this->chat, '📎 فقط عکس (JPG/PNG) یا PDF را می‌توانم بخوانم.');

                return;
            }
            $f = ['fid' => $msg['document']['file_id'], 'mime' => $mime];
        }
        if (! $f) {
            return;
        }
        // در انتظارِ «عکسِ شکل» برای یک سؤال؟
        $cur = $this->get();
        if ($cur && ($cur['wait']['what'] ?? '') === 'fig' && isset($cur['qs'][$cur['wait']['i']])) {
            $this->figurePhoto($user, $cur, (int) $cur['wait']['i'], $f);

            return;
        }
        if (! BotAgent::available($user)) {
            $this->api->send($this->chat, '🤖 ساختِ سؤال از روی فایل به هوش مصنوعیِ سایت نیاز دارد که برای مدرسه‌ی شما روشن نیست.', $this->bot->mainRows());

            return;
        }
        $caption = trim((string) ($msg['caption'] ?? ''));
        $group = (string) ($msg['media_group_id'] ?? '');

        $added = $this->locked(function () use ($f, $caption) {
            $s = $this->get();
            // طراحیِ قبلی که سؤال دارد کنار گذاشته می‌شود (قابلِ برگشت)
            if (! $s || ! empty($s['qs']) || ($s['mode'] ?? '') === 'topic') {
                if ($s && ! empty($s['qs'])) {
                    Cache::put($this->key('quiz_prev'), $s, now()->addHours(3));
                }
                $s = $this->fresh();
            }
            if (count($s['src']) >= self::MAX_PAGES) {
                return -1;
            }
            $s['src'][] = $f;
            if ($caption !== '') {
                $this->applyCaption($s, $caption);
            }
            $this->save($s);

            return count($s['src']);
        });
        if ($added === -1) {
            $this->api->send($this->chat, '📎 حداکثر ' . Jalali::fa((string) self::MAX_PAGES) . ' صفحه را می‌توانم یک‌جا بخوانم؛ بقیه کنار گذاشته شد.');

            return;
        }
        // چند عکسِ یک «آلبوم» جدا جدا می‌رسند: فقط یک بار جواب می‌دهیم
        if ($group !== '' && ! Cache::add($this->key('mg:' . $group), 1, 300)) {
            return;
        }
        $this->next($user);
    }

    /** شروع از روی موضوع (از گفتگو یا دکمه). */
    public function start(User $user, array $p): void
    {
        $s = $this->fresh();
        $s['mode'] = 'topic';
        $s['kind'] = in_array($p['kind'] ?? null, ['exam', 'game', 'live'], true) ? $p['kind'] : null;
        if ($s['kind'] === 'exam' && ! \App\Support\SmartLab::enabledFor($user)) {
            $s['kind'] = null;   // آزمایشگاهِ آزمون خاموش است → فقط بازی پیشنهاد می‌شود
        }
        $s['rules'] = self::rulesFor($s['kind']);
        self::liveOpts($s, $p);
        $s['topic'] = trim((string) ($p['topic'] ?? ''));
        $s['lesson'] = trim((string) ($p['lesson'] ?? ''));
        if (! empty($p['count'])) {
            $s['count'] = max(3, min(20, (int) $p['count']));
        }
        if (in_array($p['difficulty'] ?? null, ['easy', 'medium', 'hard', 'mixed'], true)) {
            $s['diff'] = $p['difficulty'];
        }
        if (! empty($p['class'])) {
            $s['class'] = (int) $p['class'];
        }
        if ($s['topic'] === '' && $s['lesson'] === '') {
            $s['wait'] = ['what' => 'topic'];
            $this->save($s);
            $this->api->send($this->chat, (self::KIND_FA[$s['kind']] ?? '🧪 ساختِ سؤال') . "\n\n"
                . "✍️ موضوع را بنویس؛ مثلاً «ریاضی — کسرها» یا «علوم درسِ ۳: آب و هوا».\n📎 یا عکس / PDFِ برگه یا صفحه‌ی کتاب را بفرست.\n🗄 یا از بانکِ سؤال انتخاب کن.",
                [[['text' => '🗄 انتخاب از بانکِ سؤال', 'data' => 'q:bank']], [['text' => '❌ لغو', 'data' => 'q:x']]]);

            return;
        }
        $s['ready'] = ! empty($p['ready']);
        $this->save($s);
        $this->next($user);
    }

    /**
     * 📚 ساختن از سؤال‌های موجود (آزمون/بازی/مسابقه‌ی قبلی یا بانک).
     * $mode: copy = همان سؤال‌ها (تکراری‌ها حذف، به اندازه‌ی count، با ترتیبِ تصادفی اگر shuffle)
     *        similar = هوش مصنوعی سؤال‌های تازه‌ی هم‌سطح و هم‌موضوع می‌سازد (سؤال‌های قبلی تکرار نمی‌شوند)
     */
    public function seed(User $user, string $kind, array $questions, array $opt = []): void
    {
        $s = $this->fresh();
        $s['kind'] = in_array($kind, ['exam', 'game', 'live'], true) ? $kind : 'exam';
        if ($s['kind'] === 'exam' && ! \App\Support\SmartLab::enabledFor($user)) {
            $s['kind'] = 'game';
        }
        $s['rules'] = self::rulesFor($s['kind']);
        self::liveOpts($s, $opt);
        $s['types'] = $this->allowedTypes($s) === ['mc', 'tf'] ? ['mc', 'tf'] : $s['types'];
        if (! empty($opt['class'])) {
            $s['class'] = (int) $opt['class'];
        }
        $allowed = $this->allowedTypes($s);
        $ok = array_values(array_filter($questions, fn ($q) => in_array($q['type'] ?? '', $allowed, true)
            && (! in_array($q['type'], ['mc', 'tf'], true) || collect($q['choices'] ?? [])->contains('correct', true))));
        $dropped = count($questions) - count($ok);
        if (! empty($opt['shuffle'])) {
            shuffle($ok);
        }
        $count = (int) ($opt['count'] ?? 0);
        if ($count > 0) {
            $ok = array_slice($ok, 0, $count);
        }
        $ok = array_slice($ok, 0, $this->maxCount($s) * ($s['kind'] === 'live' ? 1 : 2));
        $topics = collect($questions)->pluck('topic')->filter()->countBy()->sortDesc()->keys()->take(3)->implode('، ');
        $s['topic'] = $topics;
        $s['title'] = Str::limit((string) ($opt['title'] ?? '') ?: (self::KIND_FA[$s['kind']] . ($topics ? ' — ' . $topics : ' از سؤال‌های قبلی')), 100, '');
        $note = '📚 ' . Jalali::fa((string) count($ok)) . ' سؤال از ' . Str::limit(implode('، ', (array) ($opt['from'] ?? [])), 300) . ' آمد.'
            . ($dropped ? ' (' . Jalali::fa((string) $dropped) . ' سؤال برای ' . self::KIND_FA[$s['kind']] . ' مناسب نبود: ' . ($s['kind'] === 'live' ? 'مسابقه فقط گزینه‌ای است' : 'بازی تشریحی ندارد') . ')' : '');

        if (($opt['mode'] ?? 'copy') === 'similar') {
            // سؤالِ تازه با هوش مصنوعی، هم‌موضوع با سؤال‌های قبلی و بدونِ تکرارِ آن‌ها
            $s['mode'] = 'topic';
            $s['count'] = max(3, min($this->maxCount($s), $count ?: count($ok) ?: 8));
            $s['ins'] = Str::limit('سؤال‌هایی هم‌سطح و هم‌سبک با این نمونه‌ها ولی تازه: ' . collect($questions)->take(4)->map(fn ($q) => '«' . Str::limit($q['prompt'], 80) . '»')->implode(' '), 480, '');
            $s['ready'] = true;
            $s['src'] = [];
            $this->save($s + ['avoid' => array_column($questions, 'prompt')]);
            $this->api->send($this->chat, $note . "\n🤖 حالا سؤال‌های تازه‌ی مشابه می‌سازم…");
            $this->next($user);

            return;
        }
        if (! $ok) {
            $this->api->send($this->chat, '⚠️ سؤالِ مناسبی برای ' . self::KIND_FA[$s['kind']] . ' پیدا نشد.' . ($dropped ? ' ' . $note : ''), $this->bot->mainRows());

            return;
        }
        $s['mode'] = 'bank';
        $s['qs'] = array_map(fn ($q) => array_intersect_key($q, array_flip(['type', 'prompt', 'choices', 'answer', 'explanation', 'hint', 'difficulty', 'bloom', 'topic', 'media_url', 'bank_id'])) + ['hint' => null], $ok);
        $s['ready'] = true;
        $this->save($s);
        $this->api->send($this->chat, $note . ($s['kind'] === 'live' ? "\n🗓 " . self::liveLine($s['rules']) : '')
            . "\nپیش از ساخت، سؤال‌ها را ببین؛ می‌توانی ویرایش، جایگزین یا حذف کنی و قوانین را تنظیم کنی.");
        $this->next($user);
    }

    /** متنی که در انتظارش بودیم (ویرایشِ سؤال، عنوان، موضوع، توضیح). true یعنی مصرف شد. */
    public function onText(User $user, string $text): bool
    {
        $s = $this->get();
        if (! $s || empty($s['wait'])) {
            return false;
        }
        $w = $s['wait'];
        $s['wait'] = null;
        // انتظارِ کهنه (بیش از ۱۰ دقیقه): این متن دستورِ تازه است، نه جوابِ آن سؤال
        if (($w['at'] ?? now()->timestamp) < now()->timestamp - 600) {
            $this->save($s);

            return false;
        }
        if (preg_match('/^\/?(لغو|cancel|منو|menu)$/u', P::norm($text))) {
            $this->save($s);
            $this->api->send($this->chat, '👌 باشد، تغییری ندادم.');
            $this->show($user, $s);

            return true;
        }
        switch ($w['what']) {
            case 'edit':
                $i = (int) $w['i'];
                if (! isset($s['qs'][$i])) {
                    break;
                }
                $r = self::parseEdit($text, $s['qs'][$i]);
                if (is_string($r)) {
                    $s['wait'] = $w;
                    $this->save($s);
                    $this->api->send($this->chat, '⚠️ ' . $r . "\nدوباره بفرست یا بنویس «لغو».");

                    return true;
                }
                $s['qs'][$i] = $r;
                $this->save($s);
                $this->api->send($this->chat, '✅ سؤالِ ' . Jalali::fa((string) ($i + 1)) . ' به‌روز شد.');
                $this->view($user, $s, $i);

                return true;
            case 'title':
                $s['title'] = Str::limit(trim($text), 110, '');
                $this->save($s);
                $this->review($user, $s);

                return true;
            case 'ins':
                $s['ins'] = Str::limit(trim($text), 400, '');
                $this->save($s);
                $this->setup($user, $s);

                return true;
            case 'ltime':
                $t = self::parseTime($text);
                if (! $t) {
                    $s['wait'] = $w;
                    $this->save($s);
                    $this->api->send($this->chat, '⚠️ ساعت را نفهمیدم؛ مثلاً «۲۰:۳۰» یا «۸ شب» بنویس، یا «لغو».');

                    return true;
                }
                $s['rules']['start'] = $t;
                $this->save($s);
                $this->api->send($this->chat, '🕗 مسابقه ' . self::ruleValue('start', $t) . ' خودش شروع می‌شود.');
                $this->rules($s, 0);

                return true;
            case 'bq':
                $b = $this->bankState($s);
                $b['q'] = in_array(P::norm($text), ['همه', 'all'], true) ? '' : Str::limit(trim($text), 60, '');
                $b['page'] = 0;
                $s['bank'] = $b;
                $this->save($s);
                $this->bankPicker($user, $s);

                return true;
            case 'topic':
                $s['topic'] = Str::limit(trim($text), 150, '');
                $this->save($s);
                $this->next($user);

                return true;
        }
        $this->save($s);

        return false;
    }

    /** دکمه‌های «q:…». */
    public function onButton(User $user, string $data, int $msgId): void
    {
        $a = explode(':', $data);
        $act = $a[1] ?? '';
        $x = $a[2] ?? '';
        $y = $a[3] ?? '';
        if ($act === 'home') {
            $this->home($msgId);

            return;
        }
        if ($act === 'new') {
            $this->start($user, ['kind' => $x]);

            return;
        }
        if ($act === 'bank' && ! $this->get()) {
            $f = $this->fresh();
            $f['mode'] = 'bank';
            $this->save($f);
            $this->next($user, $msgId);

            return;
        }
        if (in_array($act, ['lvr', 'lvs', 'lve', 'lvx'], true)) {
            $this->liveControl($user, $act, (int) $x, $msgId);

            return;
        }
        if ($act === 'prev') {
            if ($old = Cache::pull($this->key('quiz_prev'))) {
                $this->save($old);
                $this->show($user, $old, $msgId);
            }

            return;
        }
        $s = $this->get();
        if (! $s) {
            $this->api->edit($this->chat, $msgId, '⌛ این طراحی منقضی شده؛ دوباره فایل یا موضوع را بفرست.', $this->bot->mainRows());

            return;
        }
        $n = count($s['qs']);
        switch ($act) {
            case 'x':
                $this->forget();
                $this->api->edit($this->chat, $msgId, '❌ طراحی لغو شد؛ چیزی ساخته نشد.', $this->bot->mainRows());

                return;
            case 'mode':
                if (! in_array($x, ['same', 'similar', 'content'], true)) {
                    return;
                }
                $s['mode'] = $x;
                break;
            case 'cls':
                if ($this->bot->classes($user)->firstWhere('id', (int) $x)) {
                    $s['class'] = (int) $x;
                }
                break;
            case 'kind':
                if (in_array($x, ['exam', 'game', 'live'], true)) {
                    $s['kind'] = $x;
                    $s['types'] = array_values(array_intersect($s['types'], $this->allowedTypes($s))) ?: ['mc'];
                    $s['rules'] = self::rulesFor($x);
                }
                break;
            case 'cnt':
                $s['count'] = max(3, min($this->maxCount($s), $s['count'] + ($x === '-' ? -1 : ($x === '+' ? 1 : 0)) * ($s['count'] >= 10 ? 5 : 1)));
                $this->save($s);
                $this->setup($user, $s, $msgId);

                return;
            case 'dif':
                if (isset(self::DIFF_FA[$x])) {
                    $s['diff'] = $x;
                }
                $this->save($s);
                $this->setup($user, $s, $msgId);

                return;
            case 'typ':
                if (in_array($x, $this->allowedTypes($s), true)) {
                    $t = in_array($x, $s['types'], true) ? array_values(array_diff($s['types'], [$x])) : [...$s['types'], $x];
                    $s['types'] = $t ?: [$x];
                }
                $this->save($s);
                $this->setup($user, $s, $msgId);

                return;
            case 'ins':
                $s['wait'] = ['what' => 'ins'];
                $this->save($s);
                $this->api->send($this->chat, '✏️ توضیحت برای هوش مصنوعی را بنویس؛ مثلاً «عددها کوچک‌تر از ۱۰۰ باشند» یا «از مثال‌های خرید از بازار استفاده کن».');

                return;
            case 'thm':   // فهرستِ تم‌ها
                $this->themes($s, $msgId, $x === 'r');

                return;
            case 'th':
                $t = (int) $x > 0 ? \App\Models\Theme::where('is_active', true)->find((int) $x) : null;
                $s['theme_id'] = $t?->id;
                $s['flavor'] = $t?->name ?? '';
                $this->save($s);
                if ($y === 'r') {
                    $this->review($user, $s, $msgId, $t && ($s['kind'] ?? '') === 'exam' && $n
                        ? '🎨 فضای «' . $t->name . '» برای سؤال‌های تازه و جایگزین به کار می‌رود.' : null);
                } else {
                    $this->setup($user, $s, $msgId);
                }

                return;
            case 'tpls':
                $this->templates($s, $msgId);

                return;
            case 'tp':
                if (\App\Models\GameTemplate::where('key', $x)->where('is_active', true)->exists()) {
                    $s['tpl'] = $x;
                }
                $this->save($s);
                $this->review($user, $s, $msgId);

                return;
            case 'bank':
                if (! $s['qs'] && ! $s['src']) {
                    $s['mode'] = 'bank';
                }
                $s['bank'] = $this->bankState($s, reset: true);
                $this->save($s);
                if (empty($s['kind']) || empty($s['class'])) {
                    $this->next($user, $msgId);
                } else {
                    $this->bankPicker($user, $s, $msgId);
                }

                return;
            case 'bp':   // انتخاب/لغوِ یک سؤالِ بانک
                $b = $this->bankState($s);
                $id = (int) $x;
                $b['pick'] = in_array($id, $b['pick'], true) ? array_values(array_diff($b['pick'], [$id])) : [...$b['pick'], $id];
                $s['bank'] = $b;
                $this->save($s);
                $this->bankPicker($user, $s, $msgId);

                return;
            case 'bg':   // صفحه‌ی بعد/قبل (0 = همین صفحه)
                $b = $this->bankState($s);
                $b['page'] = max(0, $b['page'] + ($x === '-' ? -1 : ($x === '+' ? 1 : 0)));
                $s['bank'] = $b;
                $this->save($s);
                $this->bankPicker($user, $s, $msgId);

                return;
            case 'bgr':  // فقط همین پایه / همه‌ی پایه‌ها
                $b = $this->bankState($s);
                $b['all'] = ! $b['all'];
                $b['page'] = 0;
                $s['bank'] = $b;
                $this->save($s);
                $this->bankPicker($user, $s, $msgId);

                return;
            case 'bs':   // فهرستِ درس‌ها
                $this->bankSubjects($user, $s, $msgId);

                return;
            case 'bsub':
                $b = $this->bankState($s);
                $subs = $this->bankSubjectList($user, $s);
                $b['subj'] = $x === 'all' ? '' : (string) ($subs[(int) $x] ?? '');
                $b['page'] = 0;
                $s['bank'] = $b;
                $this->save($s);
                $this->bankPicker($user, $s, $msgId);

                return;
            case 'bq':
                $s['wait'] = ['what' => 'bq'];
                $this->save($s);
                $this->api->send($this->chat, '🔎 کلمه‌ای از متنِ سؤال را بنویس (مثلاً «کسر» یا «گیاه»). برای پاک کردنِ جست‌وجو بنویس «همه».');

                return;
            case 'badd':
                $this->bankAdd($user, $s, $msgId);

                return;
            case 'go':
                $s['ready'] = true;
                $s['qs'] = [];
                break;
            case 'l':
                $this->review($user, $s, $msgId);

                return;
            case 'v':
                $this->view($user, $s, max(0, min($n - 1, (int) $x)), $msgId);

                return;
            case 'k':   // گزینه‌ی درست
                $i = (int) $x;
                if (isset($s['qs'][$i]['choices'][(int) $y])) {
                    foreach ($s['qs'][$i]['choices'] as $ci => &$c) {
                        $c['correct'] = $ci === (int) $y;
                    }
                    unset($c);
                    if ($s['qs'][$i]['type'] === 'tf') {
                        $s['qs'][$i]['answer'] = $s['qs'][$i]['choices'][(int) $y]['value'];
                    }
                    $this->save($s);
                }
                $this->view($user, $s, $i, $msgId);

                return;
            case 'd':
                $i = (int) $x;
                if (isset($s['qs'][$i])) {
                    array_splice($s['qs'], $i, 1);
                    $this->save($s);
                }
                if (! $s['qs']) {
                    $s['ready'] = false;
                    $this->save($s);
                    $this->next($user, $msgId);
                } else {
                    $this->review($user, $s, $msgId, '🗑 سؤالِ ' . Jalali::fa((string) ($i + 1)) . ' حذف شد.');
                }

                return;
            case 'fs':   // دیدنِ شکل
                if (! empty($s['qs'][(int) $x]['media_url'])) {
                    $this->sendFigure($s['qs'][(int) $x]['media_url'], 'شکلِ سؤالِ ' . Jalali::fa((string) ((int) $x + 1)));
                }

                return;
            case 'fd':   // حذفِ شکل
                if (isset($s['qs'][(int) $x])) {
                    unset($s['qs'][(int) $x]['media_url'], $s['qs'][(int) $x]['figure']);
                    $this->save($s);
                }
                $this->view($user, $s, (int) $x, $msgId);

                return;
            case 'fp':   // منتظرِ عکسِ شکل
                if (isset($s['qs'][(int) $x])) {
                    $s['wait'] = ['what' => 'fig', 'i' => (int) $x];
                    $this->save($s);
                    $this->api->send($this->chat, '📷 عکسِ شکلِ سؤالِ ' . Jalali::fa((string) ((int) $x + 1)) . ' را بفرست (فقط همان بخشِ شکل). برای انصراف بنویس «لغو».');
                }

                return;
            case 'fa':   // ساختِ شکل با هوش مصنوعی
                if (isset($s['qs'][(int) $x])) {
                    $this->figureAi($user, $s, (int) $x, $msgId);
                }

                return;
            case 'e':
                $i = (int) $x;
                if (! isset($s['qs'][$i])) {
                    return;
                }
                $s['wait'] = ['what' => 'edit', 'i' => $i];
                $this->save($s);
                $this->api->send($this->chat, "✏️ ویرایشِ سؤالِ " . Jalali::fa((string) ($i + 1)) . "\nمتنِ پیامِ بعدی را کپی کن، تغییر بده و بفرست:\n"
                    . "• گزینه‌ی درست را با ✅ مشخص کن.\n• اگر فقط یک خط بفرستی، فقط متنِ سؤال عوض می‌شود.\n• برای انصراف بنویس «لغو».");
                $this->api->send($this->chat, self::editable($s['qs'][$i]));

                return;
            case 'r':
                $i = (int) $x;
                if (isset($s['qs'][$i])) {
                    $this->replace($user, $s, $i, $msgId);
                }

                return;
            case 'more':
                $this->generate($user, $s, $msgId, 5);

                return;
            case 'ltime':
                $s['wait'] = ['what' => 'ltime'];
                $this->save($s);
                $this->api->send($this->chat, '🕗 ساعتِ شروع را بنویس؛ مثلاً «۲۰:۳۰» یا «۸ شب» (اگر گذشته باشد، فردا).');

                return;
            case 'ttl':
                $s['wait'] = ['what' => 'title'];
                $this->save($s);
                $this->api->send($this->chat, '🏷️ عنوانِ تازه را بنویس:');

                return;
            case 'rul':
                $this->rules($s, $msgId);

                return;
            case 'ru':
                $this->toggleRule($s, $x);
                $this->save($s);
                $this->rules($s, $msgId);

                return;
            case 'pub':
                $this->publish($user, $s, $x === '1', $msgId);

                return;
        }
        $this->save($s);
        $this->next($user, $msgId);
    }

    /* ═════════════════════════ گام‌ها ═════════════════════════ */

    /** صفحه‌ی آغاز از منوی بات. */
    public function home(int $msgId = 0): void
    {
        $t = "🧪 طراحیِ سؤال، آزمون و بازی\n\n"
            . "📎 عکسِ برگه‌ی نمونه‌سؤال، کاربرگ یا صفحه‌ی کتاب را (یا فایلِ PDF) همین‌جا بفرست؛ می‌پرسم دقیقاً همان سؤال‌ها را بنویسم، سؤالِ مشابه بسازم یا از متنِ صفحه سؤال دربیاورم.\n\n"
            . "✍️ یا موضوع را بنویس؛ مثلاً «یک آزمونِ ۱۰ سؤالی از کسرها» یا «بازیِ ۸ سؤالی علوم درسِ آب».\n\n"
            . 'پیش از انتشار همه‌ی سؤال‌ها را می‌بینی و می‌توانی ویرایش، جایگزین یا حذف کنی؛ قوانین و تم را هم خودت انتخاب می‌کنی.';
        $rows = [[['text' => '📝 آزمون از یک موضوع', 'data' => 'q:new:exam'], ['text' => '🎮 بازی از یک موضوع', 'data' => 'q:new:game']], [['text' => '🏠 منو', 'data' => 'm:main']]];
        $msgId ? $this->api->edit($this->chat, $msgId, $t, $rows) : $this->api->send($this->chat, $t, $rows);
    }

    /** گامِ بعدیِ لازم را نشان می‌دهد. */
    private function next(User $user, int $msgId = 0): void
    {
        $s = $this->get();
        if (! $s) {
            return;
        }
        $classes = $this->bot->classes($user);
        if ($classes->isEmpty()) {
            $this->api->send($this->chat, '🏫 هنوز کلاسی به نامِ تو ثبت نشده.');

            return;
        }
        if (empty($s['kind'])) {
            $exam = \App\Support\SmartLab::enabledFor($user);
            $rows = [array_values(array_filter([
                $exam ? ['text' => '📝 آزمون', 'data' => 'q:kind:exam'] : null,
                ['text' => '🎮 بازی', 'data' => 'q:kind:game'],
            ])), [['text' => '🏆 مسابقه‌ی زنده', 'data' => 'q:kind:live']]];
            $rows[] = [['text' => '❌ لغو', 'data' => 'q:x']];
            $got = $s['src'] ? '📎 ' . ($s['src'][0]['mime'] === 'application/pdf' ? 'فایل' : 'برگه') . ' رسید (' . Jalali::fa((string) count($s['src'])) . " صفحه).\n\n" : '';
            $this->say($msgId, $got . ($exam ? 'از آن «آزمون» بسازم، «بازی» یا «مسابقه‌ی زنده»؟' : 'از آن «بازی» بسازم یا «مسابقه‌ی زنده»؟ (آزمایشگاهِ آزمون برای مدرسه روشن نیست.)'), $rows);

            return;
        }
        if ($s['src'] && empty($s['mode'])) {
            $this->say($msgId, self::KIND_FA[$s['kind']] . ' از روی ' . Jalali::fa((string) count($s['src'])) . " صفحه — با برگه چه کنم؟\n\n"
                . "📋 «دقیقاً همین سؤال‌ها»: سؤال‌های برگه بی‌کم‌وکاست تایپ و واردِ سامانه می‌شوند.\n"
                . "🔁 «مشابه»: همان سبک و سطح، با عدد و مثالِ تازه.\n"
                . "📖 «از روی متن»: از مطالبِ همین صفحه‌ی کتاب سؤال می‌سازم.\n\n"
                . 'اگر صفحه‌ی دیگری هم داری، همین حالا بفرست (حداکثر ' . Jalali::fa((string) self::MAX_PAGES) . ' صفحه).', [
                    [['text' => '📋 دقیقاً همین سؤال‌ها', 'data' => 'q:mode:same']],
                    [['text' => '🔁 سؤال‌های مشابه', 'data' => 'q:mode:similar']],
                    [['text' => '📖 از روی متن سؤال بساز', 'data' => 'q:mode:content']],
                    [['text' => '❌ لغو', 'data' => 'q:x']],
                ]);

            return;
        }
        if (empty($s['class']) || ! $classes->firstWhere('id', (int) $s['class'])) {
            if ($classes->count() === 1) {
                $s['class'] = $classes->first()->id;
                $this->save($s);
            } else {
                $rows = $classes->map(fn ($c) => [['text' => '🏫 ' . $c->name, 'data' => 'q:cls:' . $c->id]])->values()->all();
                $rows[] = [['text' => '❌ لغو', 'data' => 'q:x']];
                $this->say($msgId, 'برای کدام کلاس؟', $rows);

                return;
            }
        }
        if (empty($s['qs']) && ($s['mode'] ?? '') === 'bank') {
            $this->bankPicker($user, $s, $msgId);

            return;
        }
        if (empty($s['qs'])) {
            if (($s['mode'] ?? '') === 'same' || ! empty($s['ready'])) {
                $this->generate($user, $s, $msgId);
            } else {
                $this->setup($user, $s, $msgId);
            }

            return;
        }
        $this->review($user, $s, $msgId);
    }

    private function show(User $user, array $s, int $msgId = 0): void
    {
        $s['qs'] ? $this->review($user, $s, $msgId) : $this->next($user, $msgId);
    }

    /** تنظیمات پیش از ساخت: تعداد، دشواری، نوع، فضای داستانی، توضیح. */
    private function setup(User $user, array $s, int $msgId = 0): void
    {
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $L = [
            'ساختِ ' . self::KIND_FA[$s['kind']] . ($class ? ' — ' . $class->name : ''),
            '📚 منبع: ' . ($s['src'] ? self::MODE_FA[$s['mode']] . ' (' . Jalali::fa((string) count($s['src'])) . ' صفحه)' : '«' . trim($s['lesson'] . ' ' . $s['topic']) . '»'),
            '🔢 تعداد: ' . Jalali::fa((string) $s['count']) . ' سؤال · 📊 دشواری: ' . self::DIFF_FA[$s['diff']],
            '🧩 نوع: ' . implode('، ', array_map(fn ($t) => self::TYPE_FA[$t], $s['types'])),
            '🎨 فضای داستانی: ' . ($s['flavor'] ?: 'ندارد'),
        ];
        if ($s['ins']) {
            $L[] = '✏️ توضیح: ' . $s['ins'];
        }
        $L[] = '';
        $L[] = 'تنظیم کن و «✨ بساز» را بزن.';
        $mark = fn ($on, $t) => ($on ? '✓ ' : '') . $t;
        $types = array_map(fn ($t) => ['text' => $mark(in_array($t, $s['types'], true), self::TYPE_FA[$t]), 'data' => 'q:typ:' . $t], $this->allowedTypes($s));
        $rows = [
            [['text' => '➖', 'data' => 'q:cnt:-'], ['text' => '🔢 ' . Jalali::fa((string) $s['count']) . ' سؤال', 'data' => 'q:cnt:0'], ['text' => '➕', 'data' => 'q:cnt:+']],
            array_map(fn ($d) => ['text' => $mark($s['diff'] === $d, self::DIFF_FA[$d]), 'data' => 'q:dif:' . $d], array_keys(self::DIFF_FA)),
            ...array_chunk($types, 2),
            [['text' => '🎨 فضای داستانی', 'data' => 'q:thm'], ['text' => '✏️ توضیح برای هوش مصنوعی', 'data' => 'q:ins']],
            [['text' => '🗄 به‌جایش از بانکِ سؤال انتخاب کن', 'data' => 'q:bank']],
            [['text' => '✨ بساز', 'data' => 'q:go'], ['text' => '❌ لغو', 'data' => 'q:x']],
        ];
        $this->say($msgId, implode("\n", $L), $rows);
    }

    /** ساختِ سؤال‌ها (همه، یا $more سؤالِ بیشتر). */
    private function generate(User $user, array $s, int $msgId = 0, int $more = 0): void
    {
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $same = $s['mode'] === 'same' && ! $more;
        $wait = $s['src']
            ? ($same ? '⏳ دارم سؤال‌های برگه را با دقت می‌خوانم…' : '⏳ دارم برگه را می‌خوانم و سؤال می‌سازم…')
            : '⏳ دارم ' . Jalali::fa((string) ($more ?: $s['count'])) . ' سؤال دربارهٔ «' . trim($s['lesson'] . ' ' . $s['topic']) . '» می‌سازم…';
        $sent = $this->api->send($this->chat, $wait . ' (حدودِ یک دقیقه)');
        $panel = (int) ($sent['result']['message_id'] ?? 0);
        $this->api->call('sendChatAction', ['chat_id' => $this->chat, 'action' => 'typing'], 4);
        @set_time_limit(170);

        $sources = [];
        foreach ($s['src'] as $f) {
            $d = BotFile::download($this->platform, $f['fid'], 0);   // بدونِ سقفِ حجم
            if (! $d['ok']) {
                $this->fail($panel, '📎 ' . $d['error']);

                return;
            }
            $sources[] = ['mime' => BotFile::sniff($d['bytes'], $f['mime']), 'data' => base64_encode($d['bytes'])];
        }
        $types = $same ? $this->allowedTypes($s) : $s['types'];
        $r = app(SmartExamAiService::class)->generate([
            'audience' => $s['kind'] === 'exam' ? 'exam' : 'game',
            'count' => $same ? $this->maxCount($s) : ($more ?: $s['count']),
            'types' => $types, 'difficulty' => $s['diff'], 'kind' => 'practice',
            'grade' => $class?->grade, 'subject' => $s['lesson'], 'topic' => $s['topic'],
            'flavor' => $s['flavor'], 'instructions' => $s['ins'],
            'sources' => $sources, 'source_mode' => $s['mode'] === 'same' && $more ? 'similar' : ($s['mode'] === 'topic' ? 'similar' : $s['mode']),
            'avoid' => array_slice(array_merge(array_column($s['qs'], 'prompt'), (array) ($s['avoid'] ?? [])), -60),
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
        ]);
        $qs = array_values(array_filter(array_map(fn ($q) => self::fromAi($q), (array) ($r['questions'] ?? []))));
        if (! $qs) {
            $this->fail($panel, '⚠️ ساختِ سؤال انجام نشد' . (! empty($r['message']) ? ': ' . $r['message'] : '.'), $more ? 'q:l' : 'q:go');

            return;
        }
        $s = $this->get() ?? $s;
        $s['qs'] = array_slice(array_merge($s['qs'], $qs), 0, 50);
        $s['ready'] = true;
        if (! $s['title']) {
            $s['title'] = Str::limit(trim($s['topic'] ?: $s['lesson']) ?: ($s['src'] ? 'سؤال‌های برگه‌ی ' . Jalali::format(now()) : 'سؤال‌های کلاس'), 100, '');
        }
        $this->save($s);
        $this->review($user, $s, $panel, $more ? '➕ ' . Jalali::fa((string) count($qs)) . ' سؤالِ تازه اضافه شد.' : ($r['message'] ?? null));
    }

    private function fail(int $panel, string $text, string $retry = 'q:go'): void
    {
        $rows = [[['text' => '🔁 دوباره', 'data' => $retry], ['text' => '❌ لغو', 'data' => 'q:x']]];
        $panel ? $this->api->edit($this->chat, $panel, $text, $rows) : $this->api->send($this->chat, $text, $rows);
    }

    /** فهرستِ بازبینی: همه‌ی سؤال‌ها + دکمه‌ها. */
    private function review(User $user, array $s, int $msgId = 0, ?string $note = null): void
    {
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $n = count($s['qs']);
        $head = self::KIND_FA[$s['kind']] . ' «' . $s['title'] . '» — ' . Jalali::fa((string) $n) . ' سؤال' . ($class ? ' · ' . $class->name : '');
        $look = $s['kind'] === 'live' ? self::liveLine($s['rules']) : ($s['kind'] === 'game'
            ? '🎨 قالب: ' . (\App\Models\GameTemplate::where('key', $s['tpl'])->value('name') ?? 'پیش‌فرض') . ($s['flavor'] ? ' · تم: ' . $s['flavor'] : '')
            : '⏱ ' . Jalali::fa((string) $s['rules']['duration']) . ' دقیقه · 🔁 ' . Jalali::fa((string) $s['rules']['attempts']) . ' بار · 🎯 قبولی ' . Jalali::fa((string) $s['rules']['pass']) . '٪');
        // متنِ پیام حداکثر ۴۰۹۶ نویسه است؛ اگر سؤال‌ها زیادند، کوتاه‌تر نشان می‌دهیم
        $lim = $n > 12 ? 70 : ($n > 7 ? 110 : 170);
        $body = [];
        foreach ($s['qs'] as $i => $q) {
            $body[] = Jalali::fa((string) ($i + 1)) . ') ' . (! empty($q['media_url']) ? '🖼️ ' : '') . Str::limit($q['prompt'], $lim) . "\n   ✔ " . Str::limit(self::answerOf($q), 60);
        }
        $text = implode("\n", array_filter([$note, $head, $look, '', implode("\n", $body), '', 'روی شماره‌ی هر سؤال بزن تا ببینی، ✏️ ویرایش، 🔄 جایگزین یا 🗑 حذفش کنی.']));
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3990) . '…';
        }
        $nums = array_map(fn ($i) => ['text' => Jalali::fa((string) ($i + 1)), 'data' => 'q:v:' . $i], array_keys($s['qs']));
        $rows = array_chunk($nums, 6);
        $rows[] = [['text' => '➕ ۵ سؤالِ دیگر', 'data' => 'q:more'], ['text' => '🗄 از بانک', 'data' => 'q:bank'], ['text' => '🏷️ عنوان', 'data' => 'q:ttl']];
        $rows[] = match ($s['kind']) {
            'game' => [['text' => '🎨 قالبِ بازی', 'data' => 'q:tpls'], ['text' => '🌈 تم', 'data' => 'q:thm:r'], ['text' => '⚙️ قوانین', 'data' => 'q:rul']],
            'live' => [['text' => '⚙️ زمان‌بندی و قوانینِ مسابقه', 'data' => 'q:rul']],
            default => [['text' => '🎨 فضای داستانی', 'data' => 'q:thm:r'], ['text' => '⚙️ قوانینِ آزمون', 'data' => 'q:rul']],
        };
        $rows[] = $s['kind'] === 'live'
            ? [['text' => '🏆 ساختِ مسابقه و خبر به بچه‌ها', 'data' => 'q:pub:1']]
            : [['text' => '🚀 ساخت و انتشار', 'data' => 'q:pub:1'], ['text' => '💾 پیش‌نویس', 'data' => 'q:pub:0']];
        $rows[] = [['text' => '❌ لغو', 'data' => 'q:x']];
        $this->say($msgId, $text, $rows);
    }

    /** نمایشِ کاملِ یک سؤال با دکمه‌های ویرایش. */
    private function view(User $user, array $s, int $i, int $msgId = 0): void
    {
        $q = $s['qs'][$i] ?? null;
        if (! $q) {
            $this->review($user, $s, $msgId);

            return;
        }
        $n = count($s['qs']);
        $L = ['سؤالِ ' . Jalali::fa((string) ($i + 1)) . ' از ' . Jalali::fa((string) $n) . ' · ' . self::TYPE_FA[$q['type']] . ' · ' . (self::DIFF_FA[$q['difficulty']] ?? ''), '', $q['prompt'], ''];
        if (in_array($q['type'], ['mc', 'tf'], true)) {
            foreach ($q['choices'] as $ci => $c) {
                $L[] = ($c['correct'] ? '✅ ' : '▫️ ') . (self::LETTERS[$ci] ?? Jalali::fa((string) ($ci + 1))) . ') ' . $c['value'];
            }
        } else {
            $L[] = '✔ پاسخ: ' . ($q['answer'] ?? '');
        }
        if (! empty($q['explanation'])) {
            $L[] = '';
            $L[] = '💡 ' . Str::limit($q['explanation'], 400);
        }
        $L[] = '';
        $L[] = ! empty($q['media_url']) ? '🖼️ این سؤال شکل دارد.' : '▫️ بدونِ شکل';
        $rows = [[['text' => '✏️ ویرایش', 'data' => 'q:e:' . $i], ['text' => '🔄 جایگزین', 'data' => 'q:r:' . $i], ['text' => '🗑 حذف', 'data' => 'q:d:' . $i]]];
        $rows[] = ! empty($q['media_url'])
            ? [['text' => '🖼️ دیدنِ شکل', 'data' => 'q:fs:' . $i], ['text' => '🤖 شکلِ تازه', 'data' => 'q:fa:' . $i], ['text' => '🚫 بدونِ شکل', 'data' => 'q:fd:' . $i]]
            : [['text' => '🤖 ساختِ شکل', 'data' => 'q:fa:' . $i], ['text' => '📷 عکسِ شکل را می‌فرستم', 'data' => 'q:fp:' . $i]];
        if (! empty($q['media_url'])) {
            $rows[1][] = ['text' => '📷 عکس', 'data' => 'q:fp:' . $i];
        }
        if (in_array($q['type'], ['mc', 'tf'], true)) {
            $rows[] = array_map(fn ($ci) => ['text' => ($q['choices'][$ci]['correct'] ? '✅ ' : '') . ($q['type'] === 'tf' ? $q['choices'][$ci]['value'] : self::LETTERS[$ci] ?? (string) ($ci + 1)), 'data' => 'q:k:' . $i . ':' . $ci], array_keys($q['choices']));
        }
        $rows[] = array_values(array_filter([
            $i > 0 ? ['text' => '→ قبلی', 'data' => 'q:v:' . ($i - 1)] : null,
            ['text' => '📋 همه‌ی سؤال‌ها', 'data' => 'q:l'],
            $i < $n - 1 ? ['text' => 'بعدی ←', 'data' => 'q:v:' . ($i + 1)] : null,
        ]));
        $this->say($msgId, Str::limit(implode("\n", $L), 4000), $rows);
    }

    /* ─────────────── 🗄 بانکِ سؤال ─────────────── */

    private const BANK_PAGE = 8;

    private function bankState(array $s, bool $reset = false): array
    {
        $b = ! $reset && is_array($s['bank'] ?? null) ? $s['bank'] : [];

        return $b + ['subj' => (string) ($s['lesson'] ?? ''), 'q' => '', 'page' => 0, 'pick' => [], 'all' => false];
    }

    private function bankQuery(User $user, array $s, bool $withSubject = true)
    {
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $b = $this->bankState($s);

        return \App\Support\BankAccess::search($user, array_filter([
            'grade' => $b['all'] ? null : $class?->grade,
            'subject' => $withSubject && $b['subj'] !== '' ? $b['subj'] : null,
            'search' => $b['q'] !== '' ? $b['q'] : null,
            'types' => $this->allowedTypes($s),
            'exclude' => array_values(array_filter(array_column($s['qs'], 'bank_id'))),
        ]));
    }

    /** درس‌هایی که در بانک (برای همین پایه) سؤال دارند. */
    private function bankSubjectList(User $user, array $s): array
    {
        return $this->bankQuery($user, $s, false)->reorder()->selectRaw('COALESCE(subject, book) as subj')->distinct()
            ->pluck('subj')->filter()->take(14)->values()->all();
    }

    private function bankSubjects(User $user, array $s, int $msgId): void
    {
        $subs = $this->bankSubjectList($user, $s);
        $rows = collect($subs)->map(fn ($n, $i) => ['text' => '📚 ' . $n, 'data' => 'q:bsub:' . $i])->chunk(2)->map(fn ($c) => $c->values()->all())->values()->all();
        $rows[] = [['text' => '📚 همه‌ی درس‌ها', 'data' => 'q:bsub:all'], ['text' => '↩️ بازگشت', 'data' => 'q:bg:0']];
        $this->say($msgId, $subs ? '📚 کدام درس؟' : '📚 هنوز سؤالی با درسِ مشخص در بانک نیست.', $rows);
    }

    private function bankPicker(User $user, array $s, int $msgId = 0): void
    {
        $b = $this->bankState($s);
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $q = $this->bankQuery($user, $s);
        $total = (clone $q)->count();
        $pages = max(1, (int) ceil($total / self::BANK_PAGE));
        $b['page'] = min($b['page'], $pages - 1);
        $rows = (clone $q)->orderByDesc('used_count')->latest('id')->skip($b['page'] * self::BANK_PAGE)->take(self::BANK_PAGE)->get();
        $head = '🗄 بانکِ سؤال — برای ' . self::KIND_FA[$s['kind']]
            . "\n🎓 " . ($b['all'] ? 'همه‌ی پایه‌ها' : 'پایه‌ی ' . ($class?->grade ?: '—'))
            . ' · 📚 ' . ($b['subj'] !== '' ? $b['subj'] : 'همه‌ی درس‌ها') . ($b['q'] !== '' ? ' · 🔎 «' . $b['q'] . '»' : '')
            . "\n" . Jalali::fa((string) $total) . ' سؤال' . ($total ? ' · صفحه‌ی ' . Jalali::fa((string) ($b['page'] + 1)) . ' از ' . Jalali::fa((string) $pages) : '')
            . ' · ✅ انتخاب‌شده: ' . Jalali::fa((string) count($b['pick']));
        $L = [$head, ''];
        $btn = [];
        foreach ($rows as $k => $r) {
            $n = $b['page'] * self::BANK_PAGE + $k + 1;
            $on = in_array($r->id, $b['pick'], true);
            $L[] = ($on ? '✅ ' : '') . Jalali::fa((string) $n) . ') ' . ($r->media_path ? '🖼️ ' : '') . Str::limit($r->prompt, 110)
                . ' · ' . (self::TYPE_FA[$r->type] ?? $r->type);
            $btn[] = ['text' => ($on ? '✅ ' : '') . Jalali::fa((string) $n), 'data' => 'q:bp:' . $r->id];
        }
        if (! $total) {
            $L[] = 'سؤالی پیدا نشد. درس یا جست‌وجو را عوض کن، یا «همه‌ی پایه‌ها» را بزن.';
        } else {
            $L[] = '';
            $L[] = 'روی شماره‌ها بزن تا انتخاب شوند، بعد «➕ افزودن».';
        }
        $kb = array_chunk($btn, 4);
        $nav = array_values(array_filter([
            $b['page'] > 0 ? ['text' => '→ قبلی', 'data' => 'q:bg:-'] : null,
            $b['page'] < $pages - 1 ? ['text' => 'بعدی ←', 'data' => 'q:bg:+'] : null,
        ]));
        if ($nav) {
            $kb[] = $nav;
        }
        $kb[] = [['text' => '📚 درس', 'data' => 'q:bs'], ['text' => '🔎 جست‌وجو', 'data' => 'q:bq'], ['text' => $b['all'] ? '🎓 فقط همین پایه' : '🎓 همه‌ی پایه‌ها', 'data' => 'q:bgr']];
        if ($b['pick']) {
            $kb[] = [['text' => '➕ افزودنِ ' . Jalali::fa((string) count($b['pick'])) . ' سؤال', 'data' => 'q:badd']];
        }
        $kb[] = [$s['qs'] ? ['text' => '↩️ بازگشت به سؤال‌ها', 'data' => 'q:l'] : ['text' => '❌ لغو', 'data' => 'q:x']];
        $s['bank'] = $b;
        $this->save($s);
        $this->say($msgId, Str::limit(implode("\n", $L), 4000), $kb);
    }

    private function bankAdd(User $user, array $s, int $msgId): void
    {
        $b = $this->bankState($s);
        $rows = \App\Support\BankAccess::visibleQuery($user)->whereIn('id', $b['pick'])->get()->keyBy('id');
        $added = 0;
        foreach ($b['pick'] as $id) {
            if (! ($r = $rows->get($id))) {
                continue;
            }
            $q = self::fromAi(\App\Support\BankAccess::row($r, $user));
            if ($q && in_array($q['type'], $this->allowedTypes($s), true)) {
                $q['bank_id'] = $r->id;
                $s['qs'][] = $q;
                $added++;
            }
        }
        $s['qs'] = array_slice($s['qs'], 0, 50);
        $s['bank'] = $this->bankState($s, reset: true);
        $s['ready'] = true;
        if (! $s['title'] && $s['qs']) {
            $s['title'] = Str::limit(($s['qs'][0]['topic'] ?? '') ?: ($b['subj'] ?: 'سؤال‌های بانک'), 100, '');
        }
        $this->save($s);
        $s['qs'] ? $this->review($user, $s, $msgId, '🗄 ' . Jalali::fa((string) $added) . ' سؤال از بانک اضافه شد.') : $this->bankPicker($user, $s, $msgId);
    }

    /* ─────────────── شکلِ سؤال ─────────────── */

    /** نشانیِ کامل برای پیام‌رسان. */
    private static function absolute(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : rtrim(BotConfig::siteUrl(), '/') . '/' . ltrim($url, '/');
    }

    /** عکس مستقیم فرستاده می‌شود؛ SVG (شکلِ رسم‌شده) را پیام‌رسان‌ها نشان نمی‌دهند → پیوند. */
    private function sendFigure(string $url, string $caption): void
    {
        $abs = self::absolute($url);
        if (! str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?? ''), '.svg')) {
            $r = $this->api->call('sendPhoto', ['chat_id' => $this->chat, 'photo' => $abs, 'caption' => '🖼️ ' . $caption], 30);
            if ($r['ok'] ?? false) {
                return;
            }
        }
        $this->api->send($this->chat, '🖼️ ' . $caption . ":\n" . $abs);
    }

    private function figureAi(User $user, array $s, int $i, int $msgId): void
    {
        $q = $s['qs'][$i];
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $this->api->edit($this->chat, $msgId, '⏳ دارم برای سؤالِ ' . Jalali::fa((string) ($i + 1)) . ' شکل می‌سازم…');
        $r = \App\Support\FigureAi::suggest($q['prompt'], array_column($q['choices'] ?? [], 'value'), self::answerOf($q), $class?->grade, $s['lesson'] ?: null);
        $url = $r['ok'] ? \App\Support\QuestionMedia::storeFigure($r['spec']) : null;
        $s = $this->get() ?? $s;
        if ($url && isset($s['qs'][$i])) {
            $s['qs'][$i]['media_url'] = $url;
            $s['qs'][$i]['figure'] = $r['spec'];
            $this->save($s);
            $this->sendFigure($url, 'شکلِ سؤالِ ' . Jalali::fa((string) ($i + 1)));
        } else {
            $this->api->send($this->chat, '⚠️ ' . ($r['message'] ?? 'شکل ساخته نشد.'));
        }
        $this->view($user, $s, $i, $msgId);
    }

    private function figurePhoto(User $user, array $s, int $i, array $f): void
    {
        $s['wait'] = null;
        $d = $f['mime'] === 'application/pdf' ? ['ok' => false, 'error' => 'برای شکل، عکس بفرست (نه PDF).'] : BotFile::download($this->platform, $f['fid'], 6 * 1024 * 1024);
        $url = $d['ok'] ? \App\Support\QuestionMedia::storeImage($d['bytes']) : null;
        if ($url) {
            $s['qs'][$i]['media_url'] = $url;
            unset($s['qs'][$i]['figure']);
        }
        $this->save($s);
        $this->api->send($this->chat, $url ? '✅ شکلِ سؤالِ ' . Jalali::fa((string) ($i + 1)) . ' ثبت شد.' : '⚠️ ' . ($d['error'] ?? 'این عکس خوانده نشد.'));
        $this->view($user, $s, $i);
    }

    /** جایگزینیِ یک سؤال با سؤالِ تازه‌ی هم‌نوع. */
    private function replace(User $user, array $s, int $i, int $msgId): void
    {
        $q = $s['qs'][$i];
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        $this->api->edit($this->chat, $msgId, '⏳ دارم یک سؤالِ جایگزین برای سؤالِ ' . Jalali::fa((string) ($i + 1)) . ' می‌سازم…');
        @set_time_limit(120);
        $r = app(SmartExamAiService::class)->generate([
            'audience' => $s['kind'] === 'exam' ? 'exam' : 'game', 'count' => 1, 'types' => [$q['type']], 'difficulty' => $q['difficulty'] ?? 'medium',
            'grade' => $class?->grade, 'subject' => $s['lesson'], 'topic' => $q['topic'] ?: $s['topic'], 'flavor' => $s['flavor'],
            'instructions' => Str::limit('یک سؤالِ تازه به‌جای این سؤال، با همان مبحث و سطح ولی متفاوت: «' . $q['prompt'] . '». ' . $s['ins'], 480, ''),
            'avoid' => array_column($s['qs'], 'prompt'), 'school_id' => $user->school_id, 'teacher_id' => $user->id,
        ]);
        $new = isset($r['questions'][0]) ? self::fromAi($r['questions'][0]) : null;
        $s = $this->get() ?? $s;
        if (! $new) {
            $this->api->edit($this->chat, $msgId, '⚠️ جایگزین ساخته نشد' . (! empty($r['message']) ? ': ' . $r['message'] : '.'),
                [[['text' => '🔁 دوباره', 'data' => 'q:r:' . $i], ['text' => '📋 همه‌ی سؤال‌ها', 'data' => 'q:l']]]);

            return;
        }
        $s['qs'][$i] = $new;
        $this->save($s);
        $this->view($user, $s, $i, $msgId);
    }

    private function themes(array $s, int $msgId, bool $fromReview): void
    {
        $sfx = $fromReview ? ':r' : '';
        $rows = \App\Models\Theme::where('is_active', true)->where('key', '!=', 'brand')->orderBy('sort')->limit(16)->get(['id', 'name', 'emoji'])
            ->map(fn ($t) => ['text' => ((int) $s['theme_id'] === $t->id ? '✓ ' : '') . trim($t->emoji . ' ' . $t->name), 'data' => 'q:th:' . $t->id . $sfx])->chunk(2)->map(fn ($c) => $c->values()->all())->values()->all();
        $rows[] = [['text' => ($s['theme_id'] ? '' : '✓ ') . 'بدونِ تم', 'data' => 'q:th:0' . $sfx], ['text' => '↩️ بازگشت', 'data' => $fromReview ? 'q:l' : 'q:cnt:0']];
        $this->say($msgId, $s['kind'] === 'game'
            ? "🌈 تمِ بازی را انتخاب کن (رنگ و فضای بازی و داستانِ سؤال‌ها):"
            : "🎨 فضای داستانیِ سؤال‌ها را انتخاب کن (نام‌ها و مثال‌ها از همین دنیا ساخته می‌شوند):", $rows);
    }

    private function templates(array $s, int $msgId): void
    {
        $rows = \App\Models\GameTemplate::where('is_active', true)->orderBy('id')->get(['key', 'name'])
            ->map(fn ($t) => ['text' => ($s['tpl'] === $t->key ? '✓ ' : '') . $t->name, 'data' => 'q:tp:' . $t->key])->chunk(2)->map(fn ($c) => $c->values()->all())->values()->all();
        $rows[] = [['text' => '↩️ بازگشت', 'data' => 'q:l']];
        $this->say($msgId, '🎮 قالبِ بازی را انتخاب کن:', $rows);
    }

    private function rules(array $s, int $msgId): void
    {
        $rows = [];
        $L = ['⚙️ قوانینِ ' . self::KIND_FA[$s['kind']], 'روی هر مورد بزن تا عوض شود.'];
        if ($s['kind'] === 'live') {
            $L[] = '';
            $L[] = '🚀 «روی تخته» یعنی سرِ کلاس از صفحه‌ی مسابقه اجرا می‌کنی؛ «… دقیقه‌ی دیگر» یا «ساعتِ …» یعنی خودش سرِ وقت شروع می‌شود و بچه‌ها از خانه با گوشی شرکت می‌کنند.';
        }
        foreach ($s['rules'] as $k => $v) {
            if (! isset(self::RULE_FA[$k])) {
                continue;
            }
            $label = is_bool($v)
                ? ($v ? '✅ ' : '⬜ ') . self::RULE_FA[$k]
                : self::RULE_FA[$k] . ': ' . self::ruleValue($k, $v);
            $rows[] = [['text' => $label, 'data' => 'q:ru:' . $k]];
        }
        if ($s['kind'] === 'live') {
            $rows[] = [['text' => '🕗 شروع در ساعتِ مشخص', 'data' => 'q:ltime']];
        }
        $rows[] = [['text' => '↩️ بازگشت به سؤال‌ها', 'data' => 'q:l']];
        $this->say($msgId, implode("\n", $L), $rows);
    }

    private function toggleRule(array &$s, string $k): void
    {
        if (! array_key_exists($k, $s['rules'])) {
            return;
        }
        if (is_bool($s['rules'][$k])) {
            $s['rules'][$k] = ! $s['rules'][$k];

            return;
        }
        $c = self::CYCLES[$k] ?? null;
        if ($c) {
            $cur = $s['rules'][$k];
            $at = array_search(is_numeric($cur) ? (int) $cur : $cur, $c, true);
            $s['rules'][$k] = $c[$at === false ? 0 : ($at + 1) % count($c)];
        }
    }

    /* ═════════════════════════ ساخت و انتشار ═════════════════════════ */

    private function publish(User $user, array $s, bool $publish, int $msgId): void
    {
        $class = $this->bot->classes($user)->firstWhere('id', (int) $s['class']);
        if (! $s['qs']) {
            $this->next($user, $msgId);

            return;
        }
        try {
            [$done, $undo, $summary, $action] = match ($s['kind']) {
                'game' => $this->saveGame($user, $class, $s, $publish),
                'live' => $this->saveLive($user, $class, $s),
                default => $this->saveExam($user, $class, $s, $publish),
            };
        } catch (ValidationException $e) {
            $this->api->edit($this->chat, $msgId, '⚠️ ساخته نشد: ' . collect($e->errors())->flatten()->first() . "\nسؤالِ مشکل‌دار را ویرایش یا حذف کن و دوباره بزن.", [[['text' => '📋 همه‌ی سؤال‌ها', 'data' => 'q:l']]]);

            return;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->api->edit($this->chat, $msgId, '⛔ ' . ($e->getMessage() ?: 'دسترسی ندارید.'), [[['text' => '📋 همه‌ی سؤال‌ها', 'data' => 'q:l']]]);

            return;
        }
        $log = BotLog::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'platform' => $this->platform,
            'action' => $action, 'summary' => Str::limit($summary, 250), 'undo' => $undo]);
        $this->forget();
        $rows = [[['text' => '↩️ برگردان (تا ۱۰ دقیقه)', 'data' => 'un:' . $log->id]]];
        if ($action === 'live') {
            array_unshift($rows, self::liveRows((int) $undo['live']));
        }
        $rows[] = [['text' => '🧪 طراحیِ تازه', 'data' => 'q:home'], ['text' => '🏠 منو', 'data' => 'm:main']];
        $this->api->edit($this->chat, $msgId, $done, $rows);
    }

    private function saveExam(User $user, ?Classroom $class, array $s, bool $publish): array
    {
        if (! \App\Support\SmartLab::enabledFor($user)) {
            throw ValidationException::withMessages(['exam' => 'آزمایشگاهِ آزمون برای مدرسه‌ی شما روشن نیست.']);
        }
        $before = \App\Models\SmartExam::withoutGlobalScopes()->where('teacher_id', $user->id)->max('id') ?? 0;
        $this->bot->callSite($user, \App\Http\Controllers\Teacher\SmartExamController::class, 'store', [
            'title' => $s['title'], 'kind' => 'practice', 'status' => $publish ? 'published' : 'draft',
            'classroom_id' => $class?->id, 'grade' => $class?->grade, 'subject' => $s['lesson'] ?: null, 'topic' => Str::limit($s['topic'], 110, '') ?: null,
            'rules' => $s['rules'], 'adaptive' => false,
            // کلاسی که خودِ معلم معلمِ اصلی‌اش است؛ وگرنه «همه‌ی کلاس‌های من»
            'target_classrooms' => $class && (int) $class->teacher_id === (int) $user->id ? [$class->id] : [],
            'questions' => array_map(fn ($q) => [
                'type' => $q['type'], 'prompt' => $q['prompt'], 'choices' => $q['choices'], 'answer' => $q['answer'],
                'explanation' => $q['explanation'], 'difficulty' => $q['difficulty'], 'bloom' => $q['bloom'], 'topic' => $q['topic'],
                'media_url' => $q['media_url'] ?? null, 'bank_id' => $q['bank_id'] ?? null,
                'points' => 1, 'source' => empty($q['bank_id']) ? 'ai' : 'bank',
            ], $s['qs']),
        ]);
        $exam = \App\Models\SmartExam::withoutGlobalScopes()->where('teacher_id', $user->id)->where('id', '>', $before)->latest('id')->first();
        if (! $exam) {
            throw ValidationException::withMessages(['exam' => 'آزمون ذخیره نشد؛ دوباره امتحان کن.']);
        }

        return [
            '✅ آزمونِ «' . $exam->title . '» با ' . Jalali::fa((string) count($s['qs'])) . ' سؤال ' . ($publish ? 'ساخته و برای کلاس منتشر شد 🚀' : 'به‌صورتِ پیش‌نویس ساخته شد 💾')
                . "\n✏️ ویرایش در سایت: " . BotConfig::siteUrl() . '/teacher/smart-exams/' . $exam->id . '/edit',
            ['exam' => $exam->id], 'آزمون: ' . $exam->title . ($publish ? ' (منتشر شد)' : ' (پیش‌نویس)'), 'exam',
        ];
    }

    private function saveGame(User $user, ?Classroom $class, array $s, bool $publish): array
    {
        $tpl = $s['tpl'] ?: (\App\Models\GameTemplate::where('key', 'board-space')->where('is_active', true)->value('key') ?? \App\Models\GameTemplate::where('is_active', true)->value('key'));
        $before = \App\Models\EduGame::withoutGlobalScopes()->where('teacher_id', $user->id)->max('id') ?? 0;
        $qs = [];
        foreach ($s['qs'] as $q) {
            if ($q['type'] === 'desc') {
                continue;   // بازی سؤالِ تشریحی ندارد
            }
            $qs[] = [
                'type' => $q['type'] === 'blank' ? 'short' : $q['type'], 'prompt' => Str::limit($q['prompt'], 400, ''),
                'choices' => $q['type'] === 'blank' ? [['value' => (string) $q['answer'], 'correct' => true]] : $q['choices'],
                'answer' => $q['answer'], 'explanation' => $q['explanation'], 'hint1' => $q['hint'] ? Str::limit($q['hint'], 250, '') : null,
                'difficulty' => $q['difficulty'], 'bloom' => $q['bloom'], 'topic' => $q['topic'], 'source' => empty($q['bank_id']) ? 'ai' : 'bank',
                'media_url' => $q['media_url'] ?? null, 'bank_id' => $q['bank_id'] ?? null,
            ];
        }
        $this->bot->callSite($user, \App\Http\Controllers\Teacher\EduGameController::class, 'store', [
            'title' => Str::limit($s['title'], 110, ''), 'template_key' => $tpl, 'theme_id' => $s['theme_id'] ?: null,
            'subject' => $s['lesson'] ?: null, 'grade' => $class?->grade, 'classroom_id' => $class?->id, 'topic' => Str::limit($s['topic'], 150, '') ?: null,
            'difficulty' => in_array($s['diff'], ['easy', 'medium', 'hard'], true) ? $s['diff'] : 'medium',
            'status' => $publish ? 'published' : 'draft', 'rules' => $s['rules'], 'questions' => $qs,
        ]);
        $game = \App\Models\EduGame::withoutGlobalScopes()->where('teacher_id', $user->id)->where('id', '>', $before)->latest('id')->first();
        if (! $game) {
            throw ValidationException::withMessages(['game' => 'بازی ذخیره نشد؛ دوباره امتحان کن.']);
        }

        return [
            '✅ بازیِ «' . $game->title . '» با ' . Jalali::fa((string) count($qs)) . ' سؤال ' . ($publish ? 'ساخته و برای کلاس منتشر شد 🚀' : 'به‌صورتِ پیش‌نویس ساخته شد 💾')
                . "\n✏️ ویرایش در سایت: " . BotConfig::siteUrl() . '/teacher/studio',
            ['game' => $game->id], 'بازی: ' . $game->title . ($publish ? ' (منتشر شد)' : ' (پیش‌نویس)'), 'game',
        ];
    }

    /* ═════════════════════════ سؤال: تبدیل و ویرایش ═════════════════════════ */

    /** خروجیِ سرویسِ هوش مصنوعی → سؤالِ استاندارد. */
    public static function fromAi(array $q): ?array
    {
        $type = in_array($q['type'] ?? null, ['mc', 'tf', 'blank', 'desc'], true) ? $q['type'] : ((($q['type'] ?? '') === 'short') ? 'blank' : 'mc');
        $prompt = trim((string) ($q['prompt'] ?? ''));
        if ($prompt === '') {
            return null;
        }
        $choices = array_values(array_filter(array_map(fn ($c) => is_array($c) ? ['value' => trim((string) ($c['value'] ?? $c['text'] ?? '')), 'correct' => ! empty($c['correct'])] : null, (array) ($q['choices'] ?? [])), fn ($c) => $c && $c['value'] !== ''));
        if (in_array($type, ['mc', 'tf'], true) && (count($choices) < 2 || ! collect($choices)->contains('correct', true))) {
            return null;
        }

        return [
            'type' => $type, 'prompt' => mb_substr($prompt, 0, 600), 'choices' => in_array($type, ['mc', 'tf'], true) ? array_slice($choices, 0, 6) : [],
            'answer' => isset($q['answer']) && $q['answer'] !== '' ? (string) $q['answer'] : null,
            'explanation' => $q['explanation'] ?? null, 'hint' => $q['hint'] ?? ($q['hint1'] ?? null),
            'difficulty' => in_array($q['difficulty'] ?? null, ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium',
            'bloom' => $q['bloom'] ?? null, 'topic' => $q['topic'] ?? null,
            'media_url' => \App\Support\QuestionMedia::safeUrl($q['media_url'] ?? null),
        ];
    }

    public static function answerOf(array $q): string
    {
        if (in_array($q['type'], ['mc', 'tf'], true)) {
            foreach ($q['choices'] as $ci => $c) {
                if ($c['correct']) {
                    return ($q['type'] === 'mc' ? (self::LETTERS[$ci] ?? '') . ') ' : '') . $c['value'];
                }
            }

            return '؟';
        }

        return (string) ($q['answer'] ?? '');
    }

    /** سؤال به شکلِ متنِ قابلِ ویرایش. */
    public static function editable(array $q): string
    {
        $L = [$q['prompt']];
        if ($q['type'] === 'mc') {
            foreach ($q['choices'] as $ci => $c) {
                $L[] = (self::LETTERS[$ci] ?? Jalali::fa((string) ($ci + 1))) . ') ' . $c['value'] . ($c['correct'] ? ' ✅' : '');
            }
        } else {
            $L[] = 'پاسخ: ' . self::answerOf($q);
        }

        return implode("\n", $L);
    }

    /**
     * متنِ ویرایش‌شده‌ی معلم → سؤالِ تازه (یا پیامِ خطا).
     * خطِ «الف) …» گزینه است و ✅ یا * گزینه‌ی درست؛ خطِ «پاسخ: …» پاسخ؛ بقیه متنِ سؤال.
     */
    public static function parseEdit(string $text, array $q): array|string
    {
        $prompt = [];
        $opts = [];
        $answer = null;
        foreach (preg_split('/\R/u', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(?:پاسخ|جواب|پاسخ\s*درست)\s*[:：]\s*(.+)$/u', $line, $m)) {
                $answer = trim($m[1]);

                continue;
            }
            if (preg_match('/^(?:[✅✔️*]\s*)?(الف|ب|ج|د|ه|و|[1-6]|[۱-۶]|[a-fA-F])\s*[)\-.:،]\s*(.+)$/u', $line, $m)) {
                $mark = (bool) preg_match('/[✅✔*]/u', $line);
                $val = trim(preg_replace('/\s*(✅|✔️|✔|\*)\s*/u', ' ', $m[2]));
                $opts[] = ['value' => trim($val), 'correct' => $mark];

                continue;
            }
            $prompt[] = $line;
        }
        $new = $q;
        if ($prompt) {
            $new['prompt'] = mb_substr(implode("\n", $prompt), 0, 600);
        }
        if (count($opts) >= 2) {
            if (! collect($opts)->contains('correct', true)) {
                // پاسخ با حرف یا متن؟
                if ($answer !== null) {
                    foreach ($opts as $i => $o) {
                        if ($answer === (self::LETTERS[$i] ?? '-') || $answer === $o['value'] || P::digits($answer) === (string) ($i + 1)) {
                            $opts[$i]['correct'] = true;
                        }
                    }
                }
                // همان جایگاهِ قبلی اگر تعدادِ گزینه‌ها همان است
                if (! collect($opts)->contains('correct', true) && count($opts) === count($q['choices'] ?? [])) {
                    foreach ($q['choices'] as $i => $c) {
                        $opts[$i]['correct'] = (bool) $c['correct'];
                    }
                }
                if (! collect($opts)->contains('correct', true)) {
                    return 'گزینه‌ی درست را با ✅ کنارِ همان گزینه مشخص کن.';
                }
            }
            if (collect($opts)->where('correct', true)->count() > 1) {
                return 'فقط یک گزینه را با ✅ مشخص کن.';
            }
            $isTf = count($opts) === 2 && collect($opts)->pluck('value')->every(fn ($v) => in_array($v, ['درست', 'نادرست', 'صحیح', 'غلط'], true));
            $new['type'] = $isTf ? 'tf' : 'mc';
            $new['choices'] = array_slice($opts, 0, 6);
            $new['answer'] = $isTf ? collect($opts)->firstWhere('correct', true)['value'] : null;
        } elseif ($answer !== null) {
            if ($q['type'] === 'tf') {
                $t = in_array(P::norm($answer), ['درست', 'صحیح', 'true', 'بله'], true);
                $f = in_array(P::norm($answer), ['نادرست', 'غلط', 'false', 'نه'], true);
                if (! $t && ! $f) {
                    return 'برای سؤالِ درست/نادرست بنویس «پاسخ: درست» یا «پاسخ: نادرست».';
                }
                $new['choices'] = [['value' => 'درست', 'correct' => $t], ['value' => 'نادرست', 'correct' => ! $t]];
                $new['answer'] = $t ? 'درست' : 'نادرست';
            } elseif ($q['type'] === 'mc') {
                foreach ($new['choices'] as $i => $c) {
                    $new['choices'][$i]['correct'] = $answer === (self::LETTERS[$i] ?? '-') || $answer === $c['value'] || P::digits($answer) === (string) ($i + 1);
                }
                if (! collect($new['choices'])->contains('correct', true)) {
                    return 'پاسخ با هیچ گزینه‌ای جور نشد؛ گزینه‌ها را هم بفرست و درست را با ✅ مشخص کن.';
                }
            } else {
                $new['answer'] = mb_substr($answer, 0, 300);
            }
        } elseif (! $prompt) {
            return 'متنی برای ویرایش پیدا نشد.';
        }
        if ($new['type'] === 'blank' && $prompt && ! preg_match('/(…|\.{3,}|_{3,}|ـ{3,})/u', $new['prompt'])) {
            return 'سؤالِ جای خالی باید «……» داشته باشد (جای پاسخ).';
        }

        return $new;
    }

    /* ═════════════════════════ 🏆 مسابقه‌ی زنده ═════════════════════════ */

    /**
     * زمان‌بندیِ مسابقه از حرفِ معلم: start = «17:00» | «ساعت ۵ عصر» | «now» | «board» | دقیقه‌ی بعد؛
     * seconds = ثانیه‌ی هر سؤال؛ end_minutes = چند دقیقه بعد از شروع تمام شود.
     */
    public static function liveOpts(array &$s, array $o): void
    {
        if (($s['kind'] ?? '') !== 'live') {
            return;
        }
        $st = trim((string) ($o['start'] ?? ''));
        if ($st !== '') {
            $n = P::norm($st);
            $s['rules']['start'] = match (true) {
                in_array($n, ['now', 'الان', 'همین الان', 'همین حالا', 'فورا'], true) => 0,
                in_array($n, ['board', 'تخته', 'روی تخته', 'کلاس'], true) => 'board',
                (bool) preg_match('/^\d{1,3}$/', P::digits($n)) && (int) P::digits($n) <= 240 && ! str_contains($n, ':') => (int) P::digits($n),
                default => self::parseTime($st) ?? $s['rules']['start'],
            };
        }
        if (! empty($o['seconds'])) {
            $s['rules']['seconds'] = max(5, min(120, (int) $o['seconds']));
        }
        if (isset($o['end_minutes'])) {
            $s['rules']['end'] = max(0, min(600, (int) $o['end_minutes']));
        }
    }

    public static function rulesFor(?string $kind): array
    {
        return match ($kind) {
            'game' => self::GAME_RULES,
            'live' => self::LIVE_RULES,
            default => self::EXAM_RULES,
        };
    }

    private static function ruleValue(string $k, mixed $v): string
    {
        return match ($k) {
            'duration' => Jalali::fa((string) $v) . ' دقیقه',
            'pass' => Jalali::fa((string) $v) . '٪',
            'seconds' => Jalali::fa((string) $v) . ' ثانیه',
            'start' => $v === 'board' ? 'روی تخته‌ی کلاس (خودت اجرا می‌کنی)'
                : (is_string($v) && str_contains($v, ':') ? 'ساعتِ ' . Jalali::fa($v) . (self::startAt($v)?->isTomorrow() ? ' فردا' : ' امروز')
                    : ((int) $v === 0 ? 'همین حالا (خودکار)' : Jalali::fa((string) $v) . ' دقیقه‌ی دیگر (خودکار)')),
            'end' => (int) $v === 0 ? 'بعد از آخرین سؤال' : Jalali::fa((string) $v) . ' دقیقه بعد از شروع',
            default => Jalali::fa((string) $v),
        };
    }

    private static function liveLine(array $r): string
    {
        return '⏱ ' . self::ruleValue('seconds', $r['seconds']) . ' برای هر سؤال · 🚀 ' . self::ruleValue('start', $r['start']) . ' · ⏳ ' . self::ruleValue('end', $r['end']);
    }

    /** زمانِ شروع از قاعده‌ی «start» (null = روی تخته). */
    private static function startAt(mixed $v): ?\Illuminate\Support\Carbon
    {
        if ($v === 'board' || $v === null) {
            return null;
        }
        if (is_string($v) && preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m)) {
            $t = now()->setTime((int) $m[1], (int) $m[2]);

            return $t->lt(now()->subMinute()) ? $t->addDay() : $t;
        }

        return now()->addMinutes((int) $v);
    }

    /** «۲۰:۳۰»، «۸ شب»، «ساعت ۹ صبح» → «HH:MM». */
    public static function parseTime(string $text): ?string
    {
        $t = P::digits(P::norm($text));
        if (! preg_match('/(\d{1,2})(?:\s*[:٫.]\s*(\d{1,2}))?/u', $t, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = isset($m[2]) ? (int) $m[2] : 0;
        if (preg_match('/(شب|عصر|بعدازظهر|بعد از ظهر|غروب)/u', $t) && $h < 12) {
            $h += 12;
        }
        if ($h > 23 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $min);
    }

    public static function liveRows(int $id): array
    {
        return [['text' => '▶️ همین حالا شروع', 'data' => 'q:lvs:' . $id], ['text' => '📊 نتیجه', 'data' => 'q:lvr:' . $id], ['text' => '⏹ پایان', 'data' => 'q:lve:' . $id]];
    }

    private function saveLive(User $user, ?Classroom $class, array $s): array
    {
        $before = \App\Models\LiveContest::withoutGlobalScopes()->where('teacher_id', $user->id)->max('id') ?? 0;
        $start = self::startAt($s['rules']['start'] ?? 'board');
        $qs = [];
        foreach ($s['qs'] as $q) {
            if (! in_array($q['type'], ['mc', 'tf'], true) || count($q['choices']) < 2) {
                continue;   // مسابقه فقط سؤالِ گزینه‌ای دارد
            }
            $choices = $q['choices'];
            if (count($choices) > 4) {   // درست + سه گزینه‌ی نادرستِ اول
                $choices = array_merge(array_values(array_filter($choices, fn ($c) => $c['correct'])), array_slice(array_values(array_filter($choices, fn ($c) => ! $c['correct'])), 0, 3));
            }
            $qs[] = ['prompt' => Str::limit($q['prompt'], 480, ''), 'choices' => array_map(fn ($c) => Str::limit($c['value'], 118, ''), array_column($choices, null)),
                'answer' => (int) array_search(true, array_column($choices, 'correct'), true)];
        }
        if (! $qs) {
            throw ValidationException::withMessages(['live' => 'مسابقه‌ی زنده فقط سؤالِ گزینه‌ای (چهارگزینه‌ای یا درست/نادرست) می‌پذیرد.']);
        }
        $qs = array_map(fn ($q) => ['prompt' => $q['prompt'], 'choices' => array_map(fn ($c) => is_array($c) ? $c['value'] : $c, $q['choices']), 'answer' => $q['answer']], $qs);
        $end = (int) ($s['rules']['end'] ?? 0);
        $this->bot->callSite($user, \App\Http\Controllers\Teacher\LiveContestController::class, 'store', array_filter([
            'title' => Str::limit($s['title'], 140, ''),
            'classroom_id' => $class && (int) $class->teacher_id === (int) $user->id ? $class->id : null,
            'mode' => $start ? 'auto' : 'manual',
            'starts_at' => $start?->format('Y-m-d H:i:s'),
            'seconds' => (int) ($s['rules']['seconds'] ?? 20),
            'questions' => array_slice($qs, 0, 40),
            'end_type' => $end > 0 ? 'duration' : 'none',
            'duration_min' => $end > 0 ? $end : null,
        ], fn ($v) => $v !== null));
        $c = \App\Models\LiveContest::withoutGlobalScopes()->where('teacher_id', $user->id)->where('id', '>', $before)->latest('id')->first();
        if (! $c) {
            throw ValidationException::withMessages(['live' => 'مسابقه ساخته نشد؛ دوباره امتحان کن.']);
        }
        $site = BotConfig::siteUrl();

        return [
            '🏆 مسابقه‌ی زنده‌ی «' . $c->title . '» با ' . Jalali::fa((string) count($qs)) . ' سؤال ساخته شد و به بچه‌ها خبر داده شد.'
                . "\n🔢 کدِ ورود: " . Jalali::fa($c->code)
                . "\n🚀 " . ($start ? 'شروع: ' . Jalali::format($start, true) . ' ساعتِ ' . Jalali::fa($start->format('H:i')) . ' — خودش اجرا می‌شود؛ بچه‌ها با گوشی شرکت می‌کنند.'
                    : 'سرِ کلاس روی تخته اجرا کن: ' . $site . '/teacher/live/' . $c->id . '/host'),
            ['live' => $c->id], 'مسابقه‌ی زنده: ' . $c->title, 'live',
        ];
    }

    /** ▶️ شروع، 📊 نتیجه، ⏹ پایان — از داخلِ بات. */
    private function liveControl(User $user, string $act, int $id, int $msgId): void
    {
        $c = \App\Models\LiveContest::withoutGlobalScopes()->where('teacher_id', $user->id)->find($id);
        if (! $c) {
            $this->api->send($this->chat, 'این مسابقه پیدا نشد.');

            return;
        }
        $c->tick();
        if ($act === 'lvs') {
            if (in_array($c->phase, ['lobby', 'draft'], true)) {
                // «خودکار» از همین لحظه: سؤال‌ها خودشان جلو می‌روند (بدونِ تخته)
                $c->update(['mode' => 'auto', 'starts_at' => now()]);
                $c->refresh()->tick();
                $this->api->send($this->chat, '▶️ «' . $c->title . '» شروع شد؛ بچه‌ها با گوشی جواب می‌دهند و سؤال‌ها خودشان جلو می‌روند.', [self::liveRows($c->id)]);
            } else {
                $this->api->send($this->chat, $c->phase === 'end' ? '⏹ این مسابقه تمام شده.' : '▶️ مسابقه در حالِ اجراست.', [self::liveRows($c->id)]);
            }

            return;
        }
        if ($act === 'lve') {
            if ($c->phase !== 'end') {
                $c->moveTo('end');
            }
            $act = 'lvr';
        }
        $c->refresh();
        $rank = $c->ranking();
        $phase = ['lobby' => '⏳ هنوز شروع نشده', 'draft' => '⏳ هنوز شروع نشده', 'question' => '▶️ در حالِ اجرا — سؤالِ ' . Jalali::fa((string) ($c->current + 1)) . ' از ' . Jalali::fa((string) $c->total()),
            'reveal' => '▶️ در حالِ اجرا — نمایشِ جوابِ سؤالِ ' . Jalali::fa((string) ($c->current + 1)), 'end' => '🏁 تمام شد'][$c->phase] ?? $c->phase;
        $L = ['🏆 ' . $c->title, $phase . ' · ' . Jalali::fa((string) $rank->count()) . ' شرکت‌کننده', ''];
        foreach ($rank->take(15) as $r) {
            $L[] = (['🥇', '🥈', '🥉'][$r['rank'] - 1] ?? Jalali::fa((string) $r['rank']) . '.') . ' ' . $r['name'] . ' — ' . Jalali::fa((string) $r['score']) . ' امتیاز · ' . Jalali::fa((string) $r['correct']) . ' درست';
        }
        if ($rank->isEmpty()) {
            $L[] = 'هنوز کسی وارد نشده.';
        }
        $this->api->send($this->chat, implode("\n", $L), [self::liveRows($c->id)]);
    }

    /* ═════════════════════════ کمکی ═════════════════════════ */

    private function allowedTypes(array $s): array
    {
        return match ($s['kind'] ?? '') {
            'game' => ['mc', 'tf', 'blank'],
            'live' => ['mc', 'tf'],   // مسابقه‌ی زنده فقط گزینه‌ای
            default => ['mc', 'tf', 'blank', 'desc'],
        };
    }

    private function maxCount(array $s): int
    {
        return ['game' => 15, 'live' => 40][$s['kind'] ?? ''] ?? 20;
    }

    /** متنِ همراهِ عکس: «۱۰ سؤال»، «آزمون/بازی»، «همین سؤال‌ها / مشابه / از متن». */
    private function applyCaption(array &$s, string $caption): void
    {
        $c = P::norm($caption);
        if (preg_match('/(\d{1,2})\s*(تا\s*)?سوال/u', P::digits($c), $m)) {
            $s['count'] = max(3, min(20, (int) $m[1]));
        }
        if (preg_match('/(همین\s*سوال|دقیقا|عینا|عین\s*همین|تایپ)/u', $c)) {
            $s['mode'] = 'same';
        } elseif (preg_match('/(مشابه|شبیه|مثل\s*این|مثل\s*همین)/u', $c)) {
            $s['mode'] = 'similar';
        } elseif (preg_match('/(از\s*(رو|روی)\s*(این|متن|صفحه|کتاب)|از\s*متن|از\s*این\s*صفحه|سوال\s*در\s*بیار|سوال\s*دربیار)/u', $c)) {
            $s['mode'] = 'content';
        }
        if (preg_match('/(مسابقه|کوییز|کوئیز)/u', $c)) {
            $s['kind'] = 'live';
            $s['rules'] = self::LIVE_RULES;
        } elseif (preg_match('/(آزمون|امتحان)/u', $c)) {
            $s['kind'] = 'exam';
            $s['rules'] = self::EXAM_RULES;
        } elseif (str_contains($c, 'بازی')) {
            $s['kind'] = 'game';
            $s['rules'] = self::GAME_RULES;
        }
        $s['ins'] = Str::limit($caption, 400, '');
    }

    private function fresh(): array
    {
        return ['src' => [], 'mode' => null, 'class' => null, 'kind' => null, 'lesson' => '', 'topic' => '', 'ins' => '',
            'count' => 8, 'diff' => 'medium', 'types' => ['mc', 'tf'], 'flavor' => '', 'theme_id' => null, 'tpl' => null,
            'rules' => self::EXAM_RULES, 'qs' => [], 'title' => '', 'ready' => false, 'wait' => null];
    }

    private function key(string $k): string
    {
        return 'bot:' . $this->platform . ':' . $this->chat . ':' . $k;
    }

    public function get(): ?array
    {
        $s = Cache::get($this->key('quiz'));

        return is_array($s) ? $s + $this->fresh() : null;
    }

    private function save(array $s): void
    {
        // زمانِ شروعِ انتظار (برای منقضی‌شدن)
        if (! empty($s['wait']) && empty($s['wait']['at'])) {
            $s['wait']['at'] = now()->timestamp;
        }
        Cache::put($this->key('quiz'), $s, now()->addHours(3));
    }

    private function forget(): void
    {
        Cache::forget($this->key('quiz'));
    }

    /** چند عکسِ یک آلبوم هم‌زمان می‌رسند؛ نوشتنِ وضعیت با قفل. */
    private function locked(callable $fn): mixed
    {
        try {
            return Cache::lock($this->key('quiz-lock'), 15)->block(8, $fn);
        } catch (\Throwable $e) {
            return $fn();
        }
    }

    private function say(int $msgId, string $text, array $rows = []): void
    {
        $msgId ? $this->api->edit($this->chat, $msgId, $text, $rows) : $this->api->send($this->chat, $text, $rows);
    }
}
