<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\Setting;
use App\Models\SmartQuestionBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * دسترسیِ بانک سؤالات:
 * - معلم: سؤال‌های خودش (هر پایه) + سؤال‌های مدرسه/اشتراکیِ همان پایه‌ای که تدریس می‌کند.
 * - مدیر مدرسه: در صورت مجوزِ ادمین، ویرایشِ کلِ بانکِ مدرسه‌ی خودش.
 * - ادمین کل: کلِ بانک (همه‌ی مدارس) + ساختِ سؤالِ سراسری.
 * اشتراک‌گذاریِ بین‌مدرسه‌ای را ادمین کل تعیین می‌کند.
 */
class BankAccess
{
    /** حالت اشتراک: off | all | schools */
    public static function shareScope(): string
    {
        return (string) Setting::get('bank_share_scope', 'off');
    }

    public static function sharedSchoolIds(): array
    {
        $v = Setting::get('bank_share_schools', '[]');
        $d = is_array($v) ? $v : json_decode((string) $v, true);
        return is_array($d) ? array_map('intval', $d) : [];
    }

    /** آیا مدرسه به بانکِ اشتراکیِ سراسری دسترسی دارد؟ */
    public static function schoolCanSeeShared(?int $schoolId): bool
    {
        $scope = self::shareScope();
        if ($scope === 'all') {
            return true;
        }
        if ($scope === 'schools') {
            return in_array((int) $schoolId, self::sharedSchoolIds(), true);
        }
        return false;
    }

    /** پایه‌هایی که این معلم تدریس می‌کند. */
    public static function teacherGrades(User $teacher): array
    {
        return Classroom::where('teacher_id', $teacher->id)->pluck('grade')->filter()->unique()->values()->all();
    }

    /** کوئریِ سؤال‌های قابل‌مشاهده برای یک کاربر (بدونِ scopeِ چندمستأجری تا بانکِ اشتراکی هم دیده شود). */
    public static function visibleQuery(User $user): Builder
    {
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return SmartQuestionBank::withoutGlobalScopes(); // کل بانک
        }

        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            // مدیر مدرسه: کلِ بانکِ مدرسه‌ی خودش
            return SmartQuestionBank::withoutGlobalScopes()->where('school_id', $user->school_id);
        }

        // معلم
        $grades = self::teacherGrades($user);
        $seesShared = self::schoolCanSeeShared($user->school_id);

        return SmartQuestionBank::withoutGlobalScopes()->where(function (Builder $q) use ($user, $grades, $seesShared) {
            // سؤال‌های خودِ معلم (هر پایه)
            $q->where('teacher_id', $user->id);
            // سؤال‌های مدرسه‌ی خودش در پایه‌های تدریسی
            $q->orWhere(function (Builder $w) use ($user, $grades) {
                $w->where('school_id', $user->school_id)
                    ->whereIn('scope', ['school', 'shared', 'global'])
                    ->where(fn (Builder $g) => $g->whereNull('grade')->when($grades, fn ($x) => $x->orWhereIn('grade', $grades)));
            });
            // بانکِ اشتراکیِ بین‌مدرسه‌ای (اگر ادمین اجازه داده)
            if ($seesShared) {
                $q->orWhere(function (Builder $w) use ($grades) {
                    $w->where('scope', 'global')
                        ->where(fn (Builder $g) => $g->whereNull('grade')->when($grades, fn ($x) => $x->orWhereIn('grade', $grades)));
                });
            }
        });
    }

    /** آیا این کاربر می‌تواند این سؤال را ویرایش/حذف کند؟ */
    public static function canEdit(User $user, SmartQuestionBank $q): bool
    {
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return $q->school_id === $user->school_id;
        }
        return $q->teacher_id === $user->id; // معلم فقط سؤال‌های خودش
    }

    /** ثبتِ خودکارِ یک سؤال در بانک هنگام ساختِ آزمون/بازی (بدون تکرار). */
    public static function autosave(User $teacher, array $q, array $meta = []): void
    {
        $prompt = trim($q['prompt'] ?? '');
        if ($prompt === '' || ! in_array(($q['type'] ?? 'mc'), ['mc', 'tf', 'desc', 'blank'], true)) {
            return;
        }
        $exists = SmartQuestionBank::where('teacher_id', $teacher->id)
            ->where('prompt', $prompt)->exists();
        if ($exists) {
            return;
        }
        SmartQuestionBank::create([
            'school_id' => $teacher->school_id, 'teacher_id' => $teacher->id,
            'scope' => 'school', 'type' => $q['type'] ?? 'mc', 'prompt' => $prompt,
            'choices' => $q['choices'] ?? [], 'answer' => $q['answer'] ?? null,
            'explanation' => $q['explanation'] ?? null,
            'subject' => $meta['subject'] ?? null, 'grade' => $meta['grade'] ?? null,
            'book' => $meta['book'] ?? null, 'chapter' => $meta['chapter'] ?? null,
            'topic' => $q['topic'] ?? ($meta['topic'] ?? null),
            'difficulty' => $q['difficulty'] ?? 'medium',
            'source' => $q['source'] ?? ($meta['source'] ?? 'manual'),
        ]);
    }
}
