<?php

namespace App\Http\Middleware;

use App\Services\ThemeEngine;
use Illuminate\Http\Request;
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
            'auth' => [
                'user'  => $user,
                'roles' => $user ? $user->getRoleNames() : [],
            ],
            // تم فعال در همه‌ی صفحات در دسترس است تا فرانت ظاهر را بسازد
            'theme' => app(ThemeEngine::class)->presentation($theme),
            'flash' => ['flash' => fn () => $request->session()->get('flash')],
            // اعلان‌های زنگوله‌ی دانش‌آموز: موارد انضباطی ۲۴ ساعت اخیر
            'notifications' => fn () => $this->studentNotifications($user),
        ];
    }

    /** موارد انضباطی ۲۴ ساعت اخیر برای زنگوله‌ی دانش‌آموز. */
    private function studentNotifications($user): array
    {
        if (! $user || ! $user->hasRole(\App\Support\Roles::STUDENT)) {
            return [];
        }
        return \App\Models\DisciplineRecord::where('student_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->latest()->limit(10)->get()
            ->map(fn ($r) => [
                'title' => $r->title ?? ($r->points >= 0 ? 'تشویق' : 'تذکر'),
                'points' => $r->points,
                'kind' => $r->points >= 0 ? 'positive' : 'negative',
            ])->all();
    }
}
