<?php

namespace App\Services\Bot;

use App\Models\EduGame;
use App\Models\LiveContest;
use App\Models\SmartExam;
use App\Models\User;
use App\Support\Bot\BotParser as P;
use App\Support\Curriculum;
use App\Support\Jalali;
use App\Support\QuestionMedia;
use Illuminate\Support\Collection;

/**
 * 📚 آزمون‌ها، بازی‌ها و مسابقه‌های زنده‌ی خودِ معلم — پیدا کردن، خواندنِ سؤال‌ها و تبدیل به قالبِ یکسان.
 * هم «کارهای من» (دکمه‌ای) و هم دستیارِ هوش مصنوعی از همین استفاده می‌کنند.
 */
class BotItems
{
    public const TYPES = ['exam' => '📝 آزمون', 'game' => '🎮 بازی', 'live' => '🏆 مسابقه‌ی زنده'];

    public const STATUS_FA = ['draft' => 'پیش‌نویس', 'review' => 'بازبینی', 'scheduled' => 'زمان‌دار', 'published' => 'منتشرشده', 'closed' => 'بسته', 'archived' => 'آرشیو',
        'disabled' => 'غیرفعال', 'lobby' => 'منتظرِ شروع', 'question' => 'در حالِ اجرا', 'reveal' => 'در حالِ اجرا', 'end' => 'تمام‌شده'];

    public function __construct(private User $user) {}

    public function query(string $type)
    {
        return match ($type) {
            'exam' => SmartExam::withoutGlobalScopes()->where('teacher_id', $this->user->id),
            'game' => EduGame::withoutGlobalScopes()->where('teacher_id', $this->user->id),
            'live' => LiveContest::withoutGlobalScopes()->where('teacher_id', $this->user->id),
        };
    }

    public function find(string $type, int $id)
    {
        return isset(self::TYPES[$type]) ? $this->query($type)->find($id) : null;
    }

    /** آخرین‌ها، یا آن‌که عنوانش (بی‌حساسیت به اعراب و نیم‌فاصله) می‌خورد. */
    /**
     * آخرین‌ها، یا آن‌که عنوانش (بی‌حساسیت به اعراب و نیم‌فاصله) می‌خورد.
     * $f: date (YYYY-MM-DD؛ ساخته یا منتشر/ویرایش‌شده در آن روز) · days (چند روزِ اخیر) · status (published|draft|closed|…)
     */
    public function search(string $type, ?string $title = null, int $limit = 10, array $f = []): Collection
    {
        $q = $this->query($type)->latest('id');
        if (! empty($f['date'])) {
            try {
                $d = \Illuminate\Support\Carbon::parse(P::digits((string) $f['date']))->toDateString();
                $q->where(fn ($w) => $w->whereDate('created_at', $d)->orWhereDate('updated_at', $d)
                    ->when($type === 'game', fn ($x) => $x->orWhereDate('publish_at', $d))
                    ->when($type === 'exam', fn ($x) => $x->orWhereDate('opens_at', $d))
                    ->when($type === 'live', fn ($x) => $x->orWhereDate('starts_at', $d)));
            } catch (\Throwable $e) {
                // تاریخِ نامعتبر → بدونِ فیلتر
            }
        } elseif (! empty($f['days'])) {
            $from = now()->subDays(max(1, (int) $f['days']) - 1)->startOfDay();
            $q->where(fn ($w) => $w->where('created_at', '>=', $from)->orWhere('updated_at', '>=', $from));
        }
        if (! empty($f['status'])) {
            $st = (string) $f['status'];
            $type === 'live'
                ? $q->when($st === 'published', fn ($w) => $w->where('phase', '!=', 'draft'))
                : $q->where('status', $st);
        }
        $rows = $q->limit($title ? 60 : $limit)->get();
        if ($title !== null && trim($title) !== '') {
            $h = self::flat($title);
            $rows = $rows->filter(fn ($r) => str_contains(self::flat($r->title), $h))->take($limit);
        }

        return $rows->values();
    }

    /**
     * پارامترهای دستیار → فهرستِ [نوع، مورد]:
     * from_type/type، titles[] یا title، last_n/last، date، days، status
     */
    public function refs(array $a): array
    {
        $type = $a['from_type'] ?? ($a['type'] ?? null);
        $types = isset(self::TYPES[$type]) ? [$type] : array_keys(self::TYPES);
        $f = array_filter(['date' => $a['date'] ?? null, 'days' => $a['days'] ?? null, 'status' => $a['status'] ?? null]);
        $titles = array_values(array_filter(array_map('strval', (array) ($a['titles'] ?? (isset($a['title']) ? [$a['title']] : [])))));
        $n = max(1, min(15, (int) ($a['last_n'] ?? ($a['last'] ?? ($f ? 15 : 1)))));
        $refs = [];
        foreach ($types as $t) {
            if ($titles) {
                foreach ($titles as $title) {
                    foreach ($this->search($t, $title, 1, $f) as $m) {
                        $refs[] = [$t, $m];
                    }
                }
            } elseif (count($types) === 1 || $f) {
                foreach ($this->search($t, null, $n, $f) as $m) {
                    $refs[] = [$t, $m];
                }
            }
        }

        return $refs;
    }

    public static function flat(string $v): string
    {
        return preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{200C}\s\-–_«»"]+/u', '', P::norm($v)) ?? $v;
    }

    /** خلاصه‌ی یک مورد (برای فهرست و کارت). */
    public function summary(string $type, $item): array
    {
        $n = $type === 'live' ? count($item->questions ?? []) : $item->questions()->count();
        $players = match ($type) {
            'exam' => \App\Models\SmartExamAttempt::where('smart_exam_id', $item->id)->whereIn('status', ['completed', 'needs_review'])->distinct('student_id')->count('student_id'),
            'game' => \App\Models\EduGameAttempt::where('edu_game_id', $item->id)->distinct('student_id')->count('student_id'),
            'live' => $item->players()->count(),
        };

        return ['id' => $item->id, 'type' => $type, 'title' => $item->title, 'status' => self::STATUS_FA[$type === 'live' ? $item->phase : $item->status] ?? ($item->status ?? $item->phase),
            'subject' => $item->subject ?? null, 'questions' => $n, 'participants' => $players, 'date' => Jalali::format($item->created_at)];
    }

    /**
     * سؤال‌های یک مورد به قالبِ استودیوی سؤالِ بات:
     * {type, prompt, choices[{value,correct}], answer, explanation, hint, difficulty, bloom, topic, media_url}
     */
    public function questions(string $type, $item): array
    {
        if ($type === 'live') {
            return collect($item->questions ?? [])->map(fn ($q) => [
                'type' => count($q['choices'] ?? []) === 2 && in_array($q['choices'][0] ?? '', ['درست', 'نادرست'], true) ? 'tf' : 'mc',
                'prompt' => (string) ($q['prompt'] ?? ''),
                'choices' => collect($q['choices'] ?? [])->values()->map(fn ($c, $i) => ['value' => (string) $c, 'correct' => $i === (int) ($q['answer'] ?? -1)])->all(),
                'answer' => null, 'explanation' => null, 'hint' => null, 'difficulty' => 'medium', 'bloom' => null, 'topic' => null, 'media_url' => null,
            ])->filter(fn ($q) => $q['prompt'] !== '')->values()->all();
        }
        $rows = $item->questions()->orderBy('sort')->get();

        return $rows->map(function ($q) use ($type) {
            $t = $q->type === 'short' ? 'blank' : $q->type;
            $choices = \App\Support\BankAccess::cleanChoices($q->choices ?? []);
            $raw = $q->answer;
            $answer = is_array($raw) ? (string) (array_values($raw)[0] ?? '') : (string) ($raw ?? '');
            if ($t === 'blank' && $answer === '' && $choices) {
                $answer = $choices[0]['value'];   // پاسخِ کوتاهِ بازی در گزینه‌ی اول است
            }

            return [
                'type' => in_array($t, ['mc', 'tf', 'blank', 'desc'], true) ? $t : 'mc',
                'prompt' => (string) $q->prompt,
                'choices' => in_array($t, ['mc', 'tf'], true) ? $choices : [],
                'answer' => $answer !== '' ? $answer : null,
                'explanation' => $q->explanation, 'hint' => $type === 'game' ? $q->hint1 : null,
                'difficulty' => in_array($q->difficulty, ['easy', 'medium', 'hard'], true) ? $q->difficulty : 'medium',
                'bloom' => $q->bloom, 'topic' => $q->topic, 'media_url' => QuestionMedia::url($q->media_path),
                'bank_id' => $q->bank_id,
            ];
        })->filter(fn ($q) => trim($q['prompt']) !== '')->values()->all();
    }

    /** سؤال‌های چند مورد، بدونِ تکراری (بر اساسِ اثرانگشتِ متن). */
    /**
     * @param  int  $perItem  از هر مورد چند سؤال («از هر کدام ۴ تا»)؛ ۰ = همه
     * @param  int  $count  سقفِ کل («مجموعاً ۲۰ تا»)؛ ۰ = بی‌سقف
     * @param  bool  $shuffle  انتخابِ تصادفی (وگرنه از اول)
     */
    public function collect(array $refs, int $perItem = 0, int $count = 0, bool $shuffle = false): array
    {
        $out = [];
        $seen = [];
        $from = [];
        foreach ($refs as [$type, $item]) {
            $from[] = self::TYPES[$type] . ' «' . $item->title . '»';
            $qs = $this->questions($type, $item);
            if ($shuffle) {
                shuffle($qs);
            }
            $took = 0;
            foreach ($qs as $q) {
                if ($perItem > 0 && $took >= $perItem) {
                    break;
                }
                $fp = Curriculum::fingerprint($q['prompt']);
                if (isset($seen[$fp])) {
                    continue;
                }
                $seen[$fp] = true;
                $out[] = $q + ['_from' => $item->title];
                $took++;
            }
        }
        if ($count > 0) {
            $out = array_slice($out, 0, $count);
        }

        return ['questions' => $out, 'from' => $from];
    }

    /** متنِ کوتاهِ یک سؤال برای نمایش. */
    public static function line(array $q, int $i): string
    {
        $ans = BotQuiz::answerOf($q);

        return Jalali::fa((string) ($i + 1)) . ') ' . (! empty($q['media_url']) ? '🖼️ ' : '') . $q['prompt']
            . (in_array($q['type'], ['mc', 'tf'], true) && count($q['choices']) > 2
                ? "\n   " . collect($q['choices'])->map(fn ($c, $k) => (['الف', 'ب', 'ج', 'د', 'ه', 'و'][$k] ?? $k + 1) . ') ' . $c['value'])->implode('  ')
                : '')
            . "\n   ✔ " . $ans;
    }
}
