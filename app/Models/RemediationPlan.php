<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * زمان‌بندیِ «مرورِ اشتباه‌ها» برای یک کلاس — همان چیزی که معلم اعلام می‌کند.
 *
 * - first_delay: نوبتِ اول چند روز بعد از اشتباه (۰ = همان روز)
 * - rounds: چند نوبت مرور تا «جبران شد»
 * - gaps: فاصله‌ی روزِ هر نوبت تا نوبتِ بعد (بعد از قبولی)
 * - retry: نوبتِ ردشده چند روز بعد دوباره بیاید
 * - per_session: چند اشتباه در هر جلسه‌ی مرور (هر اشتباه = همان سؤال + سؤال‌های مشابه)
 * - similar: چند سؤالِ مشابه کنارِ هر سؤالِ اشتباه
 * - share: چند درصدِ امتیازِ از دست‌رفته برمی‌گردد (حداکثر ۵۰ تا کسی که از اول درست زده جلو بماند)
 * - pass: درصدِ قبولیِ هر نوبت
 * - sources: اشتباهِ کدام بخش‌ها خودکار مرور بسازد
 */
class RemediationPlan extends Model
{
    protected $fillable = ['classroom_id', 'teacher_id', 'settings'];

    protected $casts = ['settings' => 'array'];

    public const DEFAULTS = [
        'enabled' => true,
        'sources' => ['exam' => true, 'game' => true, 'mission' => true, 'grade' => true],
        'first_delay' => 0,
        'rounds' => 3,
        'gaps' => [2, 5],
        'retry' => 1,
        'per_session' => 4,
        'similar' => 2,
        'share' => 50,
        'pass' => 60,
    ];

    /** تنظیماتِ کامل و معتبر (هر مقدارِ ناقص یا خارج از محدوده با پیش‌فرض/مرز جایگزین می‌شود). */
    public static function normalize(?array $s): array
    {
        $d = self::DEFAULTS;
        $s = (array) $s;
        $clamp = fn ($v, $min, $max, $def) => is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
        $rounds = $clamp($s['rounds'] ?? null, 1, 5, $d['rounds']);
        $gaps = array_values((array) ($s['gaps'] ?? $d['gaps']));
        $out = [];
        for ($i = 0; $i < $rounds - 1; $i++) {
            $out[] = $clamp($gaps[$i] ?? null, 1, 30, $d['gaps'][$i] ?? (end($d['gaps']) + 2 * ($i - 1)));
        }
        $src = (array) ($s['sources'] ?? []);

        return [
            'enabled' => array_key_exists('enabled', $s) ? (bool) $s['enabled'] : true,
            'sources' => collect($d['sources'])->map(fn ($v, $k) => array_key_exists($k, $src) ? (bool) $src[$k] : $v)->all(),
            'first_delay' => $clamp($s['first_delay'] ?? null, 0, 14, $d['first_delay']),
            'rounds' => $rounds,
            'gaps' => $out,
            'retry' => $clamp($s['retry'] ?? null, 1, 7, $d['retry']),
            'per_session' => $clamp($s['per_session'] ?? null, 1, 8, $d['per_session']),
            'similar' => $clamp($s['similar'] ?? null, 0, 4, $d['similar']),
            'share' => $clamp($s['share'] ?? null, 0, 50, $d['share']),
            'pass' => $clamp($s['pass'] ?? null, 40, 100, $d['pass']),
        ];
    }

    public static function ready(): bool
    {
        static $ok = null;

        return $ok ??= (bool) rescue(fn () => Schema::hasTable('remediation_plans'), false, false);
    }

    public static function forClassroom(?int $classroomId): array
    {
        if (! $classroomId || ! self::ready()) {
            return self::normalize([]);
        }

        return self::normalize(static::where('classroom_id', $classroomId)->first()?->settings);
    }

    /** کلاسِ مرتبط با یک دانش‌آموز: کلاسِ همان معلم، وگرنه اولین کلاسِ او. */
    public static function classroomFor(User $student, ?int $teacherId = null): ?int
    {
        $q = $student->classrooms();
        $id = $teacherId ? (clone $q)->where('classrooms.teacher_id', $teacherId)->value('classrooms.id') : null;

        return $id ?: $q->value('classrooms.id');
    }

    public static function forStudent(User $student, ?int $teacherId = null): array
    {
        return self::forClassroom(self::classroomFor($student, $teacherId));
    }

    public static function put(int $classroomId, ?int $teacherId, array $settings): array
    {
        $n = self::normalize($settings);
        static::updateOrCreate(['classroom_id' => $classroomId], ['teacher_id' => $teacherId, 'settings' => $n]);

        return $n;
    }
}
