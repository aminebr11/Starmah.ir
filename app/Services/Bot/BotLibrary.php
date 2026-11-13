<?php

namespace App\Services\Bot;

use App\Models\User;
use App\Support\Bot\BotApi;
use App\Support\Jalali;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 📚 «کارهای من» در بات: آزمون‌ها، بازی‌ها و مسابقه‌های زنده‌ی معلم — فهرست، سؤال‌ها، نتیجه،
 * انتشار/بستن، حذف (با تأیید)، و ساختنِ آزمون/بازی/مسابقه از سؤال‌هایشان. دکمه‌ها با «lb:» شروع می‌شوند.
 *
 *   lb:home · lb:l:نوع:صفحه · lb:v:نوع:شناسه · lb:q:نوع:شناسه · lb:r:نوع:شناسه
 *   lb:s:نوع:شناسه:وضعیت · lb:d:نوع:شناسه (تأیید: lb:dy) · lb:mk:نوع:شناسه:نوعِ‌مقصد
 *   lb:pk:نوع:شناسه (انتخابِ چندتایی) · lb:mkp:نوعِ‌مقصد (ساخت از انتخاب‌شده‌ها)
 */
class BotLibrary
{
    private const PAGE = 8;

    public function __construct(private TeacherBot $bot, private BotApi $api, private string $platform, private string $chat) {}

    private function say(int $msgId, string $text, array $rows): void
    {
        $text = Str::limit($text, 4000);
        $msgId ? $this->api->edit($this->chat, $msgId, $text, $rows) : $this->api->send($this->chat, $text, $rows);
    }

    private function key(string $k): string
    {
        return 'bot:' . $this->platform . ':' . $this->chat . ':' . $k;
    }

    private function picks(): array
    {
        return Cache::get($this->key('lbpick'), []);
    }

    public function home(User $u, int $msgId = 0): void
    {
        $it = new BotItems($u);
        $c = fn ($t) => $it->query($t)->count();
        $p = count($this->picks());
        $rows = [
            [['text' => '📝 آزمون‌ها (' . Jalali::fa((string) $c('exam')) . ')', 'data' => 'lb:l:exam:0'], ['text' => '🎮 بازی‌ها (' . Jalali::fa((string) $c('game')) . ')', 'data' => 'lb:l:game:0']],
            [['text' => '🏆 مسابقه‌های زنده (' . Jalali::fa((string) $c('live')) . ')', 'data' => 'lb:l:live:0'], ['text' => '🗄 بانکِ سؤال', 'data' => 'q:bank']],
        ];
        if ($p) {
            $rows[] = [['text' => '🧺 ' . Jalali::fa((string) $p) . ' مورد انتخاب‌شده → ساختن', 'data' => 'lb:basket']];
        }
        $rows[] = [['text' => '🏠 منو', 'data' => 'm:main']];
        $this->say($msgId, "📚 کارهای من\n\nهر آزمون، بازی یا مسابقه را باز کن تا سؤال‌ها و نتیجه را ببینی، منتشر یا بسته‌اش کنی، یا از سؤال‌هایش آزمون/بازی/مسابقه‌ی تازه بسازی.\n"
            . "می‌توانی چند مورد را با «➕ انتخاب» جمع کنی و یک‌جا از همه‌شان بسازی.\n\n💡 یا بنویس: «سؤال‌های سه بازیِ آخر را بده» · «با سؤال‌های دو آزمونِ آخر یک مسابقه‌ی زنده بساز»", $rows);
    }

    public function onButton(User $u, string $data, int $msgId): void
    {
        $a = explode(':', $data);
        [$act, $t, $id, $x] = [$a[1] ?? '', $a[2] ?? '', (int) ($a[3] ?? 0), $a[4] ?? ''];
        $it = new BotItems($u);
        if ($act === 'home') {
            $this->home($u, $msgId);

            return;
        }
        if ($act === 'basket') {
            $this->basket($u, $msgId);

            return;
        }
        if ($act === 'clr') {
            Cache::forget($this->key('lbpick'));
            $this->home($u, $msgId);

            return;
        }
        if ($act === 'mkp') {   // ساختن از انتخاب‌شده‌ها: lb:mkp:نوعِ‌مقصد
            $this->make($u, $this->resolvePicks($u), $t, 'copy');

            return;
        }
        if ($act === 'mks') {   // مشابه با هوش مصنوعی از انتخاب‌شده‌ها
            $this->make($u, $this->resolvePicks($u), $t, 'similar');

            return;
        }
        if (! isset(BotItems::TYPES[$t])) {
            return;
        }
        if ($act === 'l') {
            $this->listItems($u, $t, (int) ($a[3] ?? 0), $msgId);

            return;
        }
        $item = $it->find($t, $id);
        if (! $item) {
            $this->say($msgId, '🔍 پیدا نشد (شاید حذف شده).', [[['text' => '📚 کارهای من', 'data' => 'lb:home']]]);

            return;
        }
        switch ($act) {
            case 'v':
                $this->card($u, $t, $item, $msgId);
                break;
            case 'q':
                $qs = $it->questions($t, $item);
                $L = [BotItems::TYPES[$t] . ' «' . $item->title . '» — ' . Jalali::fa((string) count($qs)) . ' سؤال', ''];
                foreach ($qs as $i => $q) {
                    $L[] = BotItems::line($q, $i);
                }
                // پیامِ بلند در چند تکه
                foreach (self::chunks(implode("\n", $L)) as $k => $part) {
                    $this->api->send($this->chat, $part, $k === array_key_last(self::chunks(implode("\n", $L))) ? self::makeRows($t, $item->id) : []);
                }
                break;
            case 'r':
                $this->results($u, $t, $item);
                break;
            case 's':   // انتشار/بستن
                $this->setStatus($u, $t, $item, $x, $msgId);
                break;
            case 'd':
                $this->say($msgId, '🗑 «' . $item->title . '» حذف شود؟ نتیجه‌های بچه‌ها هم دیگر دیده نمی‌شود.', [
                    [['text' => '✅ بله، حذف کن', 'data' => "lb:dy:{$t}:{$item->id}"], ['text' => '↩️ نه', 'data' => "lb:v:{$t}:{$item->id}"]],
                ]);
                break;
            case 'dy':
                $this->destroy($u, $t, $item, $msgId);
                break;
            case 'pk':   // ➕/➖ انتخاب برای ساختنِ گروهی
                $p = $this->picks();
                $k = $t . ':' . $item->id;
                $p = in_array($k, $p, true) ? array_values(array_diff($p, [$k])) : [...$p, $k];
                Cache::put($this->key('lbpick'), array_slice($p, -10), now()->addHours(3));
                $this->card($u, $t, $item, $msgId);
                break;
            case 'mk':   // ساختن از همین یکی
                $this->make($u, [[$t, $item]], $x, 'copy');
                break;
            case 'ms':
                $this->make($u, [[$t, $item]], $x, 'similar');
                break;
        }
    }

    private static function chunks(string $text, int $n = 3800): array
    {
        $out = [];
        $cur = '';
        foreach (explode("\n", $text) as $line) {
            if (mb_strlen($cur) + mb_strlen($line) + 1 > $n && $cur !== '') {
                $out[] = $cur;
                $cur = '';
            }
            $cur .= ($cur === '' ? '' : "\n") . $line;
        }

        return $cur !== '' ? [...$out, $cur] : $out;
    }

    private static function makeRows(string $t, int $id): array
    {
        return [
            [['text' => '📝 آزمون از این', 'data' => "lb:mk:{$t}:{$id}:exam"], ['text' => '🎮 بازی از این', 'data' => "lb:mk:{$t}:{$id}:game"], ['text' => '🏆 مسابقه از این', 'data' => "lb:mk:{$t}:{$id}:live"]],
            [['text' => '🤖 سؤال‌های تازه‌ی مشابه (آزمون)', 'data' => "lb:ms:{$t}:{$id}:exam"]],
            [['text' => '↩️ ' . BotItems::TYPES[$t], 'data' => "lb:v:{$t}:{$id}"]],
        ];
    }

    private function listItems(User $u, string $t, int $page, int $msgId): void
    {
        $it = new BotItems($u);
        $total = $it->query($t)->count();
        $rows = $it->query($t)->latest('id')->skip($page * self::PAGE)->take(self::PAGE)->get();
        $L = [BotItems::TYPES[$t] . 'ها — ' . Jalali::fa((string) $total) . ' مورد', ''];
        $btn = [];
        $picks = $this->picks();
        foreach ($rows as $k => $r) {
            $s = $it->summary($t, $r);
            $n = $page * self::PAGE + $k + 1;
            $L[] = Jalali::fa((string) $n) . '. ' . (in_array($t . ':' . $r->id, $picks, true) ? '🧺 ' : '') . $s['title'] . ' — ' . $s['status'] . ' · ' . Jalali::fa((string) $s['questions']) . ' سؤال · '
                . Jalali::fa((string) $s['participants']) . ' شرکت‌کننده · ' . $s['date'];
            $btn[] = ['text' => Jalali::fa((string) $n) . '. ' . Str::limit($r->title, 18), 'data' => "lb:v:{$t}:{$r->id}"];
        }
        if (! $total) {
            $L[] = 'هنوز چیزی ساخته نشده. از منو «📝 ساختِ آزمون» یا «🎮 ساختِ بازی» را بزن.';
        }
        $kb = array_chunk($btn, 2);
        $nav = array_values(array_filter([
            $page > 0 ? ['text' => '→ قبلی', 'data' => "lb:l:{$t}:" . ($page - 1)] : null,
            ($page + 1) * self::PAGE < $total ? ['text' => 'بعدی ←', 'data' => "lb:l:{$t}:" . ($page + 1)] : null,
        ]));
        if ($nav) {
            $kb[] = $nav;
        }
        $kb[] = [['text' => '📚 کارهای من', 'data' => 'lb:home']];
        $this->say($msgId, implode("\n", $L), $kb);
    }

    private function card(User $u, string $t, $item, int $msgId = 0): void
    {
        $it = new BotItems($u);
        $s = $it->summary($t, $item);
        $picked = in_array($t . ':' . $item->id, $this->picks(), true);
        $L = [BotItems::TYPES[$t] . ' «' . $item->title . '»', '📌 وضعیت: ' . $s['status'], '❓ ' . Jalali::fa((string) $s['questions']) . ' سؤال · 👥 ' . Jalali::fa((string) $s['participants']) . ' شرکت‌کننده',
            '🗓 ساخته‌شده: ' . $s['date'] . ($s['subject'] ? ' · 📚 ' . $s['subject'] : '')];
        if ($t === 'live') {
            $L[] = '🔢 کدِ ورود: ' . Jalali::fa((string) $item->code);
        }
        $rows = [[['text' => '📋 سؤال‌ها', 'data' => "lb:q:{$t}:{$item->id}"], ['text' => '📊 نتیجه', 'data' => "lb:r:{$t}:{$item->id}"]]];
        $rows[] = match ($t) {
            'exam' => in_array($item->status, ['published', 'scheduled'], true)
                ? [['text' => '⏸ بستنِ آزمون', 'data' => "lb:s:exam:{$item->id}:closed"]] : [['text' => '🚀 انتشار', 'data' => "lb:s:exam:{$item->id}:published"]],
            'game' => $item->status === 'published'
                ? [['text' => '⏸ غیرفعال کن', 'data' => "lb:s:game:{$item->id}:disabled"]] : [['text' => '🚀 انتشار', 'data' => "lb:s:game:{$item->id}:published"]],
            'live' => BotQuiz::liveRows($item->id),
        };
        $rows[] = [['text' => '📝 آزمون از این', 'data' => "lb:mk:{$t}:{$item->id}:exam"], ['text' => '🎮 بازی از این', 'data' => "lb:mk:{$t}:{$item->id}:game"], ['text' => '🏆 مسابقه از این', 'data' => "lb:mk:{$t}:{$item->id}:live"]];
        $rows[] = [['text' => $picked ? '➖ از انتخاب‌ها بردار' : '➕ انتخاب (برای ساختن از چند مورد)', 'data' => "lb:pk:{$t}:{$item->id}"], ['text' => '🗑 حذف', 'data' => "lb:d:{$t}:{$item->id}"]];
        $rows[] = [['text' => '↩️ فهرست', 'data' => "lb:l:{$t}:0"], ['text' => '📚 کارهای من', 'data' => 'lb:home']];
        $this->say($msgId, implode("\n", $L), $rows);
    }

    private function results(User $u, string $t, $item): void
    {
        if ($t === 'live') {
            $this->bot->quizFor($this->chat)->onButton($u, 'q:lvr:' . $item->id, 0);

            return;
        }
        $data = new BotData($u, $this->bot);
        $r = $data->run($t === 'exam' ? 'exam_results' : 'games', ['title' => $item->title]);
        $L = ['📊 ' . BotItems::TYPES[$t] . ' «' . $item->title . '»', ''];
        $res = $r['results'] ?? [];
        foreach (array_slice($res, 0, 40) as $k => $x) {
            $L[] = (['🥇', '🥈', '🥉'][$k] ?? Jalali::fa((string) ($k + 1)) . '.') . ' ' . $x['name'] . ' — '
                . ($t === 'exam' ? Jalali::fa((string) ($x['percent'] ?? 0)) . '٪' . (! empty($x['needs_review']) ? ' (منتظرِ تصحیحِ تشریحی)' : '')
                    : Jalali::fa((string) $x['score']) . ' از ' . Jalali::fa((string) $x['max']));
        }
        if (! $res) {
            $L[] = 'هنوز کسی شرکت نکرده.';
        }
        $not = $r['not_started'] ?? ($r['not_played'] ?? []);
        if ($not) {
            $L[] = '';
            $L[] = '⏳ هنوز شرکت نکرده (' . Jalali::fa((string) count($not)) . '): ' . Str::limit(implode('، ', $not), 600);
        }
        if ($t === 'exam' && ! empty($r['results'])) {
            $L[] = '';
            $L[] = '📈 میانگین: ' . Jalali::fa((string) (int) round(collect($r['results'])->avg('percent'))) . '٪';
        }
        $this->api->send($this->chat, Str::limit(implode("\n", $L), 4000), [[['text' => '↩️ ' . BotItems::TYPES[$t], 'data' => "lb:v:{$t}:{$item->id}"]]]);
    }

    private function setStatus(User $u, string $t, $item, string $status, int $msgId): void
    {
        try {
            if ($t === 'exam' && in_array($status, ['published', 'closed', 'draft'], true)) {
                $this->bot->callSite($u, \App\Http\Controllers\Teacher\SmartExamController::class, 'status', ['status' => $status], ['smartExam' => $item]);
            } elseif ($t === 'game' && in_array($status, ['published', 'disabled', 'draft'], true)) {
                $this->bot->callSite($u, \App\Http\Controllers\Teacher\EduGameController::class, 'status', ['status' => $status], ['eduGame' => $item]);
            }
        } catch (\Throwable $e) {
            $this->api->send($this->chat, '⚠️ وضعیت عوض نشد: ' . Str::limit($e->getMessage(), 150));

            return;
        }
        $this->api->send($this->chat, ($status === 'published' ? '🚀 منتشر شد و به بچه‌ها خبر داده شد: ' : '⏸ بسته شد: ') . '«' . $item->title . '»');
        $this->card($u, $t, $item->fresh(), $msgId);
    }

    private function destroy(User $u, string $t, $item, int $msgId): void
    {
        try {
            match ($t) {
                'exam' => $this->bot->callSite($u, \App\Http\Controllers\Teacher\SmartExamController::class, 'destroy', [], ['smartExam' => $item]),
                'game' => $this->bot->callSite($u, \App\Http\Controllers\Teacher\EduGameController::class, 'destroy', [], ['eduGame' => $item]),
                'live' => $item->players()->exists()
                    ? throw new \RuntimeException('بچه‌ها واردِ این مسابقه شده‌اند؛ از سایت حذف کن یا «⏹ پایان» بزن.')
                    : $item->delete(),
            };
        } catch (\Throwable $e) {
            $this->say($msgId, '⚠️ حذف نشد: ' . Str::limit($e->getMessage(), 200), [[['text' => '↩️ بازگشت', 'data' => "lb:v:{$t}:{$item->id}"]]]);

            return;
        }
        $this->say($msgId, '🗑 «' . $item->title . '» حذف شد.', [[['text' => '↩️ ' . BotItems::TYPES[$t] . 'ها', 'data' => "lb:l:{$t}:0"], ['text' => '📚 کارهای من', 'data' => 'lb:home']]]);
    }

    private function resolvePicks(User $u): array
    {
        $it = new BotItems($u);
        $out = [];
        foreach ($this->picks() as $k) {
            [$t, $id] = explode(':', $k) + [null, null];
            if ($t && ($m = $it->find($t, (int) $id))) {
                $out[] = [$t, $m];
            }
        }

        return $out;
    }

    private function basket(User $u, int $msgId): void
    {
        $refs = $this->resolvePicks($u);
        $L = ['🧺 انتخاب‌شده‌ها:'];
        foreach ($refs as [$t, $m]) {
            $L[] = '• ' . BotItems::TYPES[$t] . ' «' . $m->title . '»';
        }
        $L[] = '';
        $L[] = 'از سؤال‌های همه‌ی این‌ها چه بسازم؟ (سؤال‌های تکراری یک بار می‌آیند)';
        $this->say($msgId, implode("\n", $L), [
            [['text' => '📝 آزمون', 'data' => 'lb:mkp:exam'], ['text' => '🎮 بازی', 'data' => 'lb:mkp:game'], ['text' => '🏆 مسابقه‌ی زنده', 'data' => 'lb:mkp:live']],
            [['text' => '🤖 آزمونِ تازه با سؤال‌های مشابه', 'data' => 'lb:mks:exam']],
            [['text' => '🧹 خالی کردنِ انتخاب‌ها', 'data' => 'lb:clr'], ['text' => '📚 کارهای من', 'data' => 'lb:home']],
        ]);
    }

    /** ساختنِ آزمون/بازی/مسابقه از سؤال‌های چند مورد → استودیوی سؤال (بازبینی، قوانین، انتشار). */
    public function make(User $u, array $refs, string $kind, string $mode = 'copy', array $opt = []): void
    {
        if (! $refs) {
            $this->api->send($this->chat, 'چیزی انتخاب نشده.', [[['text' => '📚 کارهای من', 'data' => 'lb:home']]]);

            return;
        }
        $c = (new BotItems($u))->collect($refs);
        Cache::forget($this->key('lbpick'));
        $this->bot->quizFor($this->chat)->seed($u, $kind, $c['questions'], ['from' => $c['from'], 'mode' => $mode] + $opt);
    }
}
