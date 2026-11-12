<?php

namespace App\Services\Bot;

use App\Models\AudioSubmission;
use App\Models\AudioTask;
use App\Models\BotLog;
use App\Models\User;
use App\Models\WorksheetSubmission;
use App\Models\XpEntry;
use App\Support\Bot\BotApi;
use App\Support\Bot\BotParser as P;
use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ✍️ تصحیح از داخلِ بات — روخوانی، املا و کاربرگ.
 *
 *  • صفِ تصحیح: کارِ بعدی + فایلِ خودِ دانش‌آموز (صدای روخوانی، عکس/PDFِ املا و کاربرگ) در گفتگو،
 *    و دکمه‌های «خیلی خوب / خوب / …» یا نمره‌ی عددی. با «✍️ با توضیح» می‌شود نوشت: «خوب، ۳ غلط، دقت کن».
 *  • تصحیحِ گروهی با یک جمله: «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن» → فهرستِ دقیقِ
 *    همان کارها، پیش‌نمایش، تأیید، ثبت؛ تا ۱۰ دقیقه «برگردان».
 *
 * ثبتِ نمره همیشه با همان کنترلرهای سایت است: دفترِ نمره، امتیاز، تسلط و اعلانِ دانش‌آموز مثلِ سایت.
 */
class BotGrading
{
    public const TYPES = ['r' => '🎙️ روخوانی', 'd' => '📝 املا', 'w' => '📄 کاربرگ'];

    private const SCORES = [20, 19, 18, 17, 16, 15, 14, 12, 10];

    public function __construct(private TeacherBot $bot, private BotApi $api, private string $platform, private string $chat) {}

    /* ═════════════════════════ پرس‌وجو ═════════════════════════ */

    private function audioQ(User $u, ?string $kind = null)
    {
        return AudioSubmission::whereNotNull('file_path')
            ->whereHas('task', fn ($q) => $this->ownTasks($q, $u)->when($kind, fn ($w) => $w->where('kind', $kind)));
    }

    private function ownTasks($q, User $u)
    {
        return $u->hasRole(\App\Support\Roles::TEACHER) ? $q->where('teacher_id', $u->id) : $q->where('school_id', $u->school_id);
    }

    private function wsQ(User $u)
    {
        return WorksheetSubmission::whereNotNull('file_path')
            ->whereHas('worksheet', fn ($q) => $q->withoutGlobalScopes()->when($u->hasRole(\App\Support\Roles::TEACHER),
                fn ($w) => $w->where('teacher_id', $u->id), fn ($w) => $w->where('school_id', $u->school_id)));
    }

    /** @return array{r:int,d:int,w:int} */
    public function counts(User $u): array
    {
        $a = AudioTask::ready() ? $this->audioQ($u)->whereNull('graded_at')->with('task:id,kind')->get()->groupBy(fn ($s) => $s->task?->kind) : collect();
        $w = rescue(fn () => \App\Support\DbSchema::hasColumn('worksheet_submissions', 'graded_at') ? $this->wsQ($u)->whereNull('graded_at')->count() : 0, 0, false);

        return ['r' => count($a['reading'] ?? []), 'd' => count($a['dictation'] ?? []), 'w' => (int) $w];
    }

    /* ═════════════════════════ صفِ تصحیح ═════════════════════════ */

    public function home(User $u, int $msgId = 0): void
    {
        $c = $this->counts($u);
        $t = array_sum($c)
            ? "✍️ منتظرِ تصحیح:\n🎙️ روخوانی: " . Jalali::fa((string) $c['r']) . "\n📝 املا: " . Jalali::fa((string) $c['d']) . "\n📄 کاربرگ: " . Jalali::fa((string) $c['w'])
                . "\n\nیک دسته را بزن تا کارها یکی‌یکی بیاید؛ فایلِ خودِ دانش‌آموز را می‌بینی یا می‌شنوی و با یک دکمه نمره می‌دهی.\n\n"
                . "💡 گروهی هم می‌شود: «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن»"
            : '✍️ همه‌ی کارها تصحیح شده‌اند 👏';
        $rows = [];
        foreach (self::TYPES as $k => $label) {
            if ($c[$k]) {
                $rows[] = [['text' => $label . ' (' . Jalali::fa((string) $c[$k]) . ')', 'data' => 'g:q:' . $k]];
            }
        }
        $rows[] = [['text' => '🏠 منو', 'data' => 'm:main']];
        $msgId ? $this->api->edit($this->chat, $msgId, $t, $rows) : $this->api->send($this->chat, $t, $rows);
    }

    /** کارِ بعدیِ تصحیح‌نشده از این نوع (کارهای «بعداً» در این دور رد می‌شوند). */
    public function next(User $u, string $type): void
    {
        $skip = Cache::get($this->key('gskip:' . $type), []);
        $item = $type === 'w'
            ? $this->wsQ($u)->whereNull('graded_at')->whereNotIn('id', $skip)->with('worksheet:id,title', 'student:id,name')->orderBy('submitted_at')->orderBy('id')->first()
            : $this->audioQ($u, $type === 'r' ? 'reading' : 'dictation')->whereNull('graded_at')->whereNotIn('id', $skip)->with('task', 'student:id,name')->orderBy('submitted_at')->orderBy('id')->first();
        if (! $item) {
            Cache::forget($this->key('gskip:' . $type));
            $left = $this->counts($u);
            $this->api->send($this->chat, '✅ ' . self::TYPES[$type] . ': کارِ دیگری برای تصحیح نمانده.' . ($skip ? ' (' . Jalali::fa((string) count($skip)) . ' کار را «بعداً» زدی.)' : ''),
                [[['text' => '✍️ صفِ تصحیح', 'data' => 'g:home']], [['text' => '🏠 منو', 'data' => 'm:main']]]);

            return;
        }
        $this->sendFile($item, $type);
        $this->card($u, $item, $type);
    }

    private function sendFile($item, string $type): void
    {
        $path = $item->file_path;
        $disk = Storage::disk('public');
        if (! $path || ! $disk->exists($path)) {
            $this->api->send($this->chat, '⚠️ فایلِ این کار روی سرور پیدا نشد.');

            return;
        }
        $bytes = $disk->get($path);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $name = Str::slug((string) ($item->student?->name ?? 'student'), '-', null) ?: 'student';
        $cap = ($item->student?->name ?? '') . ' — ' . ($type === 'w' ? ($item->worksheet?->title ?? 'کاربرگ') : ($item->task?->title ?? ''));
        $r = match (true) {
            $type === 'r' || ($item->file_kind ?? '') === 'audio' => $this->api->upload('sendAudio', $this->chat, 'audio', $bytes, $name . '.' . ($ext ?: 'mp3'), ['caption' => '🎙️ ' . $cap, 'title' => $cap]),
            $ext === 'pdf' => $this->api->upload('sendDocument', $this->chat, 'document', $bytes, $name . '.pdf', ['caption' => '📄 ' . $cap]),
            default => $this->api->upload('sendPhoto', $this->chat, 'photo', $bytes, $name . '.' . ($ext ?: 'jpg'), ['caption' => '📝 ' . $cap]),
        };
        if (! ($r['ok'] ?? false)) {
            // بعضی قالب‌ها را پیام‌رسان به‌صورتِ صدا/عکس نمی‌پذیرد → فایلِ معمولی
            $this->api->upload('sendDocument', $this->chat, 'document', $bytes, $name . '.' . ($ext ?: 'bin'), ['caption' => $cap]);
        }
    }

    private function card(User $u, $item, string $type, int $msgId = 0): void
    {
        $title = $type === 'w' ? ($item->worksheet?->title ?? 'کاربرگ') : ($item->task?->title ?? '');
        $numeric = $type !== 'w' && ($item->task?->score_type === 'numeric');
        $L = [self::TYPES[$type] . ' · ' . $title, '👤 ' . ($item->student?->name ?? '—'),
            '🕒 ' . ($item->submitted_at ? Jalali::format($item->submitted_at, true) . ' ' . Jalali::fa($item->submitted_at->format('H:i')) : '—')];
        if (! empty($item->note)) {
            $L[] = '💬 یادداشتِ دانش‌آموز: ' . Str::limit($item->note, 300);
        }
        $L[] = '';
        $L[] = $numeric ? 'نمره از ۲۰:' : 'ارزشیابی:';
        $id = $item->id;
        if ($type === 'w') {
            $rows = array_chunk(array_map(fn ($g, $i) => ['text' => $g, 'data' => 'g:w:' . $id . ':' . $i], array_keys(WorksheetSubmission::GRADES), array_keys(array_keys(WorksheetSubmission::GRADES))), 3);
        } elseif ($numeric) {
            $rows = array_chunk(array_map(fn ($n) => ['text' => Jalali::fa((string) $n), 'data' => 'g:n:' . $id . ':' . $n], self::SCORES), 5);
        } else {
            $rows = array_chunk(array_map(fn ($g, $i) => ['text' => $g, 'data' => 'g:a:' . $id . ':' . $i], AudioTask::GRADES, array_keys(AudioTask::GRADES)), 2);
        }
        $rows[] = [['text' => '✍️ با توضیح / تعدادِ غلط', 'data' => 'g:t:' . $type . ':' . $id], ['text' => '⏭ بعداً', 'data' => 'g:s:' . $type . ':' . $id]];
        $rows[] = [['text' => '✍️ صفِ تصحیح', 'data' => 'g:home'], ['text' => '🏠 منو', 'data' => 'm:main']];
        $msgId ? $this->api->edit($this->chat, $msgId, implode("\n", $L), $rows) : $this->api->send($this->chat, implode("\n", $L), $rows);
    }

    /* ═════════════════════════ دکمه‌ها و متن ═════════════════════════ */

    public function onButton(User $u, string $data, int $msgId): void
    {
        $a = explode(':', $data);
        [$act, $x, $y] = [$a[1] ?? '', $a[2] ?? '', $a[3] ?? ''];
        switch ($act) {
            case 'home':
                $this->home($u, $msgId);

                return;
            case 'q':
                if (isset(self::TYPES[$x])) {
                    $this->next($u, $x);
                }

                return;
            case 's':   // بعداً
                $k = $this->key('gskip:' . $x);
                Cache::put($k, array_values(array_unique([...Cache::get($k, []), (int) $y])), now()->addHours(6));
                $this->api->edit($this->chat, $msgId, '⏭ برای بعد گذاشته شد.');
                $this->next($u, $x);

                return;
            case 't':   // نوشتنِ نمره با توضیح
                Cache::put($this->key('gwait'), ['type' => $x, 'id' => (int) $y, 'msg' => $msgId], now()->addMinutes(20));
                $this->api->send($this->chat, "✍️ بنویس؛ مثلاً:\n«خوب، ۳ غلط، در نوشتنِ «ض» دقت کن»\n«۱۸ آفرین، فقط کمی تندتر بخوان»\nبرای انصراف «لغو».");

                return;
            case 'a':
            case 'n':
            case 'w':
                $type = $act === 'w' ? 'w' : 'a';
                $value = match ($act) {
                    'a' => AudioTask::GRADES[(int) $y] ?? null,
                    'n' => (float) $y,
                    'w' => array_keys(WorksheetSubmission::GRADES)[(int) $y] ?? null,
                };
                $this->gradeOne($u, $type, (int) $x, $value, null, null, $msgId);

                return;
            case 'ok':
                $this->confirmBulk($u, $x, $msgId);

                return;
            case 'no':
                Cache::forget($this->key('gb:' . $x));
                $this->api->edit($this->chat, $msgId, '❌ لغو شد؛ نمره‌ای ثبت نشد.', $this->bot->mainRows());

                return;
        }
        $this->home($u, $msgId);
    }

    public function onText(User $u, string $text): bool
    {
        $w = Cache::get($this->key('gwait'));
        if (! $w) {
            return false;
        }
        Cache::forget($this->key('gwait'));
        if (preg_match('/^\/?(لغو|cancel)$/u', P::norm($text))) {
            $this->api->send($this->chat, '👌 باشد.');

            return true;
        }
        [$value, $mistakes, $feedback] = self::parseGrade($text);
        if ($value === null) {
            Cache::put($this->key('gwait'), $w, now()->addMinutes(20));
            $this->api->send($this->chat, '⚠️ نمره را پیدا نکردم؛ یکی از «خیلی خوب، خوب، قابل قبول، نیاز به تلاش» (برای کاربرگ «عالی» هم) یا عددِ نمره را بنویس.');

            return true;
        }
        $this->gradeOne($u, $w['type'] === 'w' ? 'w' : 'a', (int) $w['id'], $value, $mistakes, $feedback, 0);

        return true;
    }

    /** «خوب، ۳ غلط، دقت کن» → [خوب, 3, «دقت کن»] ؛ «۱۸ آفرین» → [18, null, «آفرین»] */
    public static function parseGrade(string $text): array
    {
        $t = P::norm($text);
        // توضیح از متنِ اصلی ساخته می‌شود (نه متنِ یکسان‌شده) تا «آفرین» همان بماند
        $rest = strtr(trim($text), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.']);
        $mistakes = null;
        if (preg_match('/(\d+)\s*(تا\s*)?(غلط|اشتباه)/u', $rest, $m)) {
            $mistakes = (int) $m[1];
            $rest = preg_replace('/\d+\s*(تا\s*)?(غلط|اشتباه)/u', ' ', $rest);
        }
        $value = null;
        foreach (['نیاز به تلاش', 'قابل قبول', 'خیلی خوب', 'عالی', 'خوب'] as $g) {
            if (str_contains($t, $g)) {
                $value = $g;
                $rest = preg_replace('/' . str_replace(' ', '[\s\x{200C}]*', $g) . '/u', ' ', $rest, 1);
                break;
            }
        }
        if ($value === null && preg_match('/(?<![\d.])(\d{1,2}(?:\.\d{1,2})?)(?![\d.])/', $rest, $n) && (float) $n[1] <= 20) {
            $value = (float) $n[1];
            $rest = preg_replace('/(?<![\d.])' . preg_quote($n[1], '/') . '(?![\d.])/', ' ', $rest, 1);
        }
        $rest = trim(preg_replace('/\s+/u', ' ', (string) $rest));
        $feedback = trim(preg_replace(['/^(نمره|بزن|بده)\s*/u', '/^[،,؛\s]+|[،,؛\s]+$/u'], '', $rest));

        return [$value, $mistakes, $feedback !== '' ? mb_substr($feedback, 0, 300) : null];
    }

    /** ثبتِ یک نمره با کنترلرِ سایت. @return array{0:bool,1:string} */
    public function save(User $u, string $type, int $id, string|float $value, ?int $mistakes = null, ?string $feedback = null): array
    {
        if ($type === 'w') {
            $sub = WorksheetSubmission::with('worksheet', 'student')->find($id);
            if (! $sub || ! is_string($value) || ! isset(WorksheetSubmission::GRADES[$value])) {
                return [false, 'برای کاربرگ یکی از «' . implode('، ', array_keys(WorksheetSubmission::GRADES)) . '» را بگو.'];
            }
            $res = $this->bot->callSite($u, \App\Http\Controllers\WorksheetSubmissionController::class, 'grade',
                ['grade' => $value, 'xp' => WorksheetSubmission::GRADES[$value], 'feedback' => $feedback], ['submission' => $sub]);
        } else {
            $sub = AudioSubmission::with('task', 'student')->find($id);
            if (! $sub || ! $sub->task) {
                return [false, 'این کار پیدا نشد.'];
            }
            $numeric = $sub->task->score_type === 'numeric';
            if ($numeric && ! is_float($value) && ! is_int($value)) {
                return [false, '«' . $sub->task->title . '» نمره‌ی عددی (از ۲۰) دارد؛ عدد بگو.'];
            }
            if (! $numeric && ! in_array($value, AudioTask::GRADES, true)) {
                return [false, '«' . $sub->task->title . '» ارزشیابیِ توصیفی دارد؛ «خیلی خوب، خوب، قابل قبول یا نیاز به تلاش» بگو.'];
            }
            $res = $this->bot->callSite($u, \App\Http\Controllers\AudioSubmissionController::class, 'grade',
                array_filter(['grade' => $numeric ? null : $value, 'score' => $numeric ? $value : null, 'mistakes' => $mistakes, 'feedback' => $feedback], fn ($v) => $v !== null),
                ['submission' => $sub]);
        }
        $status = method_exists($res, 'getStatusCode') ? $res->getStatusCode() : 200;
        if ($status >= 400) {
            $msg = method_exists($res, 'getData') ? (string) ($res->getData(true)['message'] ?? '') : '';

            return [false, $msg ?: 'ثبت نشد (' . $status . ').'];
        }

        return [true, ''];
    }

    private function snapshot(string $type, int $id): ?array
    {
        $s = $type === 'w' ? WorksheetSubmission::find($id) : AudioSubmission::find($id);
        if (! $s || ! $s->graded_at) {
            return null;
        }

        return $type === 'w' ? ['grade' => $s->grade, 'feedback' => $s->feedback]
            : ['grade' => $s->grade, 'score' => $s->score, 'mistakes' => $s->mistakes, 'feedback' => $s->feedback];
    }

    private function gradeOne(User $u, string $type, int $id, string|float|null $value, ?int $mistakes, ?string $feedback, int $msgId): void
    {
        $prev = $this->snapshot($type, $id);
        [$ok, $err] = $value === null ? [false, 'نمره مشخص نیست.'] : $this->save($u, $type, $id, $value, $mistakes, $feedback);
        if (! $ok) {
            $this->api->send($this->chat, '⚠️ ' . $err);

            return;
        }
        $sub = $type === 'w' ? WorksheetSubmission::with('student:id,name')->find($id) : AudioSubmission::with('student:id,name', 'task:id,kind')->find($id);
        $log = BotLog::create(['user_id' => $u->id, 'school_id' => $u->school_id, 'platform' => $this->platform, 'action' => 'grading',
            'summary' => 'تصحیح: ' . ($sub?->student?->name ?? '') . ' ← ' . (is_float($value) ? Jalali::fa((string) $value) : $value),
            'undo' => ['items' => [['t' => $type, 'id' => $id, 'prev' => $prev]]]]);
        $done = '✅ ' . ($sub?->student?->name ?? '') . ' ← ' . (is_float($value) ? Jalali::fa((string) $value) . ' از ۲۰' : $value)
            . ($mistakes !== null ? ' · ' . Jalali::fa((string) $mistakes) . ' غلط' : '') . ($feedback ? "\n💬 " . $feedback : '');
        $rows = [[['text' => '↩️ برگردان', 'data' => 'un:' . $log->id]]];
        $msgId ? $this->api->edit($this->chat, $msgId, $done, $rows) : $this->api->send($this->chat, $done, $rows);
        $this->next($u, $type === 'w' ? 'w' : (($sub?->task?->kind ?? 'reading') === 'reading' ? 'r' : 'd'));
    }

    /* ═════════════════════════ تصحیحِ گروهی ═════════════════════════ */

    /**
     * @param  array{kind?:string,date?:string,after_hour?:int|null,days?:int|null,task?:string|null,names?:array,except?:array,regrade?:bool,value:mixed,feedback?:string|null}  $p
     */
    public function bulk(User $u, array $p): void
    {
        $value = $p['value'] ?? null;
        $value = is_numeric($value) ? (float) $value : (is_string($value) ? trim($value) : null);
        if ($value === null || $value === '') {
            $this->api->send($this->chat, '✍️ چه نمره‌ای بدهم؟ مثلاً «خیلی خوب» یا «۱۸».');

            return;
        }
        $kind = in_array($p['kind'] ?? '', ['reading', 'dictation', 'worksheet'], true) ? $p['kind'] : 'any';
        [$from, $to, $when] = self::window($p);
        $items = collect();
        if (in_array($kind, ['reading', 'dictation', 'any'], true) && AudioTask::ready()) {
            $items = $items->merge($this->audioQ($u, $kind === 'any' ? null : $kind)
                ->when(empty($p['regrade']), fn ($q) => $q->whereNull('graded_at'))
                ->whereBetween('submitted_at', [$from, $to])
                ->with('task', 'student:id,name')->get()->map(fn ($s) => ['t' => 'a', 's' => $s, 'title' => $s->task->title, 'k' => $s->task->kind]));
        }
        if (in_array($kind, ['worksheet', 'any'], true)) {
            $items = $items->merge(rescue(fn () => $this->wsQ($u)
                ->when(empty($p['regrade']), fn ($q) => $q->whereNull('graded_at'))
                ->whereBetween('submitted_at', [$from, $to])
                ->with('worksheet:id,title', 'student:id,name')->get()->map(fn ($s) => ['t' => 'w', 's' => $s, 'title' => $s->worksheet?->title ?? 'کاربرگ', 'k' => 'worksheet']), collect(), false));
        }
        // عنوانِ تکلیف: بدونِ حساسیت به اعراب، نیم‌فاصله و فاصله («درس ۵» = «درسِ ۵»)
        if (! empty($p['task'])) {
            $flat = fn ($v) => preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{200C}\x{200F}\s\-–_«»"]+/u', '', P::norm(P::digits((string) $v)));
            $hint = $flat($p['task']);
            $items = $items->filter(fn ($i) => $hint === '' || str_contains($flat($i['title']), $hint));
        }
        // فقط این نام‌ها / به‌جز این نام‌ها
        $match = fn (string $name, array $list) => collect($list)->contains(fn ($n) => ($n = trim((string) $n)) !== '' && (P::has($name, $n) || P::has($n, $name)));
        if (! empty($p['names'])) {
            $items = $items->filter(fn ($i) => $match((string) $i['s']->student?->name, (array) $p['names']));
        }
        if (! empty($p['except'])) {
            $items = $items->reject(fn ($i) => $match((string) $i['s']->student?->name, (array) $p['except']));
        }
        $kindFa = ['reading' => 'روخوانی', 'dictation' => 'املا', 'worksheet' => 'کاربرگ', 'any' => 'روخوانی/املا/کاربرگ'][$kind];
        if ($items->isEmpty()) {
            $this->api->send($this->chat, '🔎 ' . $kindFa . 'ی ' . (empty($p['regrade']) ? 'تصحیح‌نشده‌ای ' : '') . 'که ' . $when . ' فرستاده شده باشد پیدا نکردم.',
                [[['text' => '✍️ صفِ تصحیح', 'data' => 'g:home']], [['text' => '🏠 منو', 'data' => 'm:main']]]);

            return;
        }
        // کدام‌ها با این نمره جور نیستند (عددی/توصیفی)
        $ok = [];
        $skip = [];
        foreach ($items as $i) {
            $fits = $i['t'] === 'w' ? is_string($value) && isset(WorksheetSubmission::GRADES[$value])
                : (($i['s']->task->score_type === 'numeric') ? is_float($value) : in_array($value, AudioTask::GRADES, true));
            $fits ? $ok[] = $i : $skip[] = $i;
        }
        $vFa = is_float($value) ? Jalali::fa((string) $value) . ' از ۲۰' : '«' . $value . '»';
        $L = ['✍️ تصحیحِ گروهی — ' . $kindFa . 'هایی که ' . $when . ' فرستاده شده' . (empty($p['regrade']) ? ' (هنوز تصحیح‌نشده)' : ''), ''];
        foreach (array_slice($ok, 0, 40) as $n => $i) {
            $L[] = Jalali::fa((string) ($n + 1)) . '. ' . ($i['s']->student?->name ?? '—') . ' — ' . Str::limit($i['title'], 40)
                . ' · ' . Jalali::fa($i['s']->submitted_at?->format('H:i') ?? '');
        }
        if (count($ok) > 40) {
            $L[] = '… و ' . Jalali::fa((string) (count($ok) - 40)) . ' نفرِ دیگر';
        }
        $L[] = '';
        $L[] = $ok ? '← برای همه: ' . $vFa . (! empty($p['feedback']) ? "\n💬 توضیح: " . $p['feedback'] : '') : '';
        if ($skip) {
            $L[] = '⚠️ ' . Jalali::fa((string) count($skip)) . ' کار ' . (is_float($value) ? 'ارزشیابیِ توصیفی' : 'نمره‌ی عددی') . ' دارد و با ' . $vFa . ' جور نیست؛ آن‌ها را جدا بگو: '
                . collect($skip)->take(5)->map(fn ($i) => ($i['s']->student?->name ?? '') . ' («' . Str::limit($i['title'], 25) . '»)')->implode('، ');
        }
        if (! $ok) {
            $this->api->send($this->chat, implode("\n", array_filter($L)), $this->bot->mainRows());

            return;
        }
        $tok = Str::lower(Str::random(8));
        Cache::put($this->key('gb:' . $tok), ['items' => array_map(fn ($i) => ['t' => $i['t'], 'id' => $i['s']->id], $ok), 'value' => $value,
            'feedback' => $p['feedback'] ?? null, 'label' => $kindFa . ' ' . $when], now()->addMinutes(30));
        $this->api->send($this->chat, Str::limit(implode("\n", $L), 4000), [
            [['text' => '✅ تأیید و ثبتِ ' . Jalali::fa((string) count($ok)) . ' نمره', 'data' => 'g:ok:' . $tok], ['text' => '❌ لغو', 'data' => 'g:no:' . $tok]],
        ]);
    }

    private function confirmBulk(User $u, string $tok, int $msgId): void
    {
        $p = Cache::pull($this->key('gb:' . $tok));
        if (! $p) {
            $this->api->edit($this->chat, $msgId, '⌛ این پیش‌نمایش منقضی شده؛ دوباره بگو.', $this->bot->mainRows());

            return;
        }
        $this->api->edit($this->chat, $msgId, '⏳ در حالِ ثبتِ ' . Jalali::fa((string) count($p['items'])) . ' نمره…');
        @set_time_limit(300);
        $done = [];
        $fail = [];
        foreach ($p['items'] as $it) {
            $prev = $this->snapshot($it['t'], $it['id']);
            [$ok, $err] = $this->save($u, $it['t'], $it['id'], $p['value'], null, $p['feedback'] ?? null);
            $ok ? $done[] = $it + ['prev' => $prev] : $fail[] = $err;
        }
        $vFa = is_float($p['value']) ? Jalali::fa((string) $p['value']) : $p['value'];
        $log = $done ? BotLog::create(['user_id' => $u->id, 'school_id' => $u->school_id, 'platform' => $this->platform, 'action' => 'grading',
            'summary' => 'تصحیحِ گروهیِ ' . $p['label'] . ': ' . count($done) . ' نفر ← ' . $vFa, 'undo' => ['items' => $done]]) : null;
        $this->api->edit($this->chat, $msgId, '✅ برای ' . Jalali::fa((string) count($done)) . ' نفر «' . $vFa . '» ثبت شد؛ دفترِ نمره، امتیاز و اعلانِ بچه‌ها هم به‌روز شد.'
            . ($fail ? "\n⚠️ " . Jalali::fa((string) count($fail)) . ' مورد ثبت نشد: ' . Str::limit($fail[0], 120) : ''),
            array_values(array_filter([$log ? [['text' => '↩️ برگردان (تا ۱۰ دقیقه)', 'data' => 'un:' . $log->id]] : null, [['text' => '🏠 منو', 'data' => 'm:main']]])));
    }

    /** برگرداندنِ تصحیح‌ها (به حالتِ قبل؛ اگر قبلاً تصحیح نشده بود، تصحیح پاک می‌شود). */
    public function undo(User $u, array $items): void
    {
        foreach ($items as $it) {
            if ($it['prev'] ?? null) {
                $pv = $it['prev'];
                $v = $it['t'] === 'w' ? $pv['grade'] : (($pv['score'] ?? null) !== null ? (float) $pv['score'] : $pv['grade']);
                if ($v !== null) {
                    $this->save($u, $it['t'], (int) $it['id'], $v, $pv['mistakes'] ?? null, $pv['feedback'] ?? null);
                }

                continue;
            }
            if ($it['t'] === 'w') {
                if ($s = WorksheetSubmission::find($it['id'])) {
                    XpEntry::where('source_type', WorksheetSubmission::GRADE_SOURCE)->where('source_id', $s->id)->delete();
                    $s->forceFill(['grade' => null, 'grade_xp' => 0, 'feedback' => null, 'graded_at' => null, 'graded_by' => null])->save();
                }
            } elseif ($s = AudioSubmission::find($it['id'])) {
                $this->bot->callSite($u, \App\Http\Controllers\AudioSubmissionController::class, 'ungrade', ['mode' => 'grade'], ['submission' => $s]);
            }
        }
    }

    /** بازه‌ی زمانی از «امشب / امروز / دیروز / این هفته / تاریخ». @return array{0:Carbon,1:Carbon,2:string} */
    public static function window(array $p): array
    {
        $date = trim((string) ($p['date'] ?? ''));
        $days = (int) ($p['days'] ?? 0);
        if ($days > 1) {
            return [now()->subDays($days - 1)->startOfDay(), now(), Jalali::fa((string) $days) . ' روزِ اخیر'];
        }
        try {
            $d = $date !== '' ? Carbon::parse(P::digits($date))->startOfDay() : now()->startOfDay();
        } catch (\Throwable $e) {
            $d = now()->startOfDay();
        }
        $from = $d->copy();
        $hour = $p['after_hour'] ?? null;
        if (is_numeric($hour) && $hour >= 0 && $hour <= 23) {
            $from->setTime((int) $hour, 0);
        }
        $label = $d->isToday() ? (is_numeric($hour) && $hour >= 15 ? 'امشب' : 'امروز') : ($d->isYesterday() ? 'دیروز' : Jalali::format($d));
        if (is_numeric($hour)) {
            $label .= ' (از ساعتِ ' . Jalali::fa((string) (int) $hour) . ')';
        }

        return [$from, $d->copy()->endOfDay(), $label];
    }

    /** بدونِ هوش مصنوعی: «برای کسایی که امشب روخوانی فرستادن خیلی خوب بزن». */
    public static function looksBulk(string $text): ?array
    {
        $t = P::norm($text);
        if (! preg_match('/(روخوانی|روخونی|املا|کاربرگ)/u', $t) || ! preg_match('/(فرستاد|فرستادن|فرستاده|ارسال|تحویل|داد)/u', $t)) {
            return null;
        }
        [$value, , $feedback] = self::parseGrade(preg_replace('/(فرستاد\S*|ارسال\S*|تحویل\S*|کسایی|کسانی|برای|که|امشب|امروز|دیروز|روخوانی|روخونی|املا|کاربرگ|را|رو|بزن|بده|بذار|بگذار|همه)/u', ' ', $t));
        if ($value === null) {
            return null;
        }
        $kind = preg_match('/(روخوانی|روخونی)/u', $t) ? 'reading' : (str_contains($t, 'املا') ? 'dictation' : 'worksheet');
        $p = ['kind' => $kind, 'value' => $value, 'feedback' => null];
        if (str_contains($t, 'امشب')) {
            $p['after_hour'] = 16;
        } elseif (str_contains($t, 'دیروز')) {
            $p['date'] = now()->subDay()->toDateString();
        } elseif (preg_match('/(این\s*هفته|هفته)/u', $t)) {
            $p['days'] = 7;
        }
        if (preg_match('/(?:جز|به\s*جز|بجز|غیر\s*از)\s+(.+?)(?:\s+(?:خیلی|خوب|قابل|نیاز|عالی|\d)|$)/u', $t, $m)) {
            $p['except'] = preg_split('/\s*(?:،|,|و)\s+/u', trim($m[1]));
        }

        return $p;
    }

    private function key(string $k): string
    {
        return 'bot:' . $this->platform . ':' . $this->chat . ':' . $k;
    }
}
