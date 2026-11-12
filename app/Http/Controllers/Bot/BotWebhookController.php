<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Services\Bot\TeacherBot;
use App\Support\Bot\BotConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * دریافتِ پیام‌های بات (وب‌هوکِ بله و تلگرام). نشانی رازِ تصادفی دارد؛ همیشه ۲۰۰ برمی‌گردد
 * تا پیام‌رسان یک پیام را بارها نفرستد (خطا در لاگ ثبت می‌شود).
 */
class BotWebhookController extends Controller
{
    public function __invoke(Request $request, string $platform, string $secret): JsonResponse
    {
        if (! in_array($platform, ['bale', 'telegram'], true) || ! hash_equals(BotConfig::secret(), $secret)) {
            abort(404);
        }
        if (! BotConfig::ready($platform) || ! \App\Models\BotLink::ready()) {
            return response()->json(['ok' => true]);
        }
        // هر به‌روزرسانی فقط یک بار (پیام‌رسان در صورتِ تأخیر دوباره می‌فرستد)
        $id = (string) $request->input('update_id', '');
        if ($id !== '' && ! \Illuminate\Support\Facades\Cache::add('bot-upd:' . $platform . ':' . $id, 1, 600)) {
            return response()->json(['ok' => true]);
        }
        @set_time_limit(60);
        (new TeacherBot($platform))->handle($request->all());

        return response()->json(['ok' => true]);
    }
}
