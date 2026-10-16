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

    /** «✅ انجام بده»: کاری که ماهو پیشنهاد کرده بود، بعد از تأییدِ خودِ کاربر. */
    public function act(Request $request, \App\Services\MahooActions $actions): JsonResponse
    {
        $user = $request->user();
        if (! AssistantAccess::visible($user)) {
            return response()->json(['ok' => false, 'reply' => 'دستیار برای حسابِ شما بسته است.'], 403);
        }
        $data = $request->validate(['token' => ['required', 'string', 'max:4000']]);
        try {
            $res = $actions->execute($user, $data['token']);
        } catch (\Throwable $e) {
            Log::warning('mahoo act failed: ' . get_class($e) . ' — ' . $e->getMessage());
            $res = ['ok' => false, 'reply' => 'انجامِ این کار نشد؛ کمی بعد دوباره امتحان کنید.'];
        }

        return response()->json($res);
    }

    /** خوش‌آمدِ گفتاری (اگر سرویسِ صدا روشن باشد): فایلِ صدای «سلام <نام>!» */
    public function voice(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! AssistantAccess::visible($user) || ! (\App\Support\MahooAbilities::look($user)['voice'] ?? false) || ! \App\Services\SpeechService::available()) {
            return response()->json(['url' => null]);
        }
        // متن را خودِ سرور می‌سازد (فقط خوش‌آمد)، تا این مسیر برای ساختنِ صدای دلخواه به کار نرود
        $words = preg_split('/\s+/u', trim((string) $user->name)) ?: [];
        $first = preg_match('/^(خانم|آقای|آقا|دکتر|استاد)$/u', $words[0] ?? '') ? implode(' ', array_slice($words, 0, 2)) : ($words[0] ?? '');
        $text = $user->isStudent()
            ? "سلام {$first}! من ماهو هستم. خوش اومدی! امروز با هم کلی چیز یاد می‌گیریم."
            : "سلام {$first}، خوش آمدید! من ماهو هستم، هر کاری داشتید در خدمتم.";
        $url = rescue(fn () => app(\App\Services\SpeechService::class)->urlFor($text, $user->isStudent() ? 'kid' : 'reading'), null, false);

        return response()->json(['url' => $url]);
    }

    /** صفحه‌ی خوش‌آمد: نامِ کوچک، «نمای من» و اینکه پاسخ‌ها زنده (AI) است یا سریع. */
    public function hello(Request $request, AssistantService $assistant): JsonResponse
    {
        $user = $request->user();
        if (! AssistantAccess::visible($user)) {
            return response()->json(['blocked' => true], 403);
        }

        $stats = \Illuminate\Support\Facades\Cache::remember("assistant-hello:{$user->id}", 120, function () use ($assistant, $user) {
            try {
                return $assistant->snapshot($user);
            } catch (\Throwable $e) {
                Log::warning('assistant hello failed: ' . $e->getMessage());

                return [];
            }
        });

        return response()->json([
            'first' => trim(explode(' ', trim((string) $user->name))[0] ?? ''),
            'stats' => $stats,
            'ai'    => app(\App\Services\AiContentService::class)->isConfigured() && AssistantAccess::aiAllowed($user),
            // کارهایی که ماهو برای این کاربر انجام می‌دهد (به انتخابِ ادمینِ کل و مدیرِ مدرسه)
            'abilities' => \App\Support\MahooAbilities::forUser($user),
        ]);
    }
}
