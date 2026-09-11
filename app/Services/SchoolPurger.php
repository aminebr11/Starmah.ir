<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * حذفِ کاملِ یک مدرسه و همه‌ی داده‌هایش.
 *
 * چرا این کلاس لازم شد: پیش از این هیچ راهی برای حذفِ مدرسه وجود نداشت
 * (نه مسیر، نه متد، نه دکمه)، و چون داده‌های مدرسه در ۲۸ جدولِ مستقیم و
 * ۲۱ جدولِ وابسته به کاربر پخش است، حذفِ ساده‌ی ردیفِ schools رکوردهای
 * یتیمِ فراوانی به‌جا می‌گذاشت.
 *
 * ترتیبِ حذف از برگ به ریشه است تا هیچ کلیدِ خارجی‌ای نشکند.
 */
class SchoolPurger
{
    /** جدول‌هایی که مستقیم ستونِ school_id دارند. */
    private const SCHOOL_TABLES = [
        'payment_transactions', 'parent_notes', 'team_points', 'missions',
        'bank_shares', 'worksheets', 'smart_question_bank', 'smart_exam_ai_requests',
        'smart_exam_media', 'smart_exams', 'edu_games', 'exam_questions',
        'audit_logs', 'attendance_records', 'announcements', 'class_contents',
        'grade_columns', 'discipline_records', 'discipline_topics', 'schedule_entries',
        'class_activities', 'school_requests', 'messages', 'assignments',
        'seasons', 'classrooms', 'subjects',
        // users آخر از همه، چون جدول‌های دیگر به آن ارجاع دارند
    ];

    /** جدول‌هایی که با شناسه‌ی کاربر پاک می‌شوند (ستون => نامِ جدول‌ها). */
    private const USER_TABLES = [
        'student_id' => [
            'classroom_student', 'assignment_submissions', 'activity_results',
            'xp_ledger', 'student_skill_mastery', 'student_badges', 'activity_awards',
            'grades', 'edu_game_targets', 'edu_game_attempts', 'smart_exam_targets',
            'smart_exam_attempts', 'smart_exam_recommendations', 'smart_exam_rewards',
            'content_views', 'worksheet_submissions', 'mission_completions',
        ],
        'user_id' => ['sessions', 'announcement_recipients', 'announcement_dismissals'],
        'parent_id' => ['parent_student'],
    ];

    /** ستون‌هایی که مسیرِ فایلِ آپلودی در آن‌ها ذخیره می‌شود. */
    private const FILE_COLUMNS = [
        'users'          => 'avatar',
        'class_contents' => 'file_path',
        'worksheets'     => 'file_path',
        'worksheet_submissions' => 'file_path',
        'smart_exam_media' => 'path',
    ];

    /**
     * شمارشِ آنچه حذف خواهد شد — برای نمایش به مدیر پیش از تأیید.
     *
     * @return array<string,int>
     */
    public function preview(School $school): array
    {
        $userIds = $this->userIds($school);

        return array_filter([
            'دانش‌آموز' => DB::table('users')->where('school_id', $school->id)
                ->whereIn('id', fn ($q) => $q->select('model_id')->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'student'))->count(),
            'کاربر (همه‌ی نقش‌ها)' => count($userIds),
            'کلاس'        => $this->count('classrooms', $school->id),
            'تکلیف'       => $this->count('assignments', $school->id),
            'آزمون هوشمند' => $this->count('smart_exams', $school->id),
            'بازی'        => $this->count('edu_games', $school->id),
            'کاربرگ'      => $this->count('worksheets', $school->id),
            'محتوای کلاسی' => $this->count('class_contents', $school->id),
            'رکورد حضور و غیاب' => $this->count('attendance_records', $school->id),
            'رکورد انضباطی' => $this->count('discipline_records', $school->id),
            'اطلاعیه'     => $this->count('announcements', $school->id),
            'پیام'        => $this->count('messages', $school->id),
            'تراکنش مالی' => $this->count('payment_transactions', $school->id),
        ], fn ($n) => $n > 0);
    }

    /**
     * حذفِ کاملِ مدرسه. همه‌چیز در یک تراکنش انجام می‌شود، پس اگر
     * در میانه خطایی رخ دهد هیچ‌چیز نیمه‌کاره باقی نمی‌ماند.
     *
     * @return array<string,int> شمارشِ ردیف‌های حذف‌شده به تفکیکِ جدول
     */
    public function purge(School $school): array
    {
        $userIds = $this->userIds($school);
        $files   = $this->collectFiles($school, $userIds);
        $deleted = [];

        DB::transaction(function () use ($school, $userIds, &$deleted) {
            // ۱) جدول‌های وابسته به کاربر
            foreach (self::USER_TABLES as $column => $tables) {
                foreach ($tables as $table) {
                    if (! $this->hasTable($table) || empty($userIds)) {
                        continue;
                    }
                    $n = DB::table($table)->whereIn($column, $userIds)->delete();
                    if ($n) {
                        $deleted[$table] = ($deleted[$table] ?? 0) + $n;
                    }
                }
            }

            // parent_student دو ستونِ کاربری دارد
            if ($this->hasTable('parent_student') && $userIds) {
                $n = DB::table('parent_student')->whereIn('student_id', $userIds)->delete();
                if ($n) {
                    $deleted['parent_student'] = ($deleted['parent_student'] ?? 0) + $n;
                }
            }

            // ۲) پاسخ‌های آزمونِ هوشمند (از راهِ تلاش‌ها که بالاتر حذف شدند)
            if ($this->hasTable('smart_exam_answers')) {
                $n = DB::table('smart_exam_answers')
                    ->whereNotIn('attempt_id', fn ($q) => $q->select('id')->from('smart_exam_attempts'))
                    ->delete();
                if ($n) {
                    $deleted['smart_exam_answers'] = $n;
                }
            }

            // ۳) سؤال‌های آزمونِ هوشمندِ همین مدرسه
            if ($this->hasTable('smart_exam_questions')) {
                $n = DB::table('smart_exam_questions')
                    ->whereIn('smart_exam_id', fn ($q) => $q->select('id')->from('smart_exams')->where('school_id', $school->id))
                    ->delete();
                if ($n) {
                    $deleted['smart_exam_questions'] = $n;
                }
            }

            // ۴) نقش‌های Spatie برای کاربرانِ این مدرسه
            if ($userIds && $this->hasTable('model_has_roles')) {
                $n = DB::table('model_has_roles')
                    ->where('model_type', \App\Models\User::class)
                    ->whereIn('model_id', $userIds)->delete();
                if ($n) {
                    $deleted['model_has_roles'] = $n;
                }
            }
            if ($userIds && $this->hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', \App\Models\User::class)
                    ->whereIn('tokenable_id', $userIds)->delete();
            }

            // ۵) جدول‌های مستقیمِ مدرسه
            foreach (self::SCHOOL_TABLES as $table) {
                if (! $this->hasTable($table)) {
                    continue;
                }
                $n = DB::table($table)->where('school_id', $school->id)->delete();
                if ($n) {
                    $deleted[$table] = $n;
                }
            }

            // ۶) کاربرانِ مدرسه
            if ($userIds) {
                $deleted['users'] = DB::table('users')->whereIn('id', $userIds)->delete();
            }

            // ۷) خودِ مدرسه
            DB::table('schools')->where('id', $school->id)->delete();
            $deleted['schools'] = 1;
        });

        // ۸) فایل‌ها — بیرون از تراکنش، چون حذفِ فایل برگشت‌پذیر نیست
        $deleted['_files'] = $this->deleteFiles($files);

        return $deleted;
    }

    // ───────────────────────── کمکی‌ها ─────────────────────────

    /** @return int[] */
    private function userIds(School $school): array
    {
        return DB::table('users')->where('school_id', $school->id)->pluck('id')->all();
    }

    private function count(string $table, int $schoolId): int
    {
        return $this->hasTable($table)
            ? DB::table($table)->where('school_id', $schoolId)->count()
            : 0;
    }

    private function hasTable(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= \Illuminate\Support\Facades\Schema::hasTable($table);
    }

    /**
     * مسیرِ همه‌ی فایل‌های آپلودیِ این مدرسه.
     *
     * @return string[]
     */
    private function collectFiles(School $school, array $userIds): array
    {
        $paths = [];

        if ($school->logo) {
            $paths[] = $school->logo;
        }

        foreach (self::FILE_COLUMNS as $table => $column) {
            if (! $this->hasTable($table)) {
                continue;
            }

            $q = DB::table($table)->whereNotNull($column);
            if ($table === 'users') {
                $q->whereIn('id', $userIds ?: [0]);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'school_id')) {
                $q->where('school_id', $school->id);
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'student_id')) {
                $q->whereIn('student_id', $userIds ?: [0]);
            } else {
                continue;
            }

            $paths = array_merge($paths, $q->pluck($column)->all());
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /** @param string[] $paths */
    private function deleteFiles(array $paths): int
    {
        $disk = Storage::disk('public');
        $n = 0;

        foreach ($paths as $p) {
            if ($disk->exists($p) && $disk->delete($p)) {
                $n++;
            }
        }

        return $n;
    }
}
