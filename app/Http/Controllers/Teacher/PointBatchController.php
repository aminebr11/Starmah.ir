<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ActivityAward;
use App\Models\Announcement;
use App\Models\ClassActivity;
use App\Models\Classroom;
use App\Models\PointBatch;
use App\Models\TeamPoint;
use App\Models\Theme;
use App\Models\User;
use App\Models\XpEntry;
use App\Services\GamificationService;
use App\Support\Jalali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * مرکزِ امتیاز — امتیازدهی به یک نفر، چند نفر، یک یا چند تیم یا کلِ کلاس در یک «نوبت».
 *
 * امتیازِ تیمی به‌طورِ پیش‌فرض به «هر عضو» داده می‌شود (XPِ شخصیِ هر عضو بالا می‌رود
 * و چون امتیازِ تیم جمعِ امتیازِ اعضاست، تیم هم بالا می‌رود). حالتِ «فقط تیم» امتیازِ
 * تشویقیِ خودِ تیم است و به اعضا نمی‌رسد. هر نوبت بعداً یک‌جا ویرایش/حذف می‌شود.
 */
class PointBatchController extends Controller
{
    /** کلاس‌های معلم با دانش‌آموزان (برای تشخیصِ مجاز بودن و تیم‌ها). */
    private function classrooms(User $teacher): Collection
    {
        return Classroom::where('teacher_id', $teacher->id)->with('students:id,name,theme_id,school_id')->get();
    }

    /** اگر پایگاه‌داده آماده نباشد، به‌جای ۵۰۰ پیامِ روشن برمی‌گردد. */
    private function guard(): void
    {
        if (! PointBatch::ready()) {
            throw ValidationException::withMessages(['targets' => 'پایگاه‌داده‌ی سامانه برای «مرکزِ امتیاز» هنوز به‌روز نشده است. ادمینِ کل ← «🩺 سلامتِ سیستم» ← «اجرای مایگریشن‌ها» را بزند.']);
        }
    }

    public function store(Request $request, GamificationService $game): RedirectResponse
    {
        $this->guard();
        $teacher = $request->user();
        $data = $request->validate([
            'student_ids'   => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'teams'         => ['nullable', 'array'],
            'teams.*'       => ['string', 'regex:/^\d+:\d+$/'],
            'whole_class'   => ['nullable', 'integer'],
            'team_mode'     => ['nullable', 'in:each,team'],
            'amount'        => ['required', 'integer', 'min:-1000', 'max:1000', 'not_in:0'],
            'reason'        => ['required', 'string', 'max:160'],
            'category'      => ['nullable', 'string', 'max:30'],
            'activity_id'   => ['nullable', 'integer'],
            'repeat'        => ['nullable', 'boolean'],
            'notify'        => ['nullable', 'boolean'],
        ], [
            'amount.not_in' => 'مقدارِ امتیاز نمی‌تواند صفر باشد.',
            'amount.required' => 'مقدارِ امتیاز را بنویسید.',
            'reason.required' => 'علتِ امتیاز را بنویسید یا یکی از علت‌های آماده را بزنید.',
        ]);

        $rooms = $this->classrooms($teacher);
        $mode = $data['team_mode'] ?? 'each';
        $allowed = $rooms->flatMap(fn ($c) => $c->students)->keyBy('id');

        // ── گیرنده‌ها ──
        $studentIds = collect($data['student_ids'] ?? [])->map(fn ($i) => (int) $i)->filter(fn ($i) => $allowed->has($i));
        $labels = [];

        if (! empty($data['whole_class'])) {
            $room = $rooms->firstWhere('id', (int) $data['whole_class']);
            if ($room) {
                $studentIds = $studentIds->merge($room->students->pluck('id'));
                $labels[] = 'کلِ کلاسِ ' . $room->name;
            }
        }

        $teamRows = collect();   // [classroom_id, theme_id, member ids]
        foreach ($data['teams'] ?? [] as $key) {
            [$cid, $tid] = array_map('intval', explode(':', $key));
            $room = $rooms->firstWhere('id', $cid);
            if (! $room) {
                continue;
            }
            $members = $room->students->where('theme_id', $tid)->pluck('id');
            if ($members->isEmpty()) {
                continue;
            }
            $teamRows->push(['cid' => $cid, 'tid' => $tid, 'members' => $members]);
            if ($mode === 'each') {
                $studentIds = $studentIds->merge($members);
            }
        }
        $studentIds = $studentIds->unique()->values();

        if ($studentIds->isEmpty() && ($mode !== 'team' || $teamRows->isEmpty())) {
            throw ValidationException::withMessages(['targets' => 'دستِ‌کم یک دانش‌آموز، یک تیم یا کلِ کلاس را انتخاب کنید.']);
        }

        // ── فعالیتِ آماده (اختیاری): جلوگیری از امتیازِ تکراری ──
        $activity = null;
        $skipped = collect();
        if (! empty($data['activity_id'])) {
            $activity = ClassActivity::where('teacher_id', $teacher->id)->find($data['activity_id']);
            if ($activity && empty($data['repeat'])) {
                $already = ActivityAward::where('class_activity_id', $activity->id)->whereIn('student_id', $studentIds)->pluck('student_id');
                $skipped = $already;
                $studentIds = $studentIds->diff($already)->values();
                if ($studentIds->isEmpty() && ($mode !== 'team' || $teamRows->isEmpty())) {
                    throw ValidationException::withMessages(['targets' => 'همه‌ی انتخاب‌شده‌ها امتیازِ این فعالیت را قبلاً گرفته‌اند. برای دادنِ دوباره، «اجازه‌ی تکرار» را روشن کنید.']);
                }
            }
        }

        $teamNames = Theme::whereIn('id', $teamRows->pluck('tid'))->pluck('name', 'id');
        foreach ($teamRows as $t) {
            $labels[] = '🏆 ' . ($teamNames[$t['tid']] ?? 'تیم');
        }
        $individuals = collect($data['student_ids'] ?? [])->filter(fn ($i) => $allowed->has((int) $i));
        if ($individuals->isNotEmpty()) {
            $labels[] = $individuals->count() === 1
                ? $allowed[(int) $individuals->first()]->name
                : Jalali::fa((string) $individuals->count()) . ' دانش‌آموز';
        }

        $amount = (int) $data['amount'];
        $reason = trim($data['reason']);

        $batch = DB::transaction(function () use ($teacher, $rooms, $data, $mode, $activity, $studentIds, $teamRows, $labels, $amount, $reason, $game, $allowed) {
            $batch = PointBatch::create([
                'school_id' => $teacher->school_id,
                'teacher_id' => $teacher->id,
                'classroom_id' => (int) ($data['whole_class'] ?? 0) ?: ($teamRows->first()['cid'] ?? $rooms->first()?->id),
                'class_activity_id' => $activity?->id,
                'amount' => $amount,
                'reason' => $reason,
                'category' => $data['category'] ?? null,
                'team_mode' => $mode,
                'target_label' => mb_substr(implode('، ', array_unique($labels)), 0, 255),
                'students_count' => $studentIds->count(),
                'teams_count' => $teamRows->count(),
            ]);

            foreach ($studentIds as $sid) {
                $game->award($allowed[$sid], $amount, $reason, $teacher, PointBatch::class, $batch->id);
                if ($activity && ! ActivityAward::where('class_activity_id', $activity->id)->where('student_id', $sid)->exists()) {
                    ActivityAward::create(['class_activity_id' => $activity->id, 'student_id' => $sid, 'points' => $amount, 'awarded_by' => $teacher->id, 'batch_id' => $batch->id]);
                }
            }

            if ($mode === 'team') {
                foreach ($teamRows as $t) {
                    TeamPoint::create([
                        'school_id' => $teacher->school_id, 'classroom_id' => $t['cid'], 'theme_id' => $t['tid'],
                        'amount' => $amount, 'reason' => $reason, 'awarded_by' => $teacher->id, 'batch_id' => $batch->id,
                    ]);
                }
            }

            return $batch;
        });

        // اعلان: یک اعلانِ شخصی برای همه‌ی گیرنده‌ها (و اعضای تیم در حالتِ «فقط تیم»)
        if ($data['notify'] ?? true) {
            rescue(fn () => $this->announce($batch, $teacher, $mode === 'team'
                ? $studentIds->merge($teamRows->flatMap(fn ($t) => $t['members']))->unique()
                : $studentIds), null, true);
        }

        $who = $batch->target_label ?: 'گیرنده‌ها';
        $msg = ($amount > 0 ? "⭐ +{$amount}" : "➖ {$amount}") . " امتیاز برای {$who} ثبت شد";
        if ($mode === 'each' && $studentIds->count() > 1) {
            $msg .= ' (' . Jalali::fa((string) $studentIds->count()) . ' نفر)';
        }
        if ($skipped->isNotEmpty()) {
            $msg .= ' — ' . Jalali::fa((string) $skipped->count()) . ' نفر که قبلاً امتیازِ این فعالیت را گرفته بودند کنار گذاشته شدند';
        }

        return back()->with('flash', $msg)->with('lastBatch', $batch->id);
    }

    /** ویرایشِ یک نوبت: مقدار، علت، دسته و فهرستِ دانش‌آموزانِ گیرنده. */
    public function update(Request $request, PointBatch $batch, GamificationService $game): RedirectResponse
    {
        $teacher = $request->user();
        abort_unless($batch->teacher_id === $teacher->id, 403);
        $data = $request->validate([
            'amount'        => ['required', 'integer', 'min:-1000', 'max:1000', 'not_in:0'],
            'reason'        => ['required', 'string', 'max:160'],
            'category'      => ['nullable', 'string', 'max:30'],
            'student_ids'   => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
        ], ['amount.not_in' => 'مقدارِ امتیاز نمی‌تواند صفر باشد.']);

        $allowed = $this->classrooms($teacher)->flatMap(fn ($c) => $c->students)->keyBy('id');
        $amount = (int) $data['amount'];
        $reason = trim($data['reason']);

        DB::transaction(function () use ($batch, $data, $allowed, $amount, $reason, $game, $teacher) {
            XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->update(['amount' => $amount, 'reason' => $reason]);
            TeamPoint::where('batch_id', $batch->id)->update(['amount' => $amount, 'reason' => $reason]);
            ActivityAward::where('batch_id', $batch->id)->update(['points' => $amount]);

            if (array_key_exists('student_ids', $data) && $data['student_ids'] !== null) {
                $want = collect($data['student_ids'])->map(fn ($i) => (int) $i)->filter(fn ($i) => $allowed->has($i))->unique();
                $have = XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->pluck('student_id');
                $remove = $have->diff($want);
                if ($remove->isNotEmpty()) {
                    XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->whereIn('student_id', $remove)->delete();
                    ActivityAward::where('batch_id', $batch->id)->whereIn('student_id', $remove)->delete();
                }
                foreach ($want->diff($have) as $sid) {
                    $game->award($allowed[$sid], $amount, $reason, $teacher, PointBatch::class, $batch->id);
                }
            }

            $batch->update([
                'amount' => $amount, 'reason' => $reason, 'category' => $data['category'] ?? $batch->category,
                'students_count' => XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->count(),
            ]);
        });

        return back()->with('flash', 'نوبتِ امتیاز ویرایش شد ✅');
    }

    /** حذف/برگرداندنِ کاملِ یک نوبت (امتیازِ همه‌ی گیرنده‌ها و تیم‌ها پس گرفته می‌شود). */
    public function destroy(Request $request, PointBatch $batch): RedirectResponse
    {
        abort_unless($batch->teacher_id === $request->user()->id, 403);
        DB::transaction(function () use ($batch) {
            XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->delete();
            TeamPoint::where('batch_id', $batch->id)->delete();
            ActivityAward::where('batch_id', $batch->id)->delete();
            $batch->delete();
        });

        return back()->with('flash', '↩️ نوبتِ امتیاز برگردانده شد و امتیازها پس گرفته شد');
    }

    /** برداشتنِ یک دانش‌آموز از یک نوبت. */
    public function removeStudent(Request $request, PointBatch $batch, User $student): RedirectResponse
    {
        abort_unless($batch->teacher_id === $request->user()->id, 403);
        XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->where('student_id', $student->id)->delete();
        ActivityAward::where('batch_id', $batch->id)->where('student_id', $student->id)->delete();
        $batch->update(['students_count' => XpEntry::where('source_type', PointBatch::class)->where('source_id', $batch->id)->count()]);

        return back()->with('flash', "امتیازِ این نوبت از «{$student->name}» پس گرفته شد");
    }

    /** ویرایشِ یک ردیفِ XP (مقدار/علت) از دفترِ یک دانش‌آموز. */
    public function updateEntry(Request $request, XpEntry $xpEntry): RedirectResponse
    {
        $teacher = $request->user();
        $allowed = $this->classrooms($teacher)->flatMap(fn ($c) => $c->students)->pluck('id');
        abort_unless($allowed->contains($xpEntry->student_id), 403);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:-1000', 'max:1000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:160'],
        ], ['amount.not_in' => 'مقدارِ امتیاز نمی‌تواند صفر باشد.']);
        $xpEntry->update($data);

        return back()->with('flash', 'ردیفِ امتیاز ویرایش شد ✅');
    }

    /** ویرایشِ یک امتیازِ تشویقیِ تیم. */
    public function updateTeamPoint(Request $request, TeamPoint $teamPoint): RedirectResponse
    {
        $rooms = $this->classrooms($request->user())->pluck('id');
        abort_unless($rooms->contains($teamPoint->classroom_id), 403);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:-1000', 'max:1000', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);
        $teamPoint->update($data);

        return back()->with('flash', 'امتیازِ تیم ویرایش شد ✅');
    }

    private function announce(PointBatch $batch, User $teacher, Collection $ids): void
    {
        if ($ids->isEmpty() || ! $teacher->school_id) {
            return;
        }
        $plus = $batch->amount > 0;
        $teamOnly = $batch->team_mode === 'team' && $batch->teams_count > 0;
        $ann = Announcement::create([
            'school_id' => $teacher->school_id,
            'sender_id' => $teacher->id,
            'audience'  => 'personal',
            'title'     => ($plus ? '⭐ +' : '➖ ') . abs($batch->amount) . ' امتیاز — ' . $batch->reason,
            'body'      => $teamOnly
                ? ($plus ? "تیمِ شما {$batch->amount} امتیاز گرفت 🏆" : "از امتیازِ تیمِ شما {$batch->amount} کم شد") . " ({$batch->reason})"
                : ($plus ? "امتیاز جدید گرفتی: +{$batch->amount} XP" : "امتیاز کم شد: {$batch->amount} XP") . " ({$batch->reason})",
            'link'      => $teamOnly ? '/my-team' : '/report?tab=overview',
        ]);
        $ann->recipients()->sync($ids->values()->all());
    }
}
