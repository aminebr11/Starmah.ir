<?php

namespace App\Services;

use App\Models\ClassroomPref;
use App\Models\ScreenTime;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * «🌿 سلامتِ دیجیتال»: یادآوریِ استراحت، سقفِ روزانه و سقفِ هر بار حضور (تنظیمِ معلم برای هر کلاس)،
 * قفلِ حساب بعد از سقف، و بازکردن فقط با رمزِ والدین (صفر کردنِ زمان یا سقفِ کمترِ والدین).
 */
class WellbeingService
{
    public const KEY = 'wellbeing';

    /** ۰ یعنی خاموش. */
    public const DEFAULTS = ['daily' => 0, 'session' => 0, 'break_every' => 25, 'break_minutes' => 3];

    /** فاصله‌ای که بعد از آن «یک بار حضورِ تازه» شروع می‌شود (ثانیه). */
    public const GAP = 1200;

    /** بیشترین زمانی که یک ضربان حساب می‌کند (ضربان‌ها دقیقه‌ای‌اند). */
    public const MAX_STEP = 75;

    public static function classSettings(?int $classroomId): array
    {
        return ClassroomPref::get($classroomId, self::KEY, self::DEFAULTS);
    }

    /** سخت‌گیرانه‌ترین تنظیم میانِ کلاس‌های دانش‌آموز. */
    public function classLimits(User $student): array
    {
        return Cache::remember('wb-limits:' . $student->id, 60, function () use ($student) {
            $rooms = DB::table('classroom_student')->where('student_id', $student->id)->pluck('classroom_id');
            $sets = $rooms->isEmpty() ? collect([self::DEFAULTS]) : $rooms->map(fn ($id) => self::classSettings((int) $id));
            $minPos = fn ($k) => (int) ($sets->pluck($k)->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->min() ?? 0);

            return ['daily' => $minPos('daily'), 'session' => $minPos('session'), 'break_every' => $minPos('break_every'),
                'break_minutes' => (int) $sets->max('break_minutes')];
        });
    }

    /** سقف‌های مؤثر: معلم + (اگر کمتر باشد) والدین. */
    public function limits(User $student): array
    {
        $c = $this->classLimits($student);
        $p = (array) data_get($student->settings, 'wellbeing', []);
        $eff = function ($teacher, $parent) {
            $parent = (int) $parent;
            if ($parent <= 0) return $teacher;

            return $teacher > 0 ? min($teacher, $parent) : $parent;
        };

        return array_merge($c, [
            'class_daily' => $c['daily'], 'class_session' => $c['session'],
            'parent_daily' => (int) ($p['daily'] ?? 0), 'parent_session' => (int) ($p['session'] ?? 0),
            'daily' => $eff($c['daily'], $p['daily'] ?? 0), 'session' => $eff($c['session'], $p['session'] ?? 0),
        ]);
    }

    private function row(User $student, bool $create = false): ?ScreenTime
    {
        if (! ScreenTime::ready()) return null;
        $row = ScreenTime::where('student_id', $student->id)->whereDate('day', now()->toDateString())->first();
        if ($row || ! $create) {
            return $row;
        }
        try {
            return ScreenTime::create(['student_id' => $student->id, 'day' => now()->toDateString(), 'seconds' => 0, 'session_seconds' => 0, 'resets' => 0]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // دو ضربانِ هم‌زمان
            return ScreenTime::where('student_id', $student->id)->whereDate('day', now()->toDateString())->first();
        }
    }

    private function sessionOf(?ScreenTime $r): int
    {
        return $r && $r->last_beat_at && $r->last_beat_at->gt(now()->subSeconds(self::GAP)) ? (int) $r->session_seconds : 0;
    }

    /** وضعیتِ فعلی (برای قفل، صفحه‌ی «وقت تمام شد» و کلاینت). */
    public function status(User $student, ?ScreenTime $r = null, ?array $lim = null): array
    {
        $lim ??= $this->limits($student);
        $r ??= $this->row($student);
        $used = (int) ($r->seconds ?? 0);
        $session = $this->sessionOf($r);
        $reason = null;
        if ($lim['daily'] > 0 && $used >= $lim['daily'] * 60) $reason = 'daily';
        elseif ($lim['session'] > 0 && $session >= $lim['session'] * 60) $reason = 'session';
        $left = collect([$lim['daily'] > 0 ? $lim['daily'] * 60 - $used : null, $lim['session'] > 0 ? $lim['session'] * 60 - $session : null])
            ->filter(fn ($v) => $v !== null)->min();

        return [
            'used' => $used, 'session' => $session, 'locked' => $reason !== null, 'reason' => $reason,
            'left' => $left === null ? null : max(0, (int) $left),
            'unlock_at' => $reason === 'session' && $r?->last_beat_at ? $r->last_beat_at->copy()->addSeconds(self::GAP)->timestamp : null,
            'limits' => $lim,
        ];
    }

    /** فقط وقتی سقفی فعال است پرس‌وجو می‌کند (برای میان‌افزار، سبک). */
    public function locked(User $student): ?array
    {
        $lim = $this->limits($student);
        if ($lim['daily'] <= 0 && $lim['session'] <= 0) return null;
        $s = $this->status($student, null, $lim);

        return $s['locked'] ? $s : null;
    }

    /** ضربانِ حضور (هر دقیقه وقتی صفحه دیده می‌شود). */
    public function beat(User $student): array
    {
        $lim = $this->limits($student);
        $r = $this->row($student, true);
        if (! $r) return ['enabled' => false];
        $before = $this->status($student, $r, $lim);
        if ($before['locked']) {
            return $before + ['break_due' => false, 'break_minutes' => $lim['break_minutes']];
        }
        $gap = $r->last_beat_at ? now()->diffInSeconds($r->last_beat_at, true) : null;
        $session = $this->sessionOf($r);
        $add = $gap === null || $gap > self::GAP ? 0 : (int) min($gap, self::MAX_STEP);
        $r->forceFill(['seconds' => $r->seconds + $add, 'session_seconds' => $session + $add, 'last_beat_at' => now()])->save();

        $every = $lim['break_every'] * 60;
        $breakDue = $every > 0 && $add > 0 && intdiv($session + $add, $every) > intdiv($session, $every);

        return $this->status($student, $r, $lim) + ['break_due' => $breakDue, 'break_minutes' => $lim['break_minutes']];
    }

    /** صفر کردنِ زمانِ امروز (والدین با رمز، یا معلم). */
    public function reset(User $student): void
    {
        $r = $this->row($student, true);
        $r?->forceFill(['seconds' => 0, 'session_seconds' => 0, 'resets' => $r->resets + 1])->save();
    }

    /** سقفِ والدین (فقط کمتر از سقفِ معلم پذیرفته می‌شود؛ ۰ = بدونِ سقفِ اضافه). */
    public function setParentLimits(User $student, int $daily, int $session): void
    {
        $c = $this->classLimits($student);
        $clip = fn ($v, $t) => $v <= 0 ? 0 : ($t > 0 ? min($v, $t) : $v);
        $settings = (array) ($student->settings ?? []);
        $settings['wellbeing'] = ['daily' => $clip($daily, $c['daily']), 'session' => $clip($session, $c['session'])];
        $student->forceFill(['settings' => $settings])->save();
    }

    public static function forget(iterable $studentIds): void
    {
        foreach ($studentIds as $id) Cache::forget('wb-limits:' . $id);
    }
}
