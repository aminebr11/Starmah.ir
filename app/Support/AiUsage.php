<?php

namespace App\Support;

use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\DB;

/**
 * ثبتِ خودکارِ مصرفِ توکن از پاسخِ هر سرویسِ هوش مصنوعی.
 *
 * به رویدادِ ResponseReceivedِ کلاینتِ HTTP گوش می‌دهد؛ پس هر بخشی که با
 * سرویس‌های تعریف‌شده در AiConfig حرف بزند — امروز یا بعداً — بی‌هیچ تغییری
 * حساب می‌شود. مصرفِ دانش‌آموز به نامِ معلمِ کلاسش هم ثبت می‌شود.
 */
class AiUsage
{
    /** کاربرد (اختیاری) — اگر خالی باشد از نامِ مسیرِ درخواست حدس زده می‌شود. */
    public static ?string $feature = null;

    public const FEATURES = [
        'assistant' => '🤖 دستیارِ هوشمند', 'questions' => '🧠 طراحیِ سؤال (آزمون/بازی/مأموریت)', 'worksheet' => '📝 کاربرگ',
        'announcement' => '📢 اطلاعیه و پیام', 'image' => '🎨 تصویرسازی', 'test' => '🧪 آزمایشِ اتصال', 'other' => '✨ سایر',
    ];

    public static function handle(ResponseReceived $e): void
    {
        try {
            $url = (string) $e->request->url();
            $provider = AiConfig::providerForUrl($url);
            if (! $provider) return;

            $j = $e->response->json() ?? [];
            [$in, $out] = self::tokens(is_array($j) ? $j : []);
            $body = $e->request->data();
            $model = (string) ($body['model'] ?? data_get($j, 'model') ?? (preg_match('#models/([^:/]+)#', $url, $m) ? $m[1] : ''));

            $user = auth()->user();
            $role = $user?->roles->first()?->name;
            $teacherId = null;
            if ($user && $role === Roles::TEACHER) {
                $teacherId = $user->id;
            } elseif ($user && $role === Roles::STUDENT) {
                $teacherId = DB::table('classroom_student')->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
                    ->where('classroom_student.student_id', $user->id)->value('classrooms.teacher_id');
            }

            DB::table('ai_usages')->insert([
                'user_id' => $user?->id, 'school_id' => $user?->school_id, 'teacher_id' => $teacherId, 'role' => $role,
                'provider' => $provider, 'model' => mb_substr($model, 0, 80) ?: null,
                'feature' => self::$feature ?? self::guessFeature($url),
                'input_tokens' => $in, 'output_tokens' => $out,
                'ok' => $e->response->successful(), 'status' => $e->response->status(),
                'ms' => (int) round(((float) ($e->response->handlerStats()['total_time'] ?? 0)) * 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $x) {
            // آمار هرگز نباید پاسخِ هوش مصنوعی را خراب کند
        }
    }

    /** توکن‌های ورودی/خروجی از قالب‌های Anthropic، OpenAI و Gemini. */
    public static function tokens(array $j): array
    {
        if (isset($j['usage']['input_tokens']) || isset($j['usage']['output_tokens'])) {
            return [(int) ($j['usage']['input_tokens'] ?? 0) + (int) ($j['usage']['cache_read_input_tokens'] ?? 0), (int) ($j['usage']['output_tokens'] ?? 0)];
        }
        if (isset($j['usage']['prompt_tokens']) || isset($j['usage']['completion_tokens'])) {
            return [(int) ($j['usage']['prompt_tokens'] ?? 0), (int) ($j['usage']['completion_tokens'] ?? 0)];
        }
        if (isset($j['usageMetadata'])) {
            return [(int) ($j['usageMetadata']['promptTokenCount'] ?? 0), (int) ($j['usageMetadata']['candidatesTokenCount'] ?? 0)];
        }

        return [0, 0];
    }

    private static function guessFeature(string $url): string
    {
        if (str_contains($url, 'images') || str_contains($url, 'generateContent')) return 'image';
        $r = (string) (request()?->route()?->getName() ?? '');

        return match (true) {
            str_starts_with($r, 'admin.ai') => 'test',
            str_contains($r, 'assistant') => 'assistant',
            str_contains($r, 'worksheet') => 'worksheet',
            str_contains($r, 'announcement') || str_contains($r, 'message') || str_contains($r, 'notes') => 'announcement',
            str_contains($r, 'ai') || str_contains($r, 'smart') || str_contains($r, 'studio') || str_contains($r, 'mission') || str_contains($r, 'bank') => 'questions',
            default => 'other',
        };
    }
}
