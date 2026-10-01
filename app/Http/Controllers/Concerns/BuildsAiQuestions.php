<?php

namespace App\Http\Controllers\Concerns;

use App\Services\SmartExamAiService;
use App\Support\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ورودیِ مشترکِ همه‌ی دکمه‌های «ساخت سؤال با هوش مصنوعی».
 *
 * فرم‌ها (آزمون‌ساز، استودیوی بازی، بازی‌ساز، مأموریت، بانک) همه یک «زمینه‌ی
 * درسی» می‌فرستند: کلاس یا پایه، درس، فصل (شناسه)، مبحث، هدف… اینجا اعتبارسنجی
 * و با Curriculum کامل می‌شود (عنوان و درس‌های فصل از جدولِ فصل‌ها) و به سرویس
 * داده می‌شود؛ پس هیچ فرمی نمی‌تواند بخشی از زمینه را جا بیندازد.
 */
trait BuildsAiQuestions
{
    protected function aiRespond(Request $request, SmartExamAiService $ai, string $audience, array $types, int $max = 20, array $extra = []): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['nullable', 'integer'],
            'grade' => ['nullable', 'string', 'max:40'],
            'level' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'book' => ['nullable', 'string', 'max:120'],
            'chapter_id' => ['nullable', 'integer'],
            'chapter' => ['nullable', 'string', 'max:160'],
            'topic' => ['nullable', 'string', 'max:160'],
            'goal' => ['nullable', 'string', 'max:300'],
            'kind' => ['nullable', 'string', 'max:40'],
            'count' => ['required', 'integer', 'min:1', 'max:' . $max],
            'type' => ['nullable', 'string', 'max:10'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:' . implode(',', $types)],
            'difficulty' => ['nullable', 'in:easy,medium,hard,mixed'],
            'bloom' => ['nullable', 'in:remember,understand,apply,analyze,mixed'],
            'flavor' => ['nullable', 'string', 'max:60'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'avoid' => ['nullable', 'array', 'max:80'],
            'avoid.*' => ['nullable', 'string', 'max:600'],
            'sample' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $ctx = Curriculum::resolve($data, $user);
        self::guardFatal();

        $requested = $data['types'] ?? [];
        if (! $requested && ! empty($data['type'])) {
            $requested = [$data['type'] === 'short' ? 'blank' : $data['type']];
        }
        $requested = array_values(array_intersect($requested ?: [$types[0]], $types));

        $result = $ai->generate(array_merge($ctx, [
            'audience' => $audience,
            'types' => $requested ?: [$types[0]],
            'count' => $data['count'],
            'difficulty' => $data['difficulty'] ?? 'medium',
            'bloom' => $data['bloom'] ?? 'mixed',
            'kind' => $data['kind'] ?? '',
            'flavor' => $data['flavor'] ?? '',
            'instructions' => $data['instructions'] ?? '',
            'avoid' => $data['avoid'] ?? [],
            'sample' => (bool) ($data['sample'] ?? false),
            'school_id' => $user->school_id, 'teacher_id' => $user->id,
        ], $extra));

        // زمینه‌ی نهایی را برمی‌گردانیم تا فرم همان برچسب‌ها را هنگامِ ذخیره بفرستد
        $result['context'] = $ctx;
        return response()->json($result);
    }

    /**
     * اگر هاست وسطِ کار PHP را به‌خاطرِ سقفِ زمان (max_execution_time) قطع کند، مرورگر
     * پاسخِ خالی می‌گرفت و پنلِ طراحیِ سؤال یک کادرِ قرمزِ خالی نشان می‌داد.
     * این تابع در همان لحظه یک پاسخِ JSONِ روشن می‌فرستد.
     */
    protected static function guardFatal(): void
    {
        register_shutdown_function(function () {
            $e = error_get_last();
            if (! $e || ! in_array($e['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true) || headers_sent()) {
                return;
            }
            $timeout = str_contains((string) $e['message'], 'Maximum execution time');
            while (ob_get_level() > 0) @ob_end_clean();
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false, 'mode' => 'error', 'questions' => [],
                'message' => $timeout
                    ? 'زمانِ اجرای سرور (حدود ' . (int) ini_get('max_execution_time') . ' ثانیه) پیش از رسیدنِ پاسخِ هوش مصنوعی تمام شد. تعدادِ سؤال را کمتر کنید یا در «مرکزِ هوش مصنوعی» مدلِ سریع‌تری انتخاب کنید (gpt-4o-mini، gpt-4.1-mini یا deepseek-chat). مدیرِ هاست هم می‌تواند max_execution_time را به ۱۲۰ ثانیه برساند.'
                    : 'خطای داخلیِ سرور هنگامِ طراحیِ سؤال رخ داد؛ دوباره تلاش کنید.',
            ], JSON_UNESCAPED_UNICODE);
        });
    }
}
