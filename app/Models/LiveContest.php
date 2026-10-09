<?php

namespace App\Models;

use App\Services\GamificationService;
use App\Support\DbSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «🏆 مسابقه‌ی زنده»: سؤال روی تخته/ویدئوپروژکتور، پاسخ با گوشی یا تبلت.
 * مرحله‌ها: draft → lobby → (question ↔ reveal)… → end.
 * حالتِ «خودکار»: سرِ ساعت شروع می‌شود و هر مرحله با زمان جلو می‌رود؛
 * حالتِ «دستی»: معلم از روی تخته جلو می‌برد (تمام‌شدنِ زمانِ سؤال همیشه جواب را نشان می‌دهد).
 */
class LiveContest extends Model
{
    protected $fillable = ['school_id', 'teacher_id', 'classroom_id', 'title', 'code', 'mode', 'starts_at', 'phase', 'current', 'phase_at', 'seconds', 'questions', 'rewarded'];

    protected $casts = ['questions' => 'array', 'starts_at' => 'datetime', 'rewarded' => 'boolean', 'current' => 'integer', 'phase_at' => 'integer', 'seconds' => 'integer'];

    public const REVEAL_MS = 7000;   // نمایشِ جواب در حالتِ خودکار
    public const GRACE_MS = 1500;    // تأخیرِ شبکه‌ی گوشیِ بچه‌ها
    public const LEAD_MS = 3500;     // «۳، ۲، ۱» پیش از هر سؤال: بچه‌ها سؤال را می‌خوانند، بعد گزینه‌ها باز می‌شوند
    public const HOST_IDLE_S = 12;   // اگر تخته‌ی معلم این‌قدر باز نبود، مسابقه خودش جلو می‌رود
    public const STALE_MIN = 30;     // مسابقه‌ی نیمه‌کاره بعد از این مدت خودش تمام می‌شود (و امتیازها ثبت می‌شود)
    public const SCHEDULE_WINDOW_H = 3; // شروعِ خودکار فقط تا این چند ساعت بعد از زمانِ تعیین‌شده
    public const SHAPES = ['▲', '◆', '●', '■'];

    public static function ready(): bool
    {
        return DbSchema::hasTable('live_contests') && DbSchema::hasTable('live_contest_answers');
    }

    public static function nowMs(): int
    {
        return (int) now()->getTimestampMs(); // از ساعتِ لاراول (در تست‌ها قابلِ جابه‌جایی)
    }

    public function players(): HasMany { return $this->hasMany(LiveContestPlayer::class); }

    public function answers(): HasMany { return $this->hasMany(LiveContestAnswer::class); }

    public function total(): int { return count($this->questions ?? []); }

    public function live(): bool { return in_array($this->phase, ['question', 'reveal'], true); }

    public function elapsed(): int { return $this->phase_at ? max(0, self::nowMs() - $this->phase_at) : 0; }

    /** میلی‌ثانیه‌ی باقی‌مانده تا باز شدنِ گزینه‌ها (شمارشِ «۳، ۲، ۱»). */
    public function lead(): int
    {
        return $this->phase === 'question' && $this->phase_at ? max(0, $this->phase_at - self::nowMs()) : 0;
    }

    /** سؤالِ طلایی: آخرین سؤال (از سه سؤال به بالا) امتیازِ دوبرابر دارد. */
    public function golden(int $q): bool
    {
        return $this->total() >= 3 && $q === $this->total() - 1;
    }

    /** تخته‌ی معلم همین الان باز است؟ (هر بار که تخته وضعیت می‌گیرد، علامت می‌خورد) */
    public function hostOnline(): bool
    {
        $at = \Illuminate\Support\Facades\Cache::get('live-host:' . $this->id);

        return $at && now()->timestamp - (int) $at <= self::HOST_IDLE_S;
    }

    public function markHost(): void
    {
        \Illuminate\Support\Facades\Cache::put('live-host:' . $this->id, now()->timestamp, 120);
    }

    /** مسابقه خودش جلو برود؟ حالتِ خودکار، یا حالتِ دستی وقتی تخته‌ی معلم بسته است. */
    public function selfDriving(): bool
    {
        return $this->mode === 'auto' || ! $this->hostOnline();
    }

    /** زمانِ تعیین‌شده رسیده و هنوز شروع نشده (در هر دو حالت). */
    public function due(): bool
    {
        return $this->phase === 'lobby' && $this->starts_at && $this->starts_at->lte(now())
            && $this->starts_at->gt(now()->subHours(self::SCHEDULE_WINDOW_H)) && $this->total() > 0;
    }

    /** میلی‌ثانیه‌ی باقی‌مانده‌ی سؤالِ جاری. */
    public function remaining(): int
    {
        return $this->phase === 'question' ? max(0, $this->seconds * 1000 - $this->elapsed()) : 0;
    }

    /** کلاس‌هایی که این مسابقه را می‌بینند (یک کلاس یا همه‌ی کلاس‌های معلم). */
    public function classroomIds(): array
    {
        return $this->classroom_id ? [$this->classroom_id] : Classroom::where('teacher_id', $this->teacher_id)->pluck('id')->all();
    }

    public function audienceIds(): array
    {
        return DB::table('classroom_student')->whereIn('classroom_id', $this->classroomIds())->distinct()->pluck('student_id')->map(fn ($v) => (int) $v)->all();
    }

    public static function forStudent(User $student)
    {
        $rooms = DB::table('classroom_student')->where('student_id', $student->id)->pluck('classroom_id');
        $teachers = Classroom::whereIn('id', $rooms)->pluck('teacher_id')->filter();

        return static::where('phase', '!=', 'draft')->where(fn ($q) => $q->whereIn('classroom_id', $rooms)
            ->orWhere(fn ($q) => $q->whereNull('classroom_id')->whereIn('teacher_id', $teachers)));
    }

    /** جابه‌جاییِ اتمی (اگر کسِ دیگری زودتر جلو برده باشد، کاری نمی‌کند). */
    public function moveTo(string $phase, ?int $current = null): bool
    {
        $current ??= $this->current;
        // سؤالِ تازه با چند ثانیه «آماده باش» شروع می‌شود (phase_at در آینده)
        $at = self::nowMs() + ($phase === 'question' ? self::LEAD_MS : 0);
        $n = static::whereKey($this->id)->where('phase', $this->phase)->where('current', $this->current)
            ->update(['phase' => $phase, 'current' => $current, 'phase_at' => $at, 'updated_at' => now()]);
        $this->refresh();
        if ($n && $phase === 'end') {
            $this->reward();
        }

        return (bool) $n;
    }

    /** مرحله‌ی بعد از جواب: سؤالِ بعد یا پایان. */
    public function next(): bool
    {
        return $this->current + 1 < $this->total() ? $this->moveTo('question', $this->current + 1) : $this->moveTo('end');
    }

    /** پیش‌رفتِ زمانی؛ در هر بار پرسیدنِ وضعیت (تخته، گوشیِ بچه‌ها، فهرست‌ها) صدا زده می‌شود. */
    public function tick(): void
    {
        for ($guard = 0; $guard < 4; $guard++) {
            // سرِ ساعتِ تعیین‌شده خودش شروع می‌شود (چه دستی، چه خودکار)
            if ($this->due()) {
                if (! $this->moveTo('question', 0)) return;
                continue;
            }
            // نیمه‌کاره رها شده → پایان، تا امتیازِ بچه‌ها حتماً ثبت شود
            if ($this->live() && $this->updated_at && $this->updated_at->lt(now()->subMinutes(self::STALE_MIN))) {
                if (! $this->moveTo('end')) return;
                continue;
            }
            if ($this->phase === 'question' && $this->elapsed() >= $this->seconds * 1000 + 600) {
                if (! $this->moveTo('reveal')) return;
                continue;
            }
            // بدونِ تخته: وقتی همه‌ی حاضرها جواب دادند، زودتر جواب را نشان بده
            if ($this->phase === 'question' && $this->lead() === 0 && $this->selfDriving() && $this->allAnswered()) {
                if (! $this->moveTo('reveal')) return;
                continue;
            }
            if ($this->phase === 'reveal' && $this->elapsed() >= self::REVEAL_MS && $this->selfDriving()) {
                if (! $this->next()) return;
                continue;
            }

            return;
        }
    }

    /** همه‌ی بچه‌هایی که الان در مسابقه‌اند به سؤالِ جاری جواب داده‌اند؟ */
    public function allAnswered(): bool
    {
        $online = $this->players()->where('last_seen_at', '>=', now()->subSeconds(20))->count();

        return $online > 0 && $this->answers()->where('q_index', $this->current)->count() >= $online;
    }

    /** امتیازِ یک پاسخِ درست: ۵۰۰ پایه + تا ۵۰۰ برای سرعت + جایزه‌ی زنجیره. */
    public function points(int $ms, int $streakBefore): int
    {
        $t = max(1, $this->seconds * 1000);
        $base = 500 + (int) round(500 * (1 - min($ms, $t) / $t)) + min(300, 100 * $streakBefore);

        return $this->golden($this->current) ? $base * 2 : $base;
    }

    /** پاسخِ دانش‌آموز. */
    public function answer(User $student, int $q, int $choice): array
    {
        $this->tick();
        if ($this->phase !== 'question' || $this->current !== $q) {
            return ['ok' => false, 'message' => 'زمانِ این سؤال تمام شده است.'];
        }
        if ($this->lead() > 300) {
            return ['ok' => false, 'message' => 'صبر کن تا گزینه‌ها باز شوند!'];
        }
        $ms = $this->elapsed();
        if ($ms > $this->seconds * 1000 + self::GRACE_MS) {
            return ['ok' => false, 'message' => 'زمانِ این سؤال تمام شده است.'];
        }
        $question = $this->questions[$q] ?? null;
        if (! $question || $choice < 0 || $choice >= count($question['choices'] ?? [])) {
            return ['ok' => false, 'message' => 'گزینه نامعتبر است.'];
        }

        return DB::transaction(function () use ($student, $q, $choice, $ms, $question) {
            $player = LiveContestPlayer::firstOrCreate(['live_contest_id' => $this->id, 'student_id' => $student->id]);
            if (LiveContestAnswer::where('live_contest_id', $this->id)->where('student_id', $student->id)->where('q_index', $q)->exists()) {
                return ['ok' => true, 'already' => true];
            }
            $correct = (int) ($question['answer'] ?? -1) === $choice;
            $points = $correct ? $this->points($ms, (int) $player->streak) : 0;
            LiveContestAnswer::create([
                'live_contest_id' => $this->id, 'student_id' => $student->id, 'q_index' => $q, 'choice' => $choice,
                'correct' => $correct, 'points' => $points, 'ms' => min($ms, 65000), 'created_at' => now(),
            ]);
            $player->update([
                'score' => $player->score + $points, 'correct' => $player->correct + ($correct ? 1 : 0),
                'streak' => $correct ? $player->streak + 1 : 0, 'last_seen_at' => now(),
            ]);

            return ['ok' => true];
        });
    }

    /** رتبه‌بندی (امتیاز، بعد درست‌ها) با جابه‌جاییِ رتبه نسبت به پیش از سؤالِ جاری (▲/▼). */
    public function ranking(): Collection
    {
        $players = $this->players()->with('student:id,name,avatar')->orderByDesc('score')->orderByDesc('correct')->orderBy('id')->get()->values();
        $gained = in_array($this->phase, ['reveal', 'end'], true) && $this->current >= 0
            ? $this->answers()->where('q_index', $this->current)->pluck('points', 'student_id') : collect();
        $before = $players->sortBy([fn ($a, $b) => (($b->score - ($gained[$b->student_id] ?? 0)) <=> ($a->score - ($gained[$a->student_id] ?? 0))), fn ($a, $b) => $a->id <=> $b->id])
            ->values()->pluck('student_id')->flip();

        return $players->map(fn ($p, $i) => [
            'rank' => $i + 1, 'id' => $p->student_id, 'name' => $p->student?->name ?? '—', 'score' => (int) $p->score,
            'correct' => (int) $p->correct, 'streak' => (int) $p->streak,
            'gained' => (int) ($gained[$p->student_id] ?? 0),
            'delta' => $gained->isEmpty() ? 0 : ($before[$p->student_id] ?? $i) - $i,
        ]);
    }

    /** خلاصه‌ی شخصی برای پایان: دقت، میانگینِ سرعت، بهترین زنجیره و امتیازِ کارنامه. */
    public function summaryFor(int $studentId, int $rank): array
    {
        $rows = $this->answers()->where('student_id', $studentId)->orderBy('q_index')->get(['correct', 'ms']);
        $best = 0; $run = 0;
        foreach ($rows as $r) {
            $run = $r->correct ? $run + 1 : 0;
            $best = max($best, $run);
        }
        $correct = $rows->where('correct', true)->count();

        return [
            'answered' => $rows->count(), 'correct' => $correct,
            'accuracy' => $this->total() ? (int) round(100 * $correct / $this->total()) : 0,
            'avg_s' => $rows->where('correct', true)->count() ? round($rows->where('correct', true)->avg('ms') / 1000, 1) : null,
            'best_streak' => $best, 'xp' => self::xpFor($correct, $rank),
        ];
    }

    /** امتیازِ کارنامه (XP): شرکت ۲، هر پاسخِ درست ۳، سکو ۱۵/۱۰/۵. */
    public static function xpFor(int $correct, int $rank): int
    {
        return 2 + 3 * $correct + ([1 => 15, 2 => 10, 3 => 5][$rank] ?? 0);
    }

    /** پخشِ پاسخ‌ها روی گزینه‌های یک سؤال (برای نمودار). */
    public function distribution(int $q): array
    {
        $n = count($this->questions[$q]['choices'] ?? []);
        $rows = LiveContestAnswer::where('live_contest_id', $this->id)->where('q_index', $q)
            ->selectRaw('choice, count(*) as c')->groupBy('choice')->pluck('c', 'choice');

        return collect(range(0, max(0, $n - 1)))->map(fn ($i) => (int) ($rows[$i] ?? 0))->all();
    }

    /** پایان: امتیاز (XP) یک بار — هر پاسخِ درست ۳، سکو ۱۵/۱۰/۵، شرکت ۲. */
    public function reward(): void
    {
        if (! static::whereKey($this->id)->where('rewarded', false)->update(['rewarded' => true])) {
            return;
        }
        rescue(function () {
            $game = app(GamificationService::class);
            $teacher = User::find($this->teacher_id);
            foreach ($this->ranking() as $r) {
                $xp = self::xpFor($r['correct'], $r['rank']);
                $student = User::find($r['id']);
                if ($student && $xp > 0) {
                    $game->award($student, $xp, '🏆 مسابقه‌ی زنده: ' . $this->title . ' (رتبه‌ی ' . $r['rank'] . ')', $teacher, 'live_contest', $this->id);
                }
            }
        }, null, true);
    }

    /** سؤال‌ها را به شکلِ یکسان درمی‌آورد: {prompt, choices[str], answer:int}. */
    public static function normalize(array $qs): array
    {
        return collect($qs)->map(function ($q) {
            $choices = collect($q['choices'] ?? [])->map(fn ($c) => is_array($c) ? trim((string) ($c['value'] ?? $c['text'] ?? '')) : trim((string) $c))
                ->values();
            $answer = isset($q['answer']) && is_numeric($q['answer']) ? (int) $q['answer']
                : collect($q['choices'] ?? [])->search(fn ($c) => is_array($c) && ! empty($c['correct']));
            // گزینه‌های خالی حذف (با جابه‌جاییِ اندیسِ جواب)
            $keep = $choices->keys()->filter(fn ($k) => $choices[$k] !== '')->values();
            $answer = $answer === false ? -1 : $keep->search($answer);

            return [
                'prompt' => trim((string) ($q['prompt'] ?? '')),
                'choices' => $keep->map(fn ($k) => mb_substr($choices[$k], 0, 120))->take(4)->values()->all(),
                'answer' => $answer === false || $answer > 3 ? -1 : (int) $answer,
            ];
        })->filter(fn ($q) => $q['prompt'] !== '' && count($q['choices']) >= 2 && $q['answer'] >= 0)->values()->all();
    }

    public static function newCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
