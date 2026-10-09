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
    public const SHAPES = ['▲', '◆', '●', '■'];

    public static function ready(): bool
    {
        return DbSchema::hasTable('live_contests') && DbSchema::hasTable('live_contest_answers');
    }

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function players(): HasMany { return $this->hasMany(LiveContestPlayer::class); }

    public function answers(): HasMany { return $this->hasMany(LiveContestAnswer::class); }

    public function total(): int { return count($this->questions ?? []); }

    public function live(): bool { return in_array($this->phase, ['question', 'reveal'], true); }

    public function elapsed(): int { return $this->phase_at ? max(0, self::nowMs() - $this->phase_at) : 0; }

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
        $n = static::whereKey($this->id)->where('phase', $this->phase)->where('current', $this->current)
            ->update(['phase' => $phase, 'current' => $current, 'phase_at' => self::nowMs(), 'updated_at' => now()]);
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

    /** پیش‌رفتِ زمانی؛ در هر بار پرسیدنِ وضعیت صدا زده می‌شود. */
    public function tick(): void
    {
        for ($guard = 0; $guard < 3; $guard++) {
            if ($this->mode === 'auto' && $this->phase === 'lobby' && $this->starts_at && $this->starts_at->lte(now()) && $this->total()) {
                if (! $this->moveTo('question', 0)) return;
                continue;
            }
            if ($this->phase === 'question' && $this->elapsed() >= $this->seconds * 1000 + 600) {
                if (! $this->moveTo('reveal')) return;
                continue;
            }
            if ($this->mode === 'auto' && $this->phase === 'reveal' && $this->elapsed() >= self::REVEAL_MS) {
                if (! $this->next()) return;
                continue;
            }

            return;
        }
    }

    /** امتیازِ یک پاسخِ درست: ۵۰۰ پایه + تا ۵۰۰ برای سرعت + جایزه‌ی زنجیره. */
    public function points(int $ms, int $streakBefore): int
    {
        $t = max(1, $this->seconds * 1000);

        return 500 + (int) round(500 * (1 - min($ms, $t) / $t)) + min(300, 100 * $streakBefore);
    }

    /** پاسخِ دانش‌آموز. */
    public function answer(User $student, int $q, int $choice): array
    {
        $this->tick();
        if ($this->phase !== 'question' || $this->current !== $q) {
            return ['ok' => false, 'message' => 'زمانِ این سؤال تمام شده است.'];
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

    /** رتبه‌بندی (امتیاز، بعد درست‌ها). */
    public function ranking(): Collection
    {
        return $this->players()->with('student:id,name,avatar')->orderByDesc('score')->orderByDesc('correct')->orderBy('id')->get()
            ->values()->map(fn ($p, $i) => [
                'rank' => $i + 1, 'id' => $p->student_id, 'name' => $p->student?->name ?? '—', 'score' => (int) $p->score,
                'correct' => (int) $p->correct, 'streak' => (int) $p->streak,
            ]);
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
                $xp = 2 + 3 * $r['correct'] + ([1 => 15, 2 => 10, 3 => 5][$r['rank']] ?? 0);
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
