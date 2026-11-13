<?php

namespace App\Services\Bot;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\BotLink;
use App\Models\BotLog;
use App\Models\Classroom;
use App\Models\GradeColumn;
use App\Models\User;
use App\Models\XpEntry;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotConfig;
use App\Support\Bot\BotParser as P;
use App\Support\ClassSupervision;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 🤖 باتِ معلم (بله و تلگرام) — «بگو، ببین، تأیید کن».
 *
 * هر پیام یا دکمه یک «به‌روزرسانی» است. معلمِ وصل‌شده هر چه بنویسد فهمیده می‌شود (اول بدونِ
 * هوش مصنوعی، بعد اگر لازم شد با هوش مصنوعی)، یک پیش‌نمایش با دکمه‌های ✅ تأیید / ❌ لغو
 * نشان داده می‌شود و فقط پس از تأیید روی سایت اجرا می‌شود — با همان کنترلرهای سایت، پس امتیاز،
 * اعلانِ غیبت، پیامکِ والدین و تسلط دقیقاً مثلِ ثبت از سایت است. هر کار تا ۱۰ دقیقه «برگردان» دارد.
 */
class TeacherBot
{
    private BotApi $api;

    private const UNDO_MIN = 10;

    public function __construct(public string $platform)
    {
        $this->api = BotApi::for($platform);
    }

    /* ═════════════════════════ ورودی ═════════════════════════ */

    public function handle(array $u): void
    {
        $cb = $u['callback_query'] ?? null;
        $msg = $cb['message'] ?? ($u['message'] ?? ($u['edited_message'] ?? null));
        $chat = (string) ($msg['chat']['id'] ?? ($cb['from']['id'] ?? ''));
        if ($chat === '') {
            return;
        }
        $this->chatKey = $chat;
        // فقط گفتگوی خصوصی (نه گروه)
        if (($msg['chat']['type'] ?? 'private') !== 'private') {
            return;
        }
        $from = $cb['from'] ?? ($msg['from'] ?? []);

        try {
            $text = trim((string) ($msg['text'] ?? ''));
            if (! $cb && preg_match('/^\/start(?:\s+(\S+))?/u', $text, $m)) {
                $this->start($chat, $from, $m[1] ?? null);

                return;
            }
            $link = BotLink::where('platform', $this->platform)->where('chat_id', $chat)->first();
            if (! $link) {
                // کدِ ۶ رقمی را مستقیم هم می‌شود فرستاد (اگر لینکِ شروع کار نکرد)
                if (! $cb && preg_match('/^\d{6}$/', P::digits($text))) {
                    $this->start($chat, $from, P::digits($text));
                } else {
                    $this->api->send($chat, "سلام 👋 من باتِ «ستاره ماه» هستم.\nبرای استفاده، اول حسابت را وصل کن:\nسایت ← منوی «🤖 بات» ← «اتصال به " . BotLink::PLATFORMS[$this->platform] . "»؛ بعد لینک را باز کن یا کدِ ۶ رقمی را همین‌جا بفرست.");
                }
                if ($cb) {
                    $this->api->answer($cb['id']);
                }

                return;
            }
            $user = User::find($link->user_id);
            if (! $user || ! BotConfig::allowed($user)) {
                $this->api->send($chat, '⛔ دسترسیِ این حساب به بات بسته است (یا بات برای مدرسه خاموش است). از مدیرِ مدرسه بپرسید.');
                if ($cb) {
                    $this->api->answer($cb['id']);
                }

                return;
            }
            $link->forceFill(['last_seen_at' => now()])->save();
            // همه‌ی کارها به نامِ همین معلم (محدوده‌ی مدرسه، ثبت‌کننده، دسترسی‌ها)
            Auth::setUser($user);

            if ($cb) {
                $this->api->answer($cb['id']);
                $this->onButton($chat, $user, (string) ($cb['data'] ?? ''), (int) ($msg['message_id'] ?? 0));
            } elseif ($text !== '') {
                $this->onText($chat, $user, $text);
            } elseif ($audio = self::audioOf($msg)) {
                // صدا پیش از عکس/فایل: «بله» گاهی پیامِ صوتی یا فایلِ صوتی را همراهِ document می‌فرستد
                $this->onVoice($chat, $user, $audio);
            } elseif (isset($msg['photo']) || isset($msg['document'])) {
                $this->quiz($chat)->onFile($user, $msg);
            } else {
                $this->menu($chat, $user);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->api->send($chat, '⚠️ خطایی پیش آمد و کار انجام نشد. دوباره امتحان کن؛ اگر تکرار شد به پشتیبانی خبر بده.');
        }
    }

    /** اتصالِ حساب با کدِ یک‌بارمصرف. */
    private function start(string $chat, array $from, ?string $code): void
    {
        $uid = $code ? Cache::pull('botlink:' . $code) : null;
        if (! $uid) {
            $linked = BotLink::where('platform', $this->platform)->where('chat_id', $chat)->exists();
            $this->api->send($chat, $linked
                ? '👋 خوش برگشتی! «منو» را بزن.'
                : "سلام 👋 من باتِ «ستاره ماه» هستم.\n" . ($code ? '⚠️ این کد معتبر نیست یا منقضی شده. ' : '') . "از سایت ← «🤖 بات» ← «اتصال به " . BotLink::PLATFORMS[$this->platform] . '» کدِ تازه بگیر و همین‌جا بفرست.',
                $linked ? $this->mainRows() : []);

            return;
        }
        $user = User::find($uid);
        if (! $user || ! BotConfig::allowed($user)) {
            $this->api->send($chat, '⛔ این حساب اجازه‌ی استفاده از بات را ندارد.');

            return;
        }
        BotLink::updateOrCreate(['platform' => $this->platform, 'chat_id' => $chat], [
            'user_id' => $user->id, 'school_id' => $user->school_id,
            'username' => isset($from['username']) ? '@' . $from['username'] : trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')),
            'last_seen_at' => now(), 'notified_at' => now(),
        ]);
        Auth::setUser($user);
        $this->api->send($chat, '✅ ' . $user->name . " عزیز، حسابت وصل شد!\n\n" . self::HELP, $this->mainRows());
    }

    public const HELP = "هر کاری داری همین‌جا بنویس؛ پیش از ثبت، پیش‌نمایش می‌بینی و تأیید می‌کنی. مثلاً:\n\n"
        . "📋 «همه حاضرن جز سارا و مریم، رضا تأخیر داشت»\n"
        . "📔 «ریاضی کسرها توصیفی: علی خیلی خوب، سارا خوب، بقیه خوب»\n"
        . "📔 «نمره علوم از ۱۰: سارا ۹، مریم ۷.۵»\n"
        . "📢 «اطلاعیه: فردا کتابِ علوم بیاورید»\n"
        . "📢 «به سارا بگو دفترش را بیاورد»\n"
        . "⭐ «به علی و سارا ۱۰ امتیاز بده به‌خاطرِ همکاری»\n"
        . "🎮 «یک بازیِ ۸ سؤالی از کسرها بساز»\n"
        . "👤 «سارا توی ریاضی چطوره؟»\n"
        . "📋 «دیروز رضا غایب بود»\n"
        . "📝 «یک آزمونِ ۱۰ سؤالی از کسرها بساز»\n"
        . "📎 عکسِ برگه‌ی نمونه‌سؤال یا صفحه‌ی کتاب (یا PDF) را بفرست تا همان سؤال‌ها، سؤالِ مشابه یا سؤال از روی متن بسازم\n"
        . "✍️ «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن» · «املای دیروز همه قابل قبول جز رضا»\n"
        . "🏆 «یک مسابقه‌ی زنده‌ی ۱۰ سؤالی از کسرها برای ساعت ۸ شب بساز»\n"
        . "📚 «سؤال‌های سه بازیِ آخر رو بده» · «با همین‌ها یک مسابقه‌ی زنده بساز» · «آزمونِ کسرها رو ببند»\n"
        . "🗄 بانکِ سؤال، 👥 دانش‌آموزان، ✍️ صفِ تصحیح، 📨 پیام‌ها و ⭐ امتیازها هم در منو هست؛ 🔔 اعلان‌های سایت همان لحظه این‌جا می‌آید\n\n🎙️ می‌توانی پیامِ صوتی هم بفرستی. یا از دکمه‌های منو استفاده کن.";

    /* ═════════════════════════ منو ═════════════════════════ */

    public function mainRows(): array
    {
        return [
            [['text' => '📋 حضور و غیاب', 'data' => 'm:att'], ['text' => '🌅 گزارشِ امروز', 'data' => 'r:today']],
            [['text' => '📔 ثبتِ نمره', 'data' => 'm:grade'], ['text' => '📢 اطلاعیه', 'data' => 'm:ann']],
            [['text' => '📝 ساختِ آزمون', 'data' => 'q:new:exam'], ['text' => '🎮 ساختِ بازی', 'data' => 'q:new:game']],
            [['text' => '🏆 مسابقه‌ی زنده', 'data' => 'q:new:live'], ['text' => '✍️ تصحیح', 'data' => 'g:home']],
            [['text' => '📚 کارهای من (آزمون، بازی، مسابقه)', 'data' => 'lb:home']],
            [['text' => '🗄 بانکِ سؤال', 'data' => 'q:bank'], ['text' => '👥 دانش‌آموزان', 'data' => 'm:stl']],
            [['text' => '📨 پیام‌ها', 'data' => 'm:msg'], ['text' => '⭐ امتیازها', 'data' => 'r:xp']],
            [['text' => '📊 گزارش‌ها', 'data' => 'm:rep'], ['text' => '⚙️ تنظیمات', 'data' => 'm:set']],
        ];
    }

    private function menu(string $chat, User $user, int $edit = 0): void
    {
        $t = '🌙 ' . $user->name . " — چه کاری انجام بدهم؟\nمی‌توانی دستور را بنویسی یا یکی از دکمه‌ها را بزنی.";
        $edit ? $this->api->edit($chat, $edit, $t, $this->mainRows()) : $this->api->send($chat, $t, $this->mainRows());
    }

    /* ═════════════════════════ متن ═════════════════════════ */

    private function onText(string $chat, User $user, string $text, bool $voice = false): void
    {
        $n = P::norm($text);
        // در انتظارِ ویرایشِ سؤال / عنوان / موضوعِ استودیوی سؤال؟
        if ($this->quiz($chat)->onText($user, $text)) {
            return;
        }
        // نمره با توضیح (صفِ تصحیح)؟
        if ($this->grading($chat)->onText($user, $text)) {
            return;
        }
        // پاسخ به پیامِ دانش‌آموز/والدین؟
        if ($mw = Cache::pull($this->key('mwait'))) {
            if (! preg_match('/^\/?(لغو|cancel)$/u', $n)) {
                $this->replyMessage($chat, $user, (int) $mw, $text);
            } else {
                $this->api->send($chat, '👌 باشد.');
            }

            return;
        }
        // در انتظارِ نامِ دانش‌آموز برای گزارش؟
        if (($w = Cache::get($this->key('wait'))) && ($w['for'] ?? '') === 'student') {
            Cache::forget($this->key('wait'));
            $this->studentReport($chat, $user, $text);

            return;
        }
        if (preg_match('/^\/?(menu|منو|start|شروع|راهنما|help|\/help)$/u', $n)) {
            $text === '/help' || $n === 'راهنما' || $n === 'help' ? $this->api->send($chat, self::HELP, $this->mainRows()) : $this->menu($chat, $user);

            return;
        }
        if (preg_match('/^(گزارش\s*امروز|امروز|صبح\s*بخیر|\/today)$/u', $n)) {
            $this->api->send($chat, $this->reports($user)->today(), $this->reportRows());

            return;
        }
        // میان‌بُرها فقط برای دستورهای کوتاه؛ جمله‌ی کامل («… که تصحیح نشده خیلی خوب بزن») با دستیار فهمیده می‌شود
        $short = mb_strlen($n) <= 28 && substr_count($n, ' ') <= 3;
        $ai = BotAgent::available($user);
        if ((! $ai || $short) && preg_match('/^(?:گزارش|وضعیت|کارنامه)\s*(?:ی|ِ)?\s+(.{2,40})$/u', trim($text), $m) && ! preg_match('/(حضور|غیاب|نمره|کلاس|امروز|هفته)/u', $m[1])) {
            $this->studentReport($chat, $user, $m[1]);

            return;
        }
        if (($short || ! $ai) && preg_match('/(منتظر\s*تصحیح|تصحیح\s*نشده|کارهای\s*مانده|^تصحیح$|^صف\s*تصحیح$)/u', $n) && ! preg_match('/(بزن|بده|بذار|بگذار)/u', $n)) {
            $this->grading($chat)->home($user);

            return;
        }
        if (($short || ! $ai) && preg_match('/^(گزارش\s*)?(حضور|غیبت)(ها)?\s*(این\s*)?هفته$/u', $n)) {
            $this->api->send($chat, $this->reports($user)->attendanceWeek(), $this->reportRows());

            return;
        }

        // «اطلاعیه: …» همیشه مستقیم؛ بقیه‌ی شکل‌ها («به سارا بگو…») اگر هوش مصنوعی هست، با او (نامِ دقیق‌تر)
        if (($a = P::announce($text)) && ($a['to'] === 'class' || ! BotAgent::available($user))) {
            $this->prepare($chat, $user, 'announce', ['to' => $a['to'], 'text' => $a['text']]);

            return;
        }
        if (preg_match('/^(کارهای\s*من|آزمون\s*ها(ی\s*من)?|بازی\s*ها(ی\s*من)?|مسابقه\s*ها(ی\s*من)?)$/u', $n)) {
            $this->library($chat)->home($user);

            return;
        }
        if (preg_match('/^(گفتگوی\s*تازه|از\s*اول|فراموش\s*کن|reset)$/u', $n)) {
            Cache::forget($this->key('conv'));
            $this->api->send($chat, '🆕 گفتگوی تازه؛ بفرما.', $this->mainRows());

            return;
        }
        // 🧠 گفتگوی آزاد با هوش مصنوعیِ سایت (اگر برای مدرسه روشن است)
        if (BotAgent::available($user)) {
            $this->agent($chat, $user, $text, $voice);

            return;
        }
        // بدونِ هوش مصنوعی: «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن»
        if ($b = BotGrading::looksBulk($text)) {
            $this->grading($chat)->bulk($user, $b);

            return;
        }
        if (P::looksGrades($text)) {
            $this->prepare($chat, $user, 'grades', ['text' => $text]);

            return;
        }
        if (P::looksAttendance($text)) {
            $this->prepare($chat, $user, 'attendance', ['text' => $text]);

            return;
        }
        $this->api->send($chat, "🤔 منظورت را دقیق نفهمیدم.\n\n" . self::HELP, $this->mainRows());
    }

    /* ═════════════════════════ دستیارِ گفتگویی ═════════════════════════ */

    /**
     * 🧠 گفتگو با دستیار: هوش مصنوعی اول داده‌ی واقعی را با ابزار می‌خواند و بعد جواب می‌دهد
     * یا یک «کار» پیشنهاد می‌کند (که پیش‌نمایش و تأیید می‌گیرد).
     */
    private function agent(string $chat, User $user, string $text, bool $voice = false): void
    {
        $this->api->call('sendChatAction', ['chat_id' => $chat, 'action' => 'typing'], 4);
        $brain = new BotBrain($user, $this);
        $d = $brain->think($text, Cache::get($this->key('conv'), []), $voice);
        $this->remember('u', $text);
        if ($d['type'] === 'error') {
            // هوش مصنوعی در دسترس نیست → فهمِ قاعده‌محور (بدونِ هوش مصنوعی)
            if ($b = BotGrading::looksBulk($text)) {
                $this->grading($chat)->bulk($user, $b);
            } elseif (P::looksGrades($text)) {
                $this->prepare($chat, $user, 'grades', ['text' => $text]);
            } elseif (P::looksAttendance($text)) {
                $this->prepare($chat, $user, 'attendance', ['text' => $text]);
            } else {
                $this->api->send($chat, '⚠️ ' . $d['text'], $this->mainRows());
            }

            return;
        }
        if ($d['type'] === 'answer') {
            $this->api->send($chat, Str::limit($d['text'], 3900), [[['text' => '🏠 منو', 'data' => 'm:main']]]);
            $this->remember('a', $d['text']);

            return;
        }
        $this->remember('a', '(پیش‌نمایشِ «' . $d['tool'] . '» نشان داده شد)');
        $this->act($chat, $user, $d['tool'], (array) $d['args'], $text, (string) ($d['text'] ?? ''));
    }

    /** «کار»ِ پیشنهادیِ دستیار → همان مسیرهای پیش‌نمایش و تأیید. */
    private function act(string $chat, User $user, string $tool, array $a, string $said, string $note = ''): void
    {
        $classes = $this->classes($user);
        $cls = ! empty($a['class']) ? $classes->first(fn ($c) => P::has((string) $a['class'], $c->name) || P::has($c->name, (string) $a['class'])) : null;
        $extra = $cls ? ['class' => $cls->id] : [];
        switch ($tool) {
            case 'record_attendance':
                $status = [];
                $warn = [];
                $all = [];
                foreach (['absent', 'late', 'excused'] as $k) {
                    $r = $this->resolveNames($user, (array) ($a[$k] ?? []), $cls);
                    foreach ($r['ids'] as $id) {
                        $status[$id] = $k;
                    }
                    $all = array_merge($all, $r['ids']);
                    if ($w = $this->namesWarn($r)) {
                        $warn[] = $w;
                    }
                }
                // کلاس از خودِ نام‌ها معلوم است (اگر همه در یک کلاس باشند)
                if (! $cls && $all && $classes->count() > 1) {
                    $hit = $classes->filter(fn ($c) => ! array_diff($all, array_keys($this->roster($c))));
                    if ($hit->count() === 1) {
                        $extra = ['class' => $hit->first()->id];
                    }
                }
                $this->prepare($chat, $user, 'attendance', ['status' => $status, 'date' => (string) ($a['date'] ?? ''), 'warn' => $warn ? implode("\n", array_unique($warn)) : null] + $extra);
                break;
            case 'record_grades':
                $head = trim(implode(' ', array_filter([$a['lesson'] ?? '', $a['topic'] ?? '', ['numeric' => 'عددی', 'descriptive' => 'توصیفی', 'homework' => 'تکلیف'][$a['type'] ?? ''] ?? '', ! empty($a['max']) ? 'از ' . $a['max'] : ''])));
                $items = [];
                $warn = [];
                foreach (array_filter((array) ($a['grades'] ?? []), 'is_array') as $g) {
                    // نامِ شنیده‌شده → املای دقیقِ فهرست (تا پارسرِ نمره پیدایش کند)
                    $r = $this->resolveNames($user, [(string) ($g['name'] ?? '')], $cls);
                    if (count($r['ids']) === 1) {
                        $items[] = $r['roster'][$r['ids'][0]] . ' ' . ($g['value'] ?? '');
                    }
                    if ($w = $this->namesWarn($r)) {
                        $warn[] = $w;
                    }
                }
                if (! empty($a['rest'])) {
                    $items[] = 'بقیه ' . $a['rest'];
                }
                if (! $items) {
                    $this->api->send($chat, '🤔 نمره‌ای برای ثبت پیدا نکردم.' . ($warn ? "\n" . implode("\n", $warn) : ''), $this->mainRows());
                    break;
                }
                $this->prepare($chat, $user, 'grades', ['text' => ($head ?: 'نمره') . ': ' . implode('، ', $items), 'warn' => $warn ? implode("\n", array_unique($warn)) : null] + $extra);
                break;
            case 'give_points':
                $this->prepare($chat, $user, 'award', ['names' => (array) ($a['names'] ?? []), 'amount' => (int) ($a['amount'] ?? 0), 'reason' => (string) ($a['reason'] ?? '')] + $extra);
                break;
            case 'record_discipline':
                $this->prepare($chat, $user, 'discipline', ['names' => (array) ($a['names'] ?? []), 'topic' => (string) ($a['topic'] ?? ''), 'note' => (string) ($a['note'] ?? ''), 'class' => 0]);
                break;
            case 'send_announcement':
                $to = trim((string) ($a['to'] ?? 'class'));
                if ($to !== 'class' && $to !== '' && ! in_array(P::norm($to), ['کلاس', 'همه', 'بچه ها', 'class'], true)) {
                    $r = $this->resolveNames($user, [$to], $cls);
                    $to = count($r['ids']) === 1 ? $r['roster'][$r['ids'][0]] : $to;
                } else {
                    $to = 'class';
                }
                $this->prepare($chat, $user, 'announce', ['to' => $to, 'text' => (string) ($a['text'] ?? '')] + $extra);
                break;
            case 'grade_submissions':
                foreach (['names', 'except'] as $k) {
                    if (! empty($a[$k])) {
                        $r = $this->resolveNames($user, (array) $a[$k]);
                        $a[$k] = array_merge(array_map(fn ($id) => $r['roster'][$id], $r['ids']), $r['missing'], array_keys($r['amb']));
                    }
                }
                $this->grading($chat)->bulk($user, $a);
                break;
            case 'create_quiz':
                $this->quiz($chat)->start($user, ['kind' => (string) ($a['kind'] ?? 'exam'), 'lesson' => (string) ($a['lesson'] ?? ''), 'topic' => (string) ($a['topic'] ?? ''),
                    'count' => (int) ($a['count'] ?? 8), 'difficulty' => (string) ($a['difficulty'] ?? 'medium')] + $extra);
                break;
            case 'build_from_items':
                $it = new BotItems($user);
                $refs = [];
                $types = isset(BotItems::TYPES[$a['from_type'] ?? '']) ? [$a['from_type']] : array_keys(BotItems::TYPES);
                foreach ($types as $t) {
                    if (! empty($a['titles'])) {
                        foreach ((array) $a['titles'] as $title) {
                            if ($m = $it->search($t, (string) $title, 1)->first()) {
                                $refs[] = [$t, $m];
                            }
                        }
                    } elseif (count($types) === 1) {
                        foreach ($it->search($t, null, max(1, min(10, (int) ($a['last_n'] ?? 1)))) as $m) {
                            $refs[] = [$t, $m];
                        }
                    }
                }
                if (! $refs) {
                    $this->api->send($chat, '🔍 ' . (! empty($a['titles']) ? 'موردی با این نام پیدا نشد: ' . implode('، ', (array) $a['titles']) : 'هنوز چیزی ساخته نشده.'), [[['text' => '📚 کارهای من', 'data' => 'lb:home']]]);
                    break;
                }
                $this->library($chat)->make($user, $refs, (string) ($a['kind'] ?? 'exam'), ($a['mode'] ?? 'copy') === 'similar' ? 'similar' : 'copy',
                    array_filter(['count' => (int) ($a['count'] ?? 0), 'shuffle' => (bool) ($a['shuffle'] ?? false), 'title' => (string) ($a['title'] ?? ''), 'class' => $cls?->id]));
                break;
            case 'open_item':
                $it = new BotItems($user);
                $t = isset(BotItems::TYPES[$a['type'] ?? '']) ? $a['type'] : 'exam';
                $m = $it->search($t, (string) ($a['title'] ?? ''), 1)->first();
                $m ? $this->library($chat)->onButton($user, "lb:v:{$t}:{$m->id}", 0) : $this->library($chat)->onButton($user, "lb:l:{$t}:0", 0);
                break;
            case 'open_screen':
                match ((string) ($a['screen'] ?? 'menu')) {
                    'library' => $this->library($chat)->home($user),
                    'grading_queue' => isset(BotGrading::TYPES[['reading' => 'r', 'dictation' => 'd', 'worksheet' => 'w'][$a['kind'] ?? ''] ?? ''])
                        ? $this->grading($chat)->next($user, ['reading' => 'r', 'dictation' => 'd', 'worksheet' => 'w'][$a['kind']]) : $this->grading($chat)->home($user),
                    'messages' => $this->inbox($chat, $user, 0),
                    'students' => $this->api->send($chat, '👥 کدام کلاس؟', $classes->map(fn ($c) => [['text' => '🏫 ' . $c->name, 'data' => 'sl:' . $c->id]])->values()->all()),
                    'question_bank' => $this->quiz($chat)->onButton($user, 'q:bank', 0),
                    'attendance_board' => $cls || $classes->count() === 1 ? $this->board($chat, $user, ($cls ?? $classes->first())->id, 0)
                        : $this->api->send($chat, '📋 کدام کلاس؟', $classes->map(fn ($c) => [['text' => '🏫 ' . $c->name, 'data' => 'bd:' . $c->id]])->values()->all()),
                    'reports' => $this->api->send($chat, '📊 کدام گزارش؟', $this->reportRows()),
                    'settings' => $this->settings($chat, $user, 0),
                    default => $this->menu($chat, $user),
                };
                break;
            case 'legacy_report':
                $this->report($chat, $user, (string) ($a['kind'] ?? 'today'));
                break;
            case 'legacy_student':
                $this->studentReport($chat, $user, (string) ($a['name'] ?? ''), (string) ($a['question'] ?? $said));
                break;
            default:
                $this->api->send($chat, $note !== '' ? $note : self::HELP, $this->mainRows());
        }
    }

    /**
     * حافظه‌ی کوتاهِ گفتگو (۸ پیامِ آخر، ۳۰ دقیقه) — برای «بله، همین کار را بکن» یا «سارا چی؟».
     * کوتاه نگه داشته می‌شود تا موضوعِ قبلی روی دستورِ تازه اثرِ نابه‌جا نگذارد.
     */
    private function remember(string $who, string $text): void
    {
        $h = Cache::get($this->key('conv'), []);
        $h[] = [$who, Str::limit($text, 500)];
        Cache::put($this->key('conv'), array_slice($h, -8), now()->addMinutes(30));
    }

    /** پیامِ صوتی، فایلِ صوتی یا ویدیوی دایره‌ای (هر شکلی که پیام‌رسان بفرستد). */
    public static function audioOf(array $msg): ?array
    {
        foreach (['voice', 'audio', 'video_note'] as $k) {
            if (! empty($msg[$k]['file_id'])) {
                return $msg[$k];
            }
        }
        $d = $msg['document'] ?? null;
        if (! empty($d['file_id'])) {
            $mime = strtolower((string) ($d['mime_type'] ?? ''));
            $name = strtolower((string) ($d['file_name'] ?? ''));
            if (str_starts_with($mime, 'audio/') || preg_match('/\.(ogg|oga|opus|mp3|m4a|aac|wav|amr|webm|flac|3gp)$/', $name)) {
                return $d;
            }
        }

        return null;
    }

    private function onVoice(string $chat, User $user, array $voice): void
    {
        $this->api->call('sendChatAction', ['chat_id' => $chat, 'action' => 'typing'], 4);
        $r = BotVoice::fromMessage($this->platform, $voice, $this->voiceHint($user));
        if (! $r['ok']) {
            $this->api->send($chat, '🎙️ ' . $r['error'], $this->mainRows());

            return;
        }
        $this->api->send($chat, '🎙️ شنیدم: «' . Str::limit($r['text'], 600) . "»\n(اگر درست نشنیدم، دوباره بگو یا بنویس.)");
        $this->onText($chat, $user, $r['text'], true);
    }

    /** نامِ بچه‌ها و درس‌ها برای مدلِ صدا به متن (املای درستِ نام‌ها). */
    private function voiceHint(User $user): string
    {
        $names = [];
        $subjects = [];
        foreach ($this->classes($user) as $c) {
            foreach ($this->roster($c) as $n) {
                $names[] = $n;
            }
            foreach (collect(rescue(fn () => $c->subjectNames(), [], false))->pluck('name') as $sub) {
                $subjects[] = $sub;
            }
        }
        $txt = implode('، ', array_unique($names));
        if (mb_strlen($txt) > 520) {
            // کلاسِ بزرگ: نام‌های کوچک و خانوادگیِ یکتا (کوتاه‌تر)
            $parts = [];
            foreach ($names as $n) {
                foreach (preg_split('/\s+/u', $n) as $w) {
                    $parts[$w] = true;
                }
            }
            $txt = implode('، ', array_keys($parts));
        }

        return mb_substr($txt, 0, 520) . ($subjects ? ' — درس‌ها: ' . implode('، ', array_unique($subjects)) : '');
    }

    /* ═════════════════════════ دکمه‌ها ═════════════════════════ */

    private function onButton(string $chat, User $user, string $data, int $msgId): void
    {
        [$kind, $a, $b] = array_pad(explode(':', $data, 3), 3, null);
        switch ($kind) {
            case 'm':
                $this->submenu($chat, $user, (string) $a, $msgId);

                return;
            case 'g':    // ✍️ تصحیح
                $this->grading($chat)->onButton($user, $data, $msgId);

                return;
            case 'msg':  // 📨 پیام‌ها: msg:r:فرستنده (پاسخ) · msg:k:فرستنده (خواندم)
                if ($a === 'r') {
                    Cache::put($this->key('mwait'), (int) $b, now()->addMinutes(10));
                    $this->api->send($chat, '✍️ پاسخت را بنویس (برای انصراف «لغو»):');
                } else {
                    \App\Models\Message::where('recipient_id', $user->id)->where('sender_id', (int) $b)->whereNull('read_at')->update(['read_at' => now()]);
                    $this->inbox($chat, $user, $msgId);
                }

                return;
            case 'lb':   // 📚 کارهای من
                $this->library($chat)->onButton($user, $data, $msgId);

                return;
            case 'q':    // استودیوی سؤال، آزمون و بازی
                $this->quiz($chat)->onButton($user, $data, $msgId);

                return;
            case 'r':
                $this->report($chat, $user, (string) $a);

                return;
            case 'ok':   // تأییدِ کارِ آماده
                $this->confirm($chat, $user, (string) $a, $msgId);

                return;
            case 'no':
                Cache::forget($this->key('p:' . $a));
                $this->api->edit($chat, $msgId, '❌ لغو شد؛ چیزی ثبت نشد.', $this->mainRows());

                return;
            case 'c':    // انتخابِ کلاس برای کارِ در انتظار
                $p = Cache::pull($this->key('p:' . $a));
                if ($p) {
                    $this->prepare($chat, $user, $p['action'], ['class' => (int) $b] + $p['params'], $msgId);
                }

                return;
            case 'bd':   // صفحه‌ی دکمه‌ای حضور و غیاب
                $this->board($chat, $user, (int) $a, $msgId);

                return;
            case 'bt':   // تغییرِ وضعیتِ یک دانش‌آموز روی صفحه
                $this->boardToggle($chat, $user, (string) $a, (int) $b, $msgId);

                return;
            case 'ba':   // همه حاضر
                $this->boardAll($chat, $user, (string) $a, $msgId);

                return;
            case 'bs':   // ذخیره‌ی صفحه → پیش‌نمایش/تأیید
                $s = Cache::get($this->key('b:' . $a));
                if ($s) {
                    $this->prepare($chat, $user, 'attendance', ['class' => $s['class'], 'status' => $s['st']], $msgId);
                }

                return;
            case 'un':
                $this->undo($chat, $user, (int) $a, $msgId);

                return;
            case 'st':   // تنظیمات: گزارشِ صبح / اعلان‌ها
                $this->toggleSetting($chat, $user, (string) $a, $msgId);

                return;
            case 'sl':   // فهرستِ دانش‌آموزانِ یک کلاس
                $this->studentList($chat, $user, (int) $a, $msgId);

                return;
            case 'stu':  // گزارشِ یک دانش‌آموز (پس از انتخاب از فهرست)
                $s = User::find((int) $a);
                if ($s && $this->rosterAll($user)->has($s->id)) {
                    $this->api->send($chat, $this->reports($user)->student($s), $this->reportRows());
                }

                return;
        }
        $this->menu($chat, $user);
    }

    private function submenu(string $chat, User $user, string $which, int $msgId): void
    {
        switch ($which) {
            case 'main':
                $this->menu($chat, $user, $msgId);
                break;
            case 'att':
                $classes = $this->classes($user);
                if ($classes->count() === 1) {
                    $this->board($chat, $user, $classes->first()->id, 0);
                } else {
                    $this->api->edit($chat, $msgId, '📋 حضور و غیابِ امروز — کدام کلاس؟', array_merge(
                        $classes->map(fn ($c) => [['text' => '🏫 ' . $c->name, 'data' => 'bd:' . $c->id]])->all(),
                        [[['text' => '↩️ منو', 'data' => 'm:main']]]
                    ));
                }
                $this->api->send($chat, "💡 راهِ سریع‌تر: فقط بنویس «همه حاضرن جز سارا و مریم، رضا تأخیر داشت».");
                break;
            case 'grade':
                $this->api->edit($chat, $msgId, "📔 ثبتِ نمره — به این شکل بنویس:\n\n"
                    . "«ریاضی کسرها توصیفی: علی خیلی خوب، سارا خوب، بقیه خوب»\n"
                    . "«نمره علوم گیاهان از ۱۰: سارا ۹، مریم ۷.۵»\n"
                    . "«تکلیفِ فارسی: رضا ناقص، مریم انجام نداده، بقیه کامل»\n\n"
                    . "ارزیابی‌ها: خیلی خوب، خوب، قابل قبول، نیاز به تلاش — تکلیف: کامل، ناقص، انجام نداده.\nپیش از ثبت، پیش‌نمایش را می‌بینی.",
                    [[['text' => '↩️ منو', 'data' => 'm:main']]]);
                break;
            case 'ann':
                $this->api->edit($chat, $msgId, "📢 اطلاعیه — بنویس:\n\n«اطلاعیه: فردا کتابِ علوم بیاورید» (برای همه‌ی کلاس)\n«به سارا بگو دفترش را بیاورد» (برای یک دانش‌آموز)",
                    [[['text' => '↩️ منو', 'data' => 'm:main']]]);
                break;
            case 'rep':
                $this->api->edit($chat, $msgId, '📊 کدام گزارش؟', $this->reportRows());
                break;
            case 'msg':
                $this->inbox($chat, $user, $msgId);
                break;
            case 'stl':   // 👥 فهرستِ دانش‌آموزان
                $classes = $this->classes($user);
                if ($classes->count() === 1) {
                    $this->studentList($chat, $user, $classes->first()->id, $msgId);
                } elseif ($classes->isEmpty()) {
                    $this->api->edit($chat, $msgId, '🏫 هنوز کلاسی به نامِ تو ثبت نشده.', [[['text' => '↩️ منو', 'data' => 'm:main']]]);
                } else {
                    $this->api->edit($chat, $msgId, '👥 فهرستِ دانش‌آموزان — کدام کلاس؟', array_merge(
                        $classes->map(fn ($c) => [['text' => '🏫 ' . $c->name, 'data' => 'sl:' . $c->id]])->values()->all(),
                        [[['text' => '↩️ منو', 'data' => 'm:main']]]
                    ));
                }
                break;
            case 'stu':
                Cache::put($this->key('wait'), ['for' => 'student'], now()->addMinutes(10));
                $this->api->send($chat, '👤 نامِ دانش‌آموز را بنویس (نام یا نامِ خانوادگی):');
                break;
            case 'set':
                $this->settings($chat, $user, $msgId);
                break;
        }
    }

    /** 👥 فهرستِ دانش‌آموزانِ کلاس؛ هر نام دکمه‌ی گزارشِ همان دانش‌آموز است. */
    private function studentList(string $chat, User $user, int $classId, int $msgId): void
    {
        $class = $this->classes($user)->firstWhere('id', $classId);
        if (! $class) {
            return;
        }
        $roster = $this->roster($class);
        $L = ['👥 ' . $class->name . ' — ' . Jalali::fa((string) count($roster)) . ' دانش‌آموز', ''];
        $i = 0;
        foreach ($roster as $name) {
            $L[] = Jalali::fa((string) ++$i) . '. ' . $name;
        }
        $L[] = '';
        $L[] = $roster ? 'روی نامِ هر دانش‌آموز بزن تا کارنامه‌ی کوتاهش را ببینی.' : 'این کلاس هنوز دانش‌آموزی ندارد.';
        $rows = collect($roster)->map(fn ($n, $id) => ['text' => Str::limit($n, 22), 'data' => 'stu:' . $id])->chunk(2)->map(fn ($c) => $c->values()->all())->values()->take(45)->all();
        $rows[] = [['text' => '📋 حضور و غیاب', 'data' => 'bd:' . $class->id], ['text' => '↩️ منو', 'data' => 'm:main']];
        $this->api->edit($chat, $msgId, Str::limit(implode("\n", $L), 4000), $rows);
    }

    private function reportRows(): array
    {
        return [
            [['text' => '🌅 امروز', 'data' => 'r:today'], ['text' => '📋 حضورِ این هفته', 'data' => 'r:attendance']],
            [['text' => '📔 نمره‌های اخیر', 'data' => 'r:grades'], ['text' => '⚠️ نیازمندِ توجه', 'data' => 'r:risk']],
            [['text' => '✍️ منتظرِ تصحیح', 'data' => 'g:home'], ['text' => '👤 یک دانش‌آموز', 'data' => 'm:stu']],
            [['text' => '⭐ امتیازها', 'data' => 'r:xp'], ['text' => '📨 پیام‌ها', 'data' => 'm:msg']],
            [['text' => '↩️ منو', 'data' => 'm:main']],
        ];
    }

    private function report(string $chat, User $user, string $which): void
    {
        $r = $this->reports($user);
        $text = match ($which) {
            'attendance' => $r->attendanceWeek(),
            'grades' => $r->recentGrades(),
            'risk' => $r->risk(),
            'pending' => null,
            'xp' => $r->xp(),
            default => $r->today(),
        };
        if ($text === null) {
            $this->grading($chat)->home($user);

            return;
        }
        $this->api->send($chat, $text, $this->reportRows());
    }

    private function studentReport(string $chat, User $user, string $name, string $question = ''): void
    {
        $roster = $this->rosterAll($user)->all();
        $r = P::namesIn($name, $roster);
        $cands = $r['ids'] ?: collect($r['ambiguous'])->flatten()->map(fn ($n) => array_search($n, $roster, true))->filter()->values()->all();
        if (count($cands) === 1) {
            $s = User::find($cands[0]);
            $facts = $this->reports($user)->student($s);
            // سؤالِ مشخص («توی ریاضی چطوره؟») → پاسخِ طبیعی از روی داده‌های واقعی
            if ($question !== '' && BotAgent::available($user)) {
                $more = rescue(function () use ($s) {
                    $ins = app(\App\Support\StudentInsight::class);
                    $p = $ins->profile($s);

                    return $ins->asText($p, $ins->analysis($p));
                }, '', false);
                $ans = BotAgent::answerAboutStudent($question, $facts . "\n\n" . Str::limit((string) $more, 3000));
                if ($ans) {
                    $this->remember('a', $ans);
                    $this->api->send($chat, '👤 ' . $s->name . "\n\n" . $ans, [[['text' => '📄 گزارشِ کامل', 'data' => 'stu:' . $s->id], ['text' => '🏠 منو', 'data' => 'm:main']]]);

                    return;
                }
            }
            $this->api->send($chat, $facts, $this->reportRows());
        } elseif ($cands) {
            $this->api->send($chat, 'کدام یک؟', array_map(fn ($id) => [['text' => '👤 ' . $roster[$id], 'data' => 'stu:' . $id]], array_slice($cands, 0, 8)));
        } else {
            $this->api->send($chat, '🔍 دانش‌آموزی با نامِ «' . Str::limit($name, 40) . '» در کلاس‌هایت پیدا نشد.', $this->reportRows());
        }
    }

    private function settings(string $chat, User $user, int $msgId): void
    {
        $l = BotLink::where('platform', $this->platform)->where('chat_id', $chat)->first();
        $t = "⚙️ تنظیماتِ بات\n\n🌅 گزارشِ صبحگاهی (ساعتِ ۷): " . ($l->brief ? 'روشن ✅' : 'خاموش') . "\n🔔 اعلان‌های تازه‌ی سایت: " . ($l->notify ? 'روشن ✅' : 'خاموش')
            . "\n\nبرای قطعِ اتصال: سایت ← «🤖 بات».";
        $rows = [
            [['text' => ($l->brief ? '🔕 خاموش‌کردنِ گزارشِ صبح' : '🌅 روشن‌کردنِ گزارشِ صبح'), 'data' => 'st:brief']],
            [['text' => ($l->notify ? '🔕 خاموش‌کردنِ اعلان‌ها' : '🔔 روشن‌کردنِ اعلان‌ها'), 'data' => 'st:notify']],
            [['text' => '↩️ منو', 'data' => 'm:main']],
        ];
        $msgId ? $this->api->edit($chat, $msgId, $t, $rows) : $this->api->send($chat, $t, $rows);
    }

    private function toggleSetting(string $chat, User $user, string $which, int $msgId): void
    {
        $l = BotLink::where('platform', $this->platform)->where('chat_id', $chat)->first();
        if ($l && in_array($which, ['brief', 'notify'], true)) {
            $l->forceFill([$which => ! $l->{$which}])->save();
        }
        $this->settings($chat, $user, $msgId);
    }

    /* ═════════════════════════ آماده‌سازی و پیش‌نمایش ═════════════════════════ */

    /**
     * یک کار را آماده می‌کند: کلاس (اگر چند کلاس و نامشخص → می‌پرسد)، تطبیقِ نام‌ها، پیش‌نمایش + دکمه‌ها.
     */
    private function prepare(string $chat, User $user, string $action, array $params, int $edit = 0): void
    {
        $classes = $this->classes($user);
        if ($classes->isEmpty()) {
            $this->api->send($chat, '🏫 هنوز کلاسی به نامِ تو ثبت نشده.');

            return;
        }
        // نامِ کلاس در متن آمده؟
        if (empty($params['class']) && $classes->count() > 1 && ! empty($params['text'])) {
            $hit = $classes->filter(fn ($c) => P::has($params['text'], $c->name));
            if ($hit->count() === 1) {
                $params['class'] = $hit->first()->id;
            }
        }
        // اطلاعیه برای یک دانش‌آموز / انضباط / امتیاز به نام‌های مشخص: کلاس از خودِ دانش‌آموزان معلوم می‌شود
        if (($action === 'announce' && ($params['to'] ?? 'class') !== 'class') || $action === 'discipline'
            || ($action === 'award' && ! in_array('همه', (array) ($params['names'] ?? []), true) && ! empty($params['names']))) {
            $params['class'] = $params['class'] ?? 0;
        } elseif (empty($params['class'])) {
            if ($classes->count() === 1) {
                $params['class'] = $classes->first()->id;
            } else {
                $tok = $this->put(['action' => $action, 'params' => $params]);
                $rows = $classes->map(fn ($c) => [['text' => '🏫 ' . $c->name, 'data' => 'c:' . $tok . ':' . $c->id]])->all();
                $rows[] = [['text' => '❌ لغو', 'data' => 'no:' . $tok]];
                $edit ? $this->api->edit($chat, $edit, 'برای کدام کلاس؟', $rows) : $this->api->send($chat, 'برای کدام کلاس؟', $rows);

                return;
            }
        }
        $class = $params['class'] ? $classes->firstWhere('id', (int) $params['class']) : null;
        if ($params['class'] && ! $class) {
            $this->api->send($chat, '⛔ به این کلاس دسترسی نداری.');

            return;
        }

        [$text, $payload, $warn] = match ($action) {
            'attendance' => $this->previewAttendance($class, $params),
            'grades' => $this->previewGrades($class, $params),
            'announce' => $this->previewAnnounce($user, $class, $params),
            'award' => $this->previewAward($user, $class, $params),
            'discipline' => $this->previewDiscipline($user, $params),
        };
        if ($payload === null) {
            $edit ? $this->api->edit($chat, $edit, $text, $this->mainRows()) : $this->api->send($chat, $text, $this->mainRows());

            return;
        }
        $tok = $this->put(['action' => $action, 'payload' => $payload, 'class' => $class?->id]);
        $rows = [[['text' => '✅ تأیید و ثبت', 'data' => 'ok:' . $tok], ['text' => '❌ لغو', 'data' => 'no:' . $tok]]];
        if ($action === 'attendance' && ($payload['date'] ?? now()->toDateString()) === now()->toDateString()) {
            $rows[] = [['text' => '✏️ ویرایش با دکمه‌ها', 'data' => 'bd:' . $class->id]];
        }
        $msg = $text . ($warn ? "\n\n" . $warn : '');
        $edit ? $this->api->edit($chat, $edit, $msg, $rows) : $this->api->send($chat, $msg, $rows);
    }

    private function previewAttendance(Classroom $class, array $params): array
    {
        $roster = $this->roster($class);
        if (! $roster) {
            return ['🏫 این کلاس هنوز دانش‌آموزی ندارد.', null, null];
        }
        if (isset($params['status'])) {
            // هر کس در فهرست نیامده «حاضر»
            $status = array_replace(array_fill_keys(array_keys($roster), 'present'), array_intersect_key(array_map('strval', (array) $params['status']), $roster));
            $amb = [];
        } else {
            $p = P::attendance($params['text'] ?? '', $roster);
            $status = $p['status'];
            $amb = $p['ambiguous'];
        }
        $date = self::date((string) ($params['date'] ?? ''));
        if ($date === null) {
            return ['⚠️ تاریخ معتبر نیست؛ فقط امروز و ۳۰ روزِ گذشته را می‌شود ثبت کرد.', null, null];
        }
        $isToday = $date->isToday();
        $by = fn ($s) => collect($status)->filter(fn ($v) => $v === $s)->keys()->map(fn ($id) => $roster[$id]);
        $L = ['📋 حضور و غیابِ ' . ($isToday ? 'امروز' : '⚠️ ' . Jalali::format($date, true)) . ' — ' . $class->name . ($isToday ? ' (' . Jalali::format(now(), true) . ')' : ''), ''];
        $L[] = '✅ حاضر: ' . Jalali::fa((string) $by('present')->count());
        foreach (['absent' => '❌ غایب', 'late' => '⏰ تأخیر', 'excused' => '📝 غیبتِ موجه'] as $k => $label) {
            if ($by($k)->isNotEmpty()) {
                $L[] = $label . ' (' . Jalali::fa((string) $by($k)->count()) . '): ' . $by($k)->implode('، ');
            }
        }
        $L[] = '';
        $L[] = 'ℹ️ هر کس نامش نیامده «حاضر» ثبت می‌شود. برای غایب‌ها اعلان (و اگر روشن باشد پیامکِ والدین) می‌رود.';
        $warn = $amb ? '⚠️ این نام‌ها مبهم‌اند و ثبت نشدند (نامِ کامل بنویس یا «ویرایش با دکمه‌ها»): '
            . collect($amb)->map(fn ($list, $w) => '«' . $w . '» = ' . implode(' / ', $list))->implode('؛ ') : null;
        if (! empty($params['warn'])) {
            $warn = trim(($warn ? $warn . "\n" : '') . $params['warn']);
        }
        $existing = AttendanceRecord::withoutGlobalScopes()->where('classroom_id', $class->id)->whereDate('date', $date->toDateString())->count();
        if ($existing) {
            $warn = trim(($warn ? $warn . "\n" : '') . '✏️ حضور و غیابِ این روزِ این کلاس قبلاً ثبت شده؛ با تأیید، به‌روز می‌شود.');
        }

        return [implode("\n", $L), ['status' => $status, 'date' => $date->toDateString()], $warn];
    }

    private function previewGrades(Classroom $class, array $params): array
    {
        $roster = $this->roster($class);
        $lessons = collect(rescue(fn () => $class->subjectNames(), [], false))->pluck('name')->all();
        $g = P::grades($params['text'] ?? '', $roster, $lessons);
        if (! $g['grades']) {
            return ["🤔 نمره‌ای پیدا نکردم. به این شکل بنویس:\n«ریاضی کسرها توصیفی: علی خیلی خوب، سارا خوب، بقیه خوب»", null, null];
        }
        $typeLabel = ['numeric' => '🔢 عددی از ' . Jalali::fa((string) $g['max']), 'descriptive' => '📝 توصیفی', 'homework' => '📚 تکلیف'][$g['type']];
        $L = ['📔 ثبت در دفترِ نمره — ' . $class->name, '📖 درس: ' . ($g['lesson'] ?: '— (نامشخص)') . ($g['topic'] ? ' · موضوع: ' . $g['topic'] : '') . ' · ' . $typeLabel, ''];
        foreach ($g['grades'] as $id => $v) {
            $L[] = '• ' . $roster[$id] . ': ' . ($g['type'] === 'numeric' ? Jalali::fa((string) $v['value']) : $v['value']);
        }
        $miss = array_diff_key($roster, $g['grades']);
        $warn = [];
        if ($miss) {
            $warn[] = 'ℹ️ بدونِ نمره (' . Jalali::fa((string) count($miss)) . '): ' . Str::limit(implode('، ', $miss), 300);
        }
        if ($g['ambiguous']) {
            $warn[] = '⚠️ نام‌های مبهم ثبت نشدند: ' . collect($g['ambiguous'])->map(fn ($l, $w) => '«' . $w . '» = ' . implode(' / ', $l))->implode('؛ ');
        }
        if (! $g['lesson']) {
            $warn[] = '💡 درس را هم بنویس تا اثرش در تسلطِ همان درس حساب شود.';
        }
        if ($g['type'] === 'numeric' && collect($g['grades'])->contains(fn ($v) => $v['value'] > $g['max'])) {
            return ['⚠️ بعضی نمره‌ها از بارمِ ' . Jalali::fa((string) $g['max']) . ' بیشترند؛ دوباره بنویس (مثلاً «از ۲۰»).', null, null];
        }

        if (! empty($params['warn'])) {
            $warn[] = $params['warn'];
        }

        return [implode("\n", $L), $g, $warn ? implode("\n", array_unique($warn)) : null];
    }

    private function previewAnnounce(User $user, ?Classroom $class, array $params): array
    {
        $text = trim((string) ($params['text'] ?? ''));
        if (mb_strlen($text) < 2) {
            return ['متنِ اطلاعیه خالی است. مثلاً: «اطلاعیه: فردا کتابِ علوم بیاورید»', null, null];
        }
        if (($params['to'] ?? 'class') === 'class') {
            $ids = array_keys($this->roster($class));

            return ['📢 اطلاعیه برای کلاسِ ' . $class->name . ' (' . Jalali::fa((string) count($ids)) . " دانش‌آموز)\n\n«" . $text . '»', ['to' => 'class', 'ids' => $ids, 'text' => $text], null];
        }
        $roster = $this->rosterAll($user)->all();
        $r = P::namesIn((string) $params['to'], $roster);
        if (! $r['ids'] && ! $r['ambiguous'] && ($f = \App\Support\Bot\NameMatch::best((string) $params['to'], $roster)) && $f['id']) {
            $r['ids'] = [$f['id']];
        }
        if (count($r['ids']) !== 1) {
            return [$r['ambiguous'] ? '⚠️ «' . $params['to'] . '» چند نفرند: ' . collect($r['ambiguous'])->flatten()->implode('، ') . ' — نامِ کامل بنویس.'
                : '🔍 دانش‌آموزی با نامِ «' . $params['to'] . '» پیدا نشد.', null, null];
        }
        $sid = $r['ids'][0];

        return ['📢 پیام برای ' . $roster[$sid] . "\n\n«" . $text . '»', ['to' => 'student', 'ids' => [$sid], 'text' => $text], null];
    }

    /** نام‌ها → شناسه‌ها (از همه‌ی کلاس‌های معلم) + نام‌های مبهم/پیدانشده. */
    private function resolveNames(User $user, array $names, ?Classroom $class = null): array
    {
        $roster = $class ? $this->roster($class) : $this->rosterAll($user)->all();
        if (in_array('همه', array_map(fn ($n) => P::norm((string) $n), $names), true)) {
            return ['ids' => array_keys($roster), 'amb' => [], 'missing' => [], 'roster' => $roster];
        }
        $ids = [];
        $amb = [];
        $missing = [];
        $guessed = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n === '') {
                continue;
            }
            $r = P::namesIn($n, $roster);
            if ($r['ids']) {
                $ids = array_merge($ids, $r['ids']);
            } elseif ($r['ambiguous']) {
                $amb += $r['ambiguous'];
            } else {
                // خطای صدا/تایپ: «ظهرا» ← «زهرا رضایی» (فقط اگر شباهت بالا و بی‌رقیب باشد)
                $f = \App\Support\Bot\NameMatch::best($n, $roster);
                if ($f['id']) {
                    $ids[] = $f['id'];
                    if (P::norm($n) !== P::norm($roster[$f['id']])) {
                        $guessed[$n] = $roster[$f['id']];
                    }
                } elseif ($f['ambiguous']) {
                    $amb[$n] = $f['ambiguous'];
                } else {
                    $missing[] = $n;
                }
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'amb' => $amb, 'missing' => $missing, 'guessed' => $guessed, 'roster' => $roster];
    }

    private function namesWarn(array $r): ?string
    {
        $w = [];
        if ($r['amb']) {
            $w[] = '⚠️ مبهم (نامِ کامل بنویس): ' . collect($r['amb'])->map(fn ($l, $k) => '«' . $k . '» = ' . implode(' / ', $l))->implode('؛ ');
        }
        if ($r['missing']) {
            $w[] = '🔍 پیدا نشد: ' . implode('، ', $r['missing']);
        }
        if (! empty($r['guessed'])) {
            $w[] = '🔤 نزدیک‌ترین نام را گذاشتم: ' . collect($r['guessed'])->map(fn ($to, $from) => '«' . $from . '» ← ' . $to)->implode('، ') . ' (اگر اشتباه است «لغو» بزن)';
        }

        return $w ? implode("\n", $w) : null;
    }

    private function previewAward(User $user, ?Classroom $class, array $p): array
    {
        $amount = (int) ($p['amount'] ?? 0);
        if ($amount === 0 || abs($amount) > 50) {
            return ['⚠️ مقدارِ امتیاز باید بینِ ۱ تا ۵۰ باشد (برای کم کردن: منفی). مثلاً «به سارا ۱۰ امتیاز بده».', null, null];
        }
        $r = $this->resolveNames($user, (array) ($p['names'] ?? []), $class);
        if (! $r['ids']) {
            return ['🤔 دانش‌آموزی برای امتیاز پیدا نشد.' . (($w = $this->namesWarn($r)) ? "\n" . $w : ''), null, null];
        }
        $reason = trim((string) ($p['reason'] ?? '')) ?: ($amount > 0 ? 'تشویقِ معلم' : 'کسرِ امتیاز');
        $names = collect($r['ids'])->map(fn ($id) => $r['roster'][$id])->implode('، ');
        $text = ($amount > 0 ? '⭐ +' : '➖ ') . Jalali::fa((string) abs($amount)) . ' امتیاز برای ' . Jalali::fa((string) count($r['ids'])) . " نفر\n" . Str::limit($names, 900)
            . "\n📝 دلیل: " . $reason . "\n\nبچه‌ها اعلانِ امتیاز را هم می‌بینند.";

        return [$text, ['ids' => $r['ids'], 'amount' => $amount, 'reason' => $reason], $this->namesWarn($r)];
    }

    private function doAward(User $user, array $p): array
    {
        $game = app(\App\Services\GamificationService::class);
        $xp = [];
        $given = 0;
        foreach (User::whereIn('id', $p['ids'])->get() as $st) {
            $amt = \App\Support\XpRules::apply($st, 'teacher_manual', (int) $p['amount']);
            if ($amt === 0) {
                continue;
            }
            $e = $game->award($st, $amt, ($p['amount'] > 0 ? '⭐ ' : '➖ ') . $p['reason'], $user, null, null, true);
            $xp[] = $e->id;
            $given = $amt;
        }
        if (! $xp) {
            throw ValidationException::withMessages(['xp' => 'امتیازدهیِ دستی در مدرسه‌ی شما توسطِ مدیر خاموش است.']);
        }
        $cap = $given !== (int) $p['amount'] ? ' (سقفِ مدرسه ' . Jalali::fa((string) abs($given)) . ' است)' : '';

        return [
            '✅ ' . ($given > 0 ? '+' : '') . Jalali::fa((string) $given) . ' امتیاز برای ' . Jalali::fa((string) count($xp)) . ' نفر ثبت شد' . $cap . '.',
            ['xp' => $xp],
            'امتیاز ' . $given . ' برای ' . count($xp) . ' نفر — ' . $p['reason'],
        ];
    }

    private function previewDiscipline(User $user, array $p): array
    {
        $topics = \App\Models\DisciplineTopic::where('school_id', $user->school_id)->where('is_active', true)->get();
        if ($topics->isEmpty()) {
            return ['📒 هنوز موضوعِ انضباطی برای مدرسه تعریف نشده؛ از سایت ← «دفترِ انضباط» بسازید. فعلاً می‌توانی «امتیاز» بدهی.', null, null];
        }
        $want = P::norm((string) ($p['topic'] ?? ''));
        $topic = $topics->first(fn ($t) => P::norm($t->name) === $want) ?? $topics->first(fn ($t) => $want !== '' && (str_contains(P::norm($t->name), $want) || str_contains($want, P::norm($t->name))));
        if (! $topic) {
            return ["🤔 موضوعِ انضباطی را پیدا نکردم. موضوع‌های مدرسه:\n" . $topics->map(fn ($t) => '• ' . $t->name . ' (' . ($t->points > 0 ? '+' : '') . Jalali::fa((string) $t->points) . ')')->implode("\n")
                . "\n\nمثلاً: «برای سارا همکاری در کلاس ثبت کن».", null, null];
        }
        $r = $this->resolveNames($user, (array) ($p['names'] ?? []));
        if (! $r['ids']) {
            return ['🤔 دانش‌آموزی پیدا نشد.' . (($w = $this->namesWarn($r)) ? "\n" . $w : ''), null, null];
        }
        $text = ($topic->kind === 'positive' ? '🌟 تشویق' : '⚠️ تذکر') . ' — ' . $topic->name . ' (' . ($topic->points > 0 ? '+' : '') . Jalali::fa((string) $topic->points) . " امتیاز)\n"
            . collect($r['ids'])->map(fn ($id) => '• ' . $r['roster'][$id])->implode("\n")
            . (! empty($p['note']) ? "\n📝 " . $p['note'] : '') . "\n\nدر دفترِ انضباط ثبت می‌شود" . ($topic->kind === 'positive' ? '.' : ' و طبقِ تنظیمِ مدرسه برای والدین پیامک می‌رود.');

        return [$text, ['topic' => $topic->id, 'ids' => $r['ids'], 'note' => Str::limit((string) ($p['note'] ?? ''), 250)], $this->namesWarn($r)];
    }

    private function doDiscipline(User $user, array $p): array
    {
        $start = now()->subSecond();
        $this->callSite($user, \App\Http\Controllers\Teacher\DisciplineController::class, 'record',
            ['topic_ids' => [$p['topic']], 'student_ids' => $p['ids'], 'note' => $p['note'] ?: null]);
        $records = \App\Models\DisciplineRecord::withoutGlobalScopes()->where('recorded_by', $user->id)->whereIn('student_id', $p['ids'])
            ->where('topic_id', $p['topic'])->where('created_at', '>=', $start)->pluck('id')->all();
        $xp = XpEntry::where('awarded_by', $user->id)->whereIn('student_id', $p['ids'])->where('created_at', '>=', $start)->pluck('id')->all();

        return ['✅ در دفترِ انضباط برای ' . Jalali::fa((string) count($p['ids'])) . ' نفر ثبت شد.', ['records' => $records, 'xp' => $xp],
            'انضباط: ' . count($p['ids']) . ' نفر'];
    }

    /* ═════════════════════════ اجرا ═════════════════════════ */

    private function confirm(string $chat, User $user, string $tok, int $msgId): void
    {
        $p = Cache::pull($this->key('p:' . $tok));
        if (! $p || empty($p['payload'])) {
            $this->api->edit($chat, $msgId, '⌛ این پیش‌نمایش منقضی شده؛ دوباره بنویس.', $this->mainRows());

            return;
        }
        $class = $p['class'] ? $this->classes($user)->firstWhere('id', (int) $p['class']) : null;
        try {
            [$done, $undo, $summary] = match ($p['action']) {
                'attendance' => $this->doAttendance($user, $class, $p['payload']),
                'grades' => $this->doGrades($user, $class, $p['payload']),
                'announce' => $this->doAnnounce($user, $p['payload']),
                'award' => $this->doAward($user, $p['payload']),
                'discipline' => $this->doDiscipline($user, $p['payload']),
            };
        } catch (ValidationException $e) {
            $this->api->edit($chat, $msgId, '⚠️ ثبت نشد: ' . collect($e->errors())->flatten()->first(), $this->mainRows());

            return;
        }
        $log = BotLog::create(['user_id' => $user->id, 'school_id' => $user->school_id, 'platform' => $this->platform,
            'action' => $p['action'], 'summary' => Str::limit($summary, 250), 'undo' => $undo]);
        $this->api->edit($chat, $msgId, $done, [
            [['text' => '↩️ برگردان (تا ' . Jalali::fa((string) self::UNDO_MIN) . ' دقیقه)', 'data' => 'un:' . $log->id]],
            [['text' => '🏠 منو', 'data' => 'm:main']],
        ]);
    }

    /** اجرای یک کنترلرِ سایت به نامِ همین معلم (همان اعتبارسنجی، دسترسی و اثرهای جانبیِ سایت). */
    public function callSite(User $user, string $controller, string $method, array $input, array $args = [])
    {
        $req = Request::create('/bot', 'POST', $input);
        $req->setUserResolver(fn () => $user);
        $req->setLaravelSession(app('session')->driver());
        $prev = app('request');
        app()->instance('request', $req);
        try {
            return app()->call([app($controller), $method], ['request' => $req] + $args);
        } finally {
            app()->instance('request', $prev);
        }
    }

    private function doAttendance(User $user, Classroom $class, array $payload): array
    {
        $date = $payload['date'] ?? now()->toDateString();
        $before = AttendanceRecord::withoutGlobalScopes()->where('classroom_id', $class->id)->whereDate('date', $date)->pluck('status', 'student_id')->all();
        $records = collect($payload['status'])->map(fn ($s, $id) => ['student_id' => (int) $id, 'status' => $s])->values()->all();
        $this->callSite($user, \App\Http\Controllers\AttendanceController::class, 'store', ['classroom_id' => $class->id, 'date' => $date, 'records' => $records]);
        $c = collect($payload['status'])->countBy();

        return [
            '✅ حضور و غیابِ ' . ($date === now()->toDateString() ? 'امروزِ ' : Jalali::format(\Illuminate\Support\Carbon::parse($date), true) . ' — ') . $class->name . " ثبت شد.\n" . Jalali::fa((string) ($c['present'] ?? 0)) . ' حاضر · ' . Jalali::fa((string) ($c['absent'] ?? 0)) . ' غایب · ' . Jalali::fa((string) ($c['late'] ?? 0)) . ' تأخیر',
            ['class' => $class->id, 'date' => $date, 'before' => $before, 'after' => $payload['status']],
            'حضور و غیابِ ' . $class->name . ': ' . ($c['absent'] ?? 0) . ' غایب، ' . ($c['late'] ?? 0) . ' تأخیر',
        ];
    }

    private function doGrades(User $user, Classroom $class, array $g): array
    {
        $col = GradeColumn::create([
            'school_id' => $class->school_id, 'classroom_id' => $class->id, 'teacher_id' => $user->id,
            'title' => $g['title'] . ($g['topic'] ? ' — ' . $g['topic'] : ''), 'type' => $g['type'] === 'numeric' ? 'numeric' : 'descriptive',
            'score_type' => $g['type'], 'lesson' => $g['lesson'], 'topic' => $g['topic'], 'max' => $g['max'] ?: 20, 'graded_at' => now(),
        ]);
        $grades = collect($g['grades'])->map(fn ($v, $id) => ['student_id' => (int) $id,
            'score' => $g['type'] === 'numeric' ? $v['value'] : null, 'text' => $g['type'] === 'numeric' ? null : $v['value']])->values()->all();
        $this->callSite($user, \App\Http\Controllers\Teacher\GradebookController::class, 'saveGrades', ['grades' => $grades], ['gradeColumn' => $col]);

        return [
            '✅ در دفترِ نمره ثبت شد: ' . $col->title . ($g['lesson'] ? ' (' . $g['lesson'] . ')' : '') . ' — ' . Jalali::fa((string) count($grades)) . " نفر.\nامتیاز و اعلانِ بچه‌ها هم مثلِ ثبت از سایت رفت.",
            ['column' => $col->id],
            'دفترِ نمره: ' . $col->title . ' — ' . count($grades) . ' نفر',
        ];
    }

    private function doAnnounce(User $user, array $p): array
    {
        $ann = Announcement::create([
            'school_id' => $user->school_id, 'sender_id' => $user->id, 'audience' => 'personal',
            'title' => '📣 ' . Str::limit($p['text'], 60), 'body' => $p['text'],
        ]);
        $ann->recipients()->sync($p['ids']);

        return [
            '✅ اطلاعیه برای ' . Jalali::fa((string) count($p['ids'])) . ' دانش‌آموز فرستاده شد.',
            ['announcement' => $ann->id],
            'اطلاعیه: ' . Str::limit($p['text'], 80),
        ];
    }

    /* ═════════════════════════ برگردان ═════════════════════════ */

    private function undo(string $chat, User $user, int $logId, int $msgId): void
    {
        $log = BotLog::where('id', $logId)->where('user_id', $user->id)->first();
        if (! $log || $log->undone_at) {
            $this->api->send($chat, 'این کار قبلاً برگردانده شده یا پیدا نشد.');

            return;
        }
        if ($log->created_at->lt(now()->subMinutes(self::UNDO_MIN))) {
            $this->api->send($chat, '⌛ زمانِ برگرداندن گذشته؛ از سایت ویرایش کن.');

            return;
        }
        $u = $log->undo ?? [];
        if ($log->action === 'attendance') {
            $class = $this->classes($user)->firstWhere('id', (int) $u['class']);
            $restore = [];
            foreach ($u['after'] as $sid => $_) {
                if (array_key_exists($sid, $u['before'])) {
                    $restore[] = ['student_id' => (int) $sid, 'status' => $u['before'][$sid]];
                } else {
                    // رکوردِ تازه بود → حذف (و امتیازش)
                    $rec = AttendanceRecord::withoutGlobalScopes()->where('classroom_id', $u['class'])->where('student_id', $sid)->whereDate('date', $u['date'])->first();
                    if ($rec) {
                        XpEntry::where('source_type', AttendanceRecord::class)->where('source_id', $rec->id)->delete();
                        $rec->delete();
                    }
                }
            }
            if ($restore && $class) {
                $this->callSite($user, \App\Http\Controllers\AttendanceController::class, 'store', ['classroom_id' => $class->id, 'date' => $u['date'], 'records' => $restore]);
            }
        } elseif ($log->action === 'grades' && ($col = GradeColumn::withoutGlobalScopes()->find($u['column'] ?? 0))) {
            $this->callSite($user, \App\Http\Controllers\Teacher\GradebookController::class, 'destroyColumn', [], ['gradeColumn' => $col]);
        } elseif (in_array($log->action, ['award', 'discipline'], true)) {
            XpEntry::whereIn('id', (array) ($u['xp'] ?? []))->delete();
            if (! empty($u['records'])) {
                \App\Models\DisciplineRecord::withoutGlobalScopes()->whereIn('id', (array) $u['records'])->delete();
            }
        } elseif ($log->action === 'game' && ($g = \App\Models\EduGame::withoutGlobalScopes()->where('teacher_id', $user->id)->find($u['game'] ?? 0))) {
            if ($g->attempts()->exists()) {
                $this->api->send($chat, '⛔ بچه‌ها این بازی را شروع کرده‌اند؛ از سایت مخفی‌اش کن.');

                return;
            }
            $g->delete();
        } elseif ($log->action === 'exam' && ($x = \App\Models\SmartExam::withoutGlobalScopes()->where('teacher_id', $user->id)->find($u['exam'] ?? 0))) {
            if ($x->attempts()->exists()) {
                $this->api->send($chat, '⛔ بچه‌ها این آزمون را شروع کرده‌اند؛ از سایت ببندیدش.');

                return;
            }
            $x->questions()->delete();
            $x->targets()->delete();
            $x->delete();
        } elseif ($log->action === 'grading') {
            $this->grading($chat)->undo($user, (array) ($u['items'] ?? []));
        } elseif ($log->action === 'live' && ($lc = \App\Models\LiveContest::withoutGlobalScopes()->where('teacher_id', $user->id)->find($u['live'] ?? 0))) {
            if ($lc->players()->exists()) {
                $this->api->send($chat, '⛔ بچه‌ها واردِ این مسابقه شده‌اند؛ از «⏹ پایان» استفاده کن.');

                return;
            }
            \App\Models\Announcement::withoutGlobalScopes()->where('link', route('live.play', $lc->id, false))->delete();
            $lc->delete();
        } elseif ($log->action === 'announce') {
            Announcement::withoutGlobalScopes()->where('id', $u['announcement'] ?? 0)->where('sender_id', $user->id)->delete();
        }
        $log->forceFill(['undone_at' => now()])->save();
        $this->api->edit($chat, $msgId, '↩️ برگردانده شد: ' . $log->summary, $this->mainRows());
    }

    /* ═════════════════════════ حضور و غیابِ دکمه‌ای ═════════════════════════ */

    private const ICONS = ['present' => '✅', 'absent' => '❌', 'late' => '⏰', 'excused' => '📝'];

    private const CYCLE = ['present' => 'absent', 'absent' => 'late', 'late' => 'excused', 'excused' => 'present'];

    private function board(string $chat, User $user, int $classId, int $msgId): void
    {
        $class = $this->classes($user)->firstWhere('id', $classId);
        if (! $class) {
            return;
        }
        $roster = $this->roster($class);
        $today = AttendanceRecord::withoutGlobalScopes()->where('classroom_id', $class->id)->whereDate('date', now()->toDateString())->pluck('status', 'student_id')->all();
        $st = [];
        foreach ($roster as $id => $_) {
            $st[$id] = $today[$id] ?? 'present';
        }
        $tok = Str::lower(Str::random(8));
        Cache::put($this->key('b:' . $tok), ['class' => $class->id, 'st' => $st], now()->addMinutes(40));
        $this->drawBoard($chat, $tok, $class, $roster, $st, $msgId);
    }

    private function drawBoard(string $chat, string $tok, Classroom $class, array $roster, array $st, int $msgId): void
    {
        $rows = [];
        $btns = [];
        foreach ($roster as $id => $name) {
            $btns[] = ['text' => self::ICONS[$st[$id] ?? 'present'] . ' ' . Str::limit($name, 22, '…'), 'data' => 'bt:' . $tok . ':' . $id];
        }
        foreach (array_chunk($btns, 2) as $r) {
            $rows[] = $r;
        }
        $rows[] = [['text' => '✅ همه حاضر', 'data' => 'ba:' . $tok], ['text' => '💾 ثبت', 'data' => 'bs:' . $tok]];
        $rows[] = [['text' => '↩️ منو', 'data' => 'm:main']];
        $c = collect($st)->countBy();
        $t = '📋 ' . $class->name . ' — ' . Jalali::format(now(), true) . "\nروی نامِ هر دانش‌آموز بزن: ✅ حاضر ← ❌ غایب ← ⏰ تأخیر ← 📝 موجه\n\n"
            . '✅ ' . Jalali::fa((string) ($c['present'] ?? 0)) . '  ❌ ' . Jalali::fa((string) ($c['absent'] ?? 0)) . '  ⏰ ' . Jalali::fa((string) ($c['late'] ?? 0)) . '  📝 ' . Jalali::fa((string) ($c['excused'] ?? 0));
        $msgId ? $this->api->edit($chat, $msgId, $t, $rows) : $this->api->send($chat, $t, $rows);
    }

    private function boardToggle(string $chat, User $user, string $tok, int $sid, int $msgId): void
    {
        $s = Cache::get($this->key('b:' . $tok));
        $class = $s ? $this->classes($user)->firstWhere('id', $s['class']) : null;
        if (! $s || ! $class || ! isset($s['st'][$sid])) {
            return;
        }
        $s['st'][$sid] = self::CYCLE[$s['st'][$sid]] ?? 'present';
        Cache::put($this->key('b:' . $tok), $s, now()->addMinutes(40));
        $this->drawBoard($chat, $tok, $class, $this->roster($class), $s['st'], $msgId);
    }

    private function boardAll(string $chat, User $user, string $tok, int $msgId): void
    {
        $s = Cache::get($this->key('b:' . $tok));
        $class = $s ? $this->classes($user)->firstWhere('id', $s['class']) : null;
        if (! $s || ! $class) {
            return;
        }
        $s['st'] = array_map(fn () => 'present', $s['st']);
        Cache::put($this->key('b:' . $tok), $s, now()->addMinutes(40));
        $this->drawBoard($chat, $tok, $class, $this->roster($class), $s['st'], $msgId);
    }

    /* ═════════════════════════ کمکی ═════════════════════════ */

    /** کلاس‌هایی که این کاربر می‌تواند از بات مدیریت کند (معلم: کلاس‌های خودش و نظارتی؛ مدیر: همه‌ی مدرسه). */
    public function classes(User $user): Collection
    {
        if ($user->hasRole(Roles::SCHOOL_ADMIN) && ! $user->hasRole(Roles::TEACHER)) {
            return Classroom::withoutGlobalScopes()->where('school_id', $user->school_id)->orderBy('name')->get();
        }

        return ClassSupervision::teacherClassrooms($user)->orderBy('name')->get();
    }

    /** @return array<int,string> */
    private function roster(?Classroom $class): array
    {
        if (! $class) {
            return [];
        }

        return $class->students()->orderBy('name')->pluck('name', 'users.id')->mapWithKeys(fn ($n, $id) => [(int) $id => $n])->all();
    }

    private function rosterAll(User $user): Collection
    {
        // + (نه flatMap): شناسه‌ی دانش‌آموز کلید است و نباید از نو شماره‌گذاری شود
        $all = [];
        foreach ($this->classes($user) as $c) {
            $all += $this->roster($c);
        }

        return collect($all);
    }

    private function grading(string $chat): BotGrading
    {
        return new BotGrading($this, $this->api, $this->platform, $chat);
    }

    /** 📨 پیام‌های خوانده‌نشده (سؤال‌های بچه‌ها و والدین) با دکمه‌ی پاسخ. */
    private function inbox(string $chat, User $user, int $msgId): void
    {
        $msgs = \App\Models\Message::where('recipient_id', $user->id)->whereNull('read_at')->with('sender:id,name')->latest()->limit(40)->get();
        if ($msgs->isEmpty()) {
            $t = '📨 پیامِ خوانده‌نشده‌ای نداری.';
            $rows = [[['text' => '🏠 منو', 'data' => 'm:main']]];
            $msgId ? $this->api->edit($chat, $msgId, $t, $rows) : $this->api->send($chat, $t, $rows);

            return;
        }
        $L = ['📨 پیام‌های تازه', ''];
        $rows = [];
        foreach ($msgs->groupBy('sender_id')->take(8) as $sid => $g) {
            $name = $g->first()->sender?->name ?? 'کاربر';
            $L[] = '👤 ' . $name . ($g->count() > 1 ? ' (' . Jalali::fa((string) $g->count()) . ' پیام)' : '');
            foreach ($g->sortBy('id')->take(3) as $m) {
                $L[] = '   💬 ' . Str::limit((string) $m->body, 220) . ' · ' . Jalali::fa($m->created_at?->format('H:i') ?? '');
            }
            $rows[] = [['text' => '↩️ پاسخ به ' . Str::limit($name, 18), 'data' => 'msg:r:' . $sid], ['text' => '✔️ خواندم', 'data' => 'msg:k:' . $sid]];
        }
        $rows[] = [['text' => '🏠 منو', 'data' => 'm:main']];
        $msgId ? $this->api->edit($chat, $msgId, Str::limit(implode("\n", $L), 4000), $rows) : $this->api->send($chat, Str::limit(implode("\n", $L), 4000), $rows);
    }

    private function replyMessage(string $chat, User $user, int $to, string $text): void
    {
        try {
            $this->callSite($user, \App\Http\Controllers\MessageController::class, 'store', ['recipient_id' => $to, 'body' => Str::limit($text, 1990, '')]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException|ValidationException $e) {
            $this->api->send($chat, '⛔ به این کاربر نمی‌شود پیام داد.');

            return;
        }
        \App\Models\Message::where('recipient_id', $user->id)->where('sender_id', $to)->whereNull('read_at')->update(['read_at' => now()]);
        $this->api->send($chat, '✅ پاسخ فرستاده شد.', [[['text' => '📨 پیام‌های دیگر', 'data' => 'm:msg'], ['text' => '🏠 منو', 'data' => 'm:main']]]);
    }

    public function quizFor(string $chat): BotQuiz
    {
        return $this->quiz($chat);
    }

    private function library(string $chat): BotLibrary
    {
        return new BotLibrary($this, $this->api, $this->platform, $chat);
    }

    private function quiz(string $chat): BotQuiz
    {
        return new BotQuiz($this, $this->api, $this->platform, $chat);
    }

    private function reports(User $user): BotReports
    {
        return new BotReports($user, $this->classes($user));
    }

    private string $chatKey = '';

    /** «2026-11-11» / خالی → تاریخ (فقط امروز و ۳۰ روزِ گذشته). */
    private static function date(string $d): ?\Illuminate\Support\Carbon
    {
        if (trim($d) === '') {
            return now()->startOfDay();
        }
        try {
            $c = \Illuminate\Support\Carbon::parse(P::digits($d))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        return $c->gt(now()) || $c->lt(now()->subDays(30)->startOfDay()) ? null : $c;
    }

    private function key(string $k): string
    {
        return 'bot:' . $this->platform . ':' . $this->chatKey . ':' . $k;
    }

    /** ذخیره‌ی کارِ در انتظار → توکنِ کوتاه برای دکمه‌ها. */
    private function put(array $data): string
    {
        $tok = Str::lower(Str::random(8));
        Cache::put($this->key('p:' . $tok), $data + ['params' => $data['params'] ?? []], now()->addMinutes(30));

        return $tok;
    }

    public function forChat(string $chat): self
    {
        $this->chatKey = $chat;

        return $this;
    }
}
