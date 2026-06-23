<?php

namespace App\Http\Controllers;

use App\Support\Roles;
use Illuminate\Http\Request;

/** مسیریابی بعد از ورود بر اساس نقش کاربر. */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return match (true) {
            $user->hasRole(Roles::SUPER_ADMIN)  => redirect()->route('admin.overview'),
            $user->hasRole(Roles::SCHOOL_ADMIN) => redirect()->route('school.overview'),
            $user->hasRole(Roles::TEACHER)      => redirect()->route('teacher.dashboard'),
            $user->hasRole(Roles::PARENT)       => redirect()->route('messages.index'),
            // دانش‌آموزی که هنوز دنیای علاقه‌اش را نساخته → onboarding
            $user->isStudent() && ! $user->theme_id => redirect()->route('world.choose'),
            default                              => app(DashboardController::class)($request, app(\App\Services\ThemeEngine::class)),
        };
    }
}
