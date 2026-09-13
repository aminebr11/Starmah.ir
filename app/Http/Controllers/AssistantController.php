<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use App\Support\AssistantAccess;
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
            Log::warning('assistant failed: ' . $e->getMessage());
            $result = [
                'reply' => $assistant->safeReply($user, $data['message']),
                'mode'  => 'local',
            ];
        }

        return response()->json($result);
    }
}
