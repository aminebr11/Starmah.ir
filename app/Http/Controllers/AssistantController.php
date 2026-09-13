<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use App\Support\AssistantAccess;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * نقطه‌ی گفت‌وگوی دستیار.
 *
 * قاعده‌ی این کنترلر: **هرگز با خطا برنگرد**. اگر هوش مصنوعی در دسترس
 * نبود، کلید نبود، یا حتی خودِ سرویس استثنا داد، باز هم یک پاسخِ مفیدِ
 * سامانه‌ای برمی‌گردد. تنها حالتی که ۴۰۳ می‌دهیم، بسته‌بودنِ دسترسی
 * توسطِ مدیرِ مدرسه است — و آن هم با پیامِ روشن.
 */
class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant): JsonResponse
    {
        $user = $request->user();

        if (! AssistantAccess::visible($user)) {
            return response()->json([
                'reply' => 'دستیارِ هوشمند برای حسابِ شما توسطِ مدیرِ مدرسه بسته شده است.',
                'mode'  => 'blocked',
            ], 403);
        }

        $data = $request->validate([
            'message'           => ['required', 'string', 'max:1000'],
            'history'           => ['nullable', 'array', 'max:20'],
            'history.*.role'    => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        try {
            $result = $assistant->reply($user, $data['history'] ?? [], $data['message']);
        } catch (\Throwable $e) {
            // حتی اگر همه‌چیز بشکند، کاربر نباید «نتوانستم پاسخ بدهم» ببیند
            Log::warning('assistant failed: ' . get_class($e) . ' — ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            $result = [
                'reply' => $assistant->safeReply($user, $data['message']),
                'mode'  => 'local',
            ];
        }

        // تشخیصِ عیب فقط برای مدیرِ مدرسه و ادمینِ کل: اگر بخشی از داده
        // ساخته نشد (جدول/مهاجرتِ کم روی سرور) همان‌جا دیده شود، نه فقط در لاگ.
        $skipped = $result['skipped'] ?? [];
        unset($result['skipped']);
        if ($skipped && $user->hasAnyRole([Roles::SCHOOL_ADMIN, Roles::SUPER_ADMIN])) {
            $result['diag'] = 'این بخش‌ها از پایگاه‌داده خوانده نشد: ' . implode('، ', $skipped)
                . ' — احتمالاً مهاجرتِ پایگاه‌داده روی سرور اجرا نشده است.';
        }

        return response()->json($result);
    }
}
