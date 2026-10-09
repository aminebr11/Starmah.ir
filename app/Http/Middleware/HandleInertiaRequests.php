<?php

namespace App\Http\Middleware;

use App\Services\ThemeEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $theme = app(ThemeEngine::class)->for($user);

        return [
            ...parent::share($request),
            'ui' => \App\Support\Ui::current($request),
            'toast' => fn () => $request->session()->get('toast'),
            'auth' => [
                'user'  => $user,
                'roles' => $user ? $user->getRoleNames() : [],
            ],
            // عکسِ کاربر و برندِ مدرسه — در همه‌ی داشبوردها (سایدبار/تاپ‌بار) استفاده می‌شود
            // «بخوان برایم» و «متنِ درشت» — فقط برای دانش‌آموز
            'a11y' => fn () => $user && $user->hasRole(\App\Support\Roles::STUDENT) ? \App\Support\A11y::for($user) : null,
            // صدای فارسیِ سرور برای دستگاه‌هایی که خودشان صدای فارسی ندارند
            'tts' => fn () => $user ? \App\Services\SpeechService::available() : false,
            'avatarUrl' => $user && $user->avatar ? Storage::url($user->avatar) : null,
            'school' => fn () => $user && $user->school ? [
                'name'     => $user->school->name,
                'logo_url' => $user->school->logo ? Storage::url($user->school->logo) : null,
            ] : null,
            // تم فعال در همه‌ی صفحات در دسترس است تا فرانت ظاهر را بسازد
            'theme' => app(ThemeEngine::class)->presentation($theme),
            'flash' => ['flash' => fn () => $request->session()->get('flash')],
            // فید یکپارچه‌ی اعلان‌ها (اطلاعیه + پیام + موارد انضباطی) برای زنگوله
            'notifications' => fn () => $user ? \App\Support\Notifications::feed($user) : [],
            // شمار اعلان‌های خوانده‌نشده (برای نشان روی زنگوله و منوی اعلان‌ها)
            'unreadNotices' => fn () => $user ? \App\Support\Notifications::unreadCount($user) : 0,
            // آزمایشگاه هوشمند آزمون — فقط برای گیتِ منو (خاموش = منو نمایش داده نمی‌شود)
            'smartLab' => fn () => $user ? \App\Support\SmartLab::enabledFor($user) : false,
            // آیا حبابِ دستیار برای این کاربر نمایش داده شود؟ (تصمیمِ مدیرِ مدرسه)
            'assistantOn' => fn () => \App\Support\AssistantAccess::visible($user),
            // پیامِ خوانده‌نشده‌ی «بخشِ والدین» (نشان روی منوی دانش‌آموز)
            'familyNew' => fn () => ($user && $user->isStudent() && \App\Support\DbSchema::hasTable('parent_notes'))
                ? \App\Models\ParentNote::where('student_id', $user->id)->where('from_parent', false)->whereNull('read_at')->count()
                : 0,
            // کاربرگ‌های پرشده‌ای که معلم هنوز تصحیح نکرده (نشان روی منوی «کاربرگ‌های ارسالی»)
            'worksheetsPending' => fn () => ($user && $user->hasRole(\App\Support\Roles::TEACHER)
                && \App\Support\DbSchema::hasColumn('worksheet_submissions', 'graded_at'))
                ? (int) rescue(fn () => \App\Models\WorksheetSubmission::whereNotNull('file_path')->whereNull('graded_at')
                    ->whereHas('worksheet', fn ($q) => $q->where('teacher_id', $user->id))->count(), 0, false)
                : 0,
            // املا/روخوانی‌های رسیده که معلم هنوز تصحیح نکرده (نشان روی منوی «املا و روخوانی»)
            'audioPending' => fn () => ($user && $user->hasRole(\App\Support\Roles::TEACHER) && \App\Models\AudioTask::ready())
                ? (int) rescue(fn () => \App\Models\AudioSubmission::whereNotNull('file_path')->whereNull('graded_at')
                    ->whereHas('task', fn ($q) => $q->where('teacher_id', $user->id))->count(), 0, false)
                : 0,
            // مسابقه‌ی زنده‌ی باز برای کلاسِ دانش‌آموز (نوارِ «بپیوند»)
            'liveNow' => fn () => rescue(fn () => ($user && $user->isStudent() && class_exists(\App\Models\LiveContest::class) && \App\Models\LiveContest::ready())
                ? \Illuminate\Support\Facades\Cache::remember('live-now:' . $user->id, 15, fn () => rescue(fn () => \App\Models\LiveContest::forStudent($user)
                    ->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('phase', ['question', 'reveal'])->where('updated_at', '>=', now()->subHours(3)))
                        ->orWhere(fn ($q) => $q->where('phase', 'lobby')->whereNotNull('starts_at')->whereBetween('starts_at', [now()->subHours(2), now()->addMinutes(10)])))
                    ->latest('id')->first(['id', 'title'])?->only(['id', 'title']), null, false) ?: false) ?: null
                : null, null, true),
            // «مرورِ اشتباه‌های من» که امروز آماده است (نشان روی منوی دانش‌آموز)
            'reviewDue' => fn () => ($user && $user->isStudent() && \App\Services\RemediationService::ready())
                ? (int) rescue(fn () => \App\Models\Remediation::where('student_id', $user->id)->where('status', 'open')
                    ->whereDate('due_on', '<=', now()->toDateString())->count(), 0, false)
                : 0,
        ];
    }
}
