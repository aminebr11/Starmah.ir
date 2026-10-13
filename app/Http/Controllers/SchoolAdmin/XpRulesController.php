<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Support\DbSchema;
use App\Support\XpRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * مدیرِ مدرسه: «⚡ مدیریتِ امتیازها» — کدام منبع XP بدهد و چقدر (فقط برای مدرسه‌ی خودش).
 */
class XpRulesController extends Controller
{
    /** منبع‌هایی که از روی source_type بی‌ابهام شناخته می‌شوند. */
    private const SOURCE_MAP = [
        \App\Models\Mission::class => 'mission',
        \App\Models\ReviewCompletion::class => 'daily_review',
        \App\Models\MissionCombo::class => 'treasure_chest',
        \App\Models\Remediation::class => 'remediation',
        \App\Models\Worksheet::class => 'worksheet_download',
        \App\Models\WorksheetSubmission::class => 'worksheet_submit',
        'worksheet_grade' => 'worksheet_grade',
        \App\Models\AudioSubmission::class => 'audio_submit',
        \App\Models\EduGameAttempt::class => 'edu_game',
        'live_contest' => 'live_contest',
        \App\Models\SmartExamReward::class => 'smart_exam',
        \App\Models\AssignmentSubmission::class => 'exam_descriptive',
        'content' => 'content',
        \App\Models\PointBatch::class => 'point_batch',
        'App\Models\OnlinePoll' => 'online_poll',
    ];

    /** منبع‌هایی که با متنِ «علت» از هم جدا می‌شوند. */
    private const BY_REASON = [null, 'growth', \App\Models\ActivityAward::class, \App\Models\AttendanceRecord::class, 'App\Models\OnlineRoom'];

    private function school(Request $request): School
    {
        $school = School::find($request->user()->school_id);
        abort_unless($school, 403);

        return $school;
    }

    public function index(Request $request): Response
    {
        $school = $this->school($request);
        $values = XpRules::resolve(XpRules::overridesFor($school->id));
        $stats = rescue(fn () => $this->stats($school->id), [], false);

        $groups = [];
        foreach (XpRules::GROUPS as $gk => $g) {
            $groups[$gk] = ['key' => $gk, 'label' => $g['label'], 'icon' => $g['icon'], 'rules' => []];
        }
        foreach (XpRules::RULES as $key => $def) {
            $groups[$def['group']]['rules'][] = [
                'key' => $key,
                'label' => $def['label'],
                'desc' => $def['desc'],
                'icon' => $def['icon'],
                'kind' => $def['kind'],
                'sign' => $def['sign'] ?? 1,
                'ex' => $def['ex'] ?? null,
                'default' => XpRules::defaults($key),
                'value' => $values[$key],
                'stats' => $stats[$key] ?? null,
            ];
        }

        return Inertia::render('SchoolAdmin/XpRules', [
            'schoolName' => $school->name,
            'groups' => array_values(array_filter($groups, fn ($g) => $g['rules'])),
            'limits' => ['fixed' => XpRules::MAX_FIXED, 'pct' => XpRules::MAX_PCT, 'cap' => XpRules::MAX_CAP],
            'ready' => DbSchema::hasColumn('schools', 'xp_rules'),
            'hasStats' => ! empty($stats),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $this->school($request);
        $this->ensureReady();

        $data = $request->validate([
            'rules'          => ['required', 'array'],
            'rules.*'        => ['array'],
            'rules.*.on'     => ['required', 'boolean'],
            'rules.*.amount' => ['nullable', 'integer', 'min:0', 'max:' . XpRules::MAX_FIXED],
            'rules.*.pct'    => ['nullable', 'integer', 'min:0', 'max:' . XpRules::MAX_PCT],
            'rules.*.cap'    => ['nullable', 'integer', 'min:1', 'max:' . XpRules::MAX_CAP],
        ], [
            'rules.*.amount.max' => 'مقدارِ امتیاز حداکثر ' . XpRules::MAX_FIXED . ' است.',
            'rules.*.amount.min' => 'مقدارِ امتیاز نمی‌تواند منفی باشد.',
            'rules.*.pct.max'    => 'ضریب حداکثر ' . XpRules::MAX_PCT . '٪ است.',
            'rules.*.pct.min'    => 'ضریب نمی‌تواند منفی باشد.',
            'rules.*.cap.min'    => 'سقف باید دستِ‌کم ۱ باشد (یا خالی بماند).',
            'rules.*.cap.max'    => 'سقف حداکثر ' . XpRules::MAX_CAP . ' است.',
        ]);

        $unknown = array_values(array_filter(array_keys($data['rules']), fn ($k) => ! XpRules::known((string) $k)));
        if ($unknown) {
            throw ValidationException::withMessages(['rules' => 'منبعِ امتیازِ ناشناخته: ' . implode('، ', $unknown)]);
        }

        // ورودی روی تنظیمِ فعلی می‌نشیند (کلیدهای نفرستاده دست نمی‌خورند)، بعد فقط تفاوت‌ها ذخیره می‌شود
        $merged = XpRules::resolve(XpRules::overridesFor($school->id));
        foreach ($data['rules'] as $key => $r) {
            $merged[$key] = array_merge($merged[$key], array_filter([
                'on' => (bool) $r['on'],
                'amount' => $r['amount'] ?? null,
                'pct' => $r['pct'] ?? null,
            ], fn ($v) => $v !== null));
            if (XpRules::RULES[$key]['kind'] === 'manual') {
                $merged[$key]['cap'] = $r['cap'] ?? null;
            }
        }
        $diff = XpRules::diff($merged);
        $school->forceFill(['xp_rules' => $diff ?: null])->save();
        XpRules::forget($school->id);

        return back()->with('flash', '⚡ قاعده‌های امتیاز ذخیره شد' . ($diff ? ' — ' . \App\Support\Jalali::fa((string) count($diff)) . ' مورد با پیش‌فرض فرق دارد' : ' (همه پیش‌فرض)'));
    }

    public function reset(Request $request): RedirectResponse
    {
        $school = $this->school($request);
        $this->ensureReady();
        $school->forceFill(['xp_rules' => null])->save();
        XpRules::forget($school->id);

        return back()->with('flash', '↩️ همه‌ی امتیازها به حالتِ پیش‌فرض برگشت');
    }

    private function ensureReady(): void
    {
        if (! DbSchema::hasColumn('schools', 'xp_rules')) {
            throw ValidationException::withMessages(['rules' => 'پایگاه‌داده هنوز به‌روز نشده است؛ چند لحظه بعد دوباره امتحان کنید.']);
        }
    }

    /**
     * آمارِ ۳۰ روزِ اخیرِ همین مدرسه: تعداد و جمعِ XP هر منبع.
     *
     * @return array<string,array{count:int,total:int}>
     */
    private function stats(int $schoolId): array
    {
        $since = now()->subDays(30);
        $base = fn () => DB::table('xp_ledger')
            ->join('users', 'users.id', '=', 'xp_ledger.student_id')
            ->where('users.school_id', $schoolId)
            ->where('xp_ledger.created_at', '>=', $since);

        $out = [];
        $add = function (?string $key, $count, $total) use (&$out) {
            if (! $key) {
                return;
            }
            $out[$key] ??= ['count' => 0, 'total' => 0];
            $out[$key]['count'] += (int) $count;
            $out[$key]['total'] += (int) $total;
        };

        // ۱) منبع‌های بی‌ابهام
        $rows = $base()->whereIn('xp_ledger.source_type', array_keys(self::SOURCE_MAP))
            ->selectRaw('xp_ledger.source_type as st, count(*) as c, sum(xp_ledger.amount) as t')
            ->groupBy('xp_ledger.source_type')->get();
        foreach ($rows as $r) {
            $add(self::SOURCE_MAP[$r->st] ?? null, $r->c, $r->t);
        }

        // ۲) منبع‌هایی که با «علت» جدا می‌شوند
        $types = array_values(array_filter(self::BY_REASON));
        $rows = $base()->where(fn ($q) => $q->whereNull('xp_ledger.source_type')->orWhereIn('xp_ledger.source_type', $types))
            ->selectRaw('xp_ledger.source_type as st, xp_ledger.reason as rs, (case when xp_ledger.awarded_by is null then 0 else 1 end) as bt, count(*) as c, sum(xp_ledger.amount) as t')
            ->groupBy('xp_ledger.source_type', 'xp_ledger.reason', DB::raw('(case when xp_ledger.awarded_by is null then 0 else 1 end)'))->get();
        foreach ($rows as $r) {
            $add($this->classify($r->st, (string) $r->rs, (bool) $r->bt), $r->c, $r->t);
        }

        // ۳) نمره‌های دفترِ نمره بر اساسِ نوعِ ستون
        if (DbSchema::hasColumn('grade_columns', 'score_type')) {
            $rows = $base()->where('xp_ledger.source_type', \App\Models\Grade::class)
                ->join('grades', 'grades.id', '=', 'xp_ledger.source_id')
                ->join('grade_columns', 'grade_columns.id', '=', 'grades.grade_column_id')
                ->selectRaw("(case when grade_columns.score_type is null or grade_columns.score_type = '' then grade_columns.type else grade_columns.score_type end) as gt, count(*) as c, sum(xp_ledger.amount) as t")
                ->groupBy(DB::raw("(case when grade_columns.score_type is null or grade_columns.score_type = '' then grade_columns.type else grade_columns.score_type end)"))->get();
            foreach ($rows as $r) {
                $add(match ($r->gt) {
                    'numeric' => 'grade_numeric',
                    'homework' => 'grade_homework',
                    default => 'grade_descriptive',
                }, $r->c, $r->t);
            }
        }

        return $out;
    }

    private function classify(?string $type, string $reason, bool $byTeacher): ?string
    {
        $starts = fn (string $p) => str_starts_with($reason, $p);

        return match ($type) {
            'growth' => match (true) {
                str_contains($reason, 'رکوردِ شخصی') => 'growth_personal_best',
                str_contains($reason, 'خودت درست کردی') => 'growth_retry_fix',
                str_contains($reason, 'سطح بالا رفت') => 'growth_level_up',
                default => null,
            },
            \App\Models\ActivityAward::class => $starts('🎮') ? 'class_game' : 'class_activity',
            \App\Models\AttendanceRecord::class => match (true) {
                str_contains($reason, 'تأخیر') => 'attendance_late',
                str_contains($reason, 'غیبت') => 'attendance_absent',
                str_contains($reason, 'حضور') => 'attendance_present',
                default => null,
            },
            'App\Models\OnlineRoom' => $starts('🎥') ? 'online_attend' : ($starts('⭐') ? 'online_star' : null),
            null => match (true) {
                $starts('💻') => 'online_exam',
                $starts('⭐ تشویق') => 'discipline_positive',
                $starts('⚠️ انضباط') => 'discipline_negative',
                $starts('🎁 هدیه‌ی تولد') => 'birthday_gift',
                $byTeacher => 'teacher_manual',
                default => null,
            },
            default => null,
        };
    }
}
