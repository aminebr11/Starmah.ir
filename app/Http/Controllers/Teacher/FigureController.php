<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Support\AiConfig;
use App\Support\Figure;
use App\Support\QuestionMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** 🖼️ شکلِ سؤال در آزمون‌ساز و استودیوی بازی: رسم، عکس، و ساخت با هوش مصنوعی. */
class FigureController extends Controller
{
    /** پیش‌نمایشِ زنده (ذخیره نمی‌شود). */
    public function preview(Request $request): JsonResponse
    {
        $svg = Figure::svg($request->input('spec'));

        return response()->json(['ok' => (bool) $svg, 'svg' => $svg, 'spec' => Figure::normalize($request->input('spec'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $url = QuestionMedia::storeFigure($request->input('spec'));

        return $url ? response()->json(['ok' => true, 'url' => $url])
            : response()->json(['ok' => false, 'message' => 'شکل کامل نیست؛ عددها و برچسب‌ها را بررسی کنید.'], 422);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:6144', 'mimes:jpg,jpeg,png,webp,gif']], [
            'image.max' => 'حجمِ عکس حداکثر ۶ مگابایت است.', 'image.mimes' => 'فقط عکس (JPG، PNG، WebP) پذیرفته می‌شود.',
        ]);
        $url = QuestionMedia::storeImage((string) file_get_contents($request->file('image')->getRealPath()));

        return $url ? response()->json(['ok' => true, 'url' => $url])
            : response()->json(['ok' => false, 'message' => 'این فایل عکسِ معتبر نیست.'], 422);
    }

    /** هوش مصنوعی برای یک سؤال شکلِ مناسب پیشنهاد می‌دهد (توصیف + پیش‌نمایش؛ معلم تأیید می‌کند). */
    public function ai(Request $request): JsonResponse
    {
        $d = $request->validate([
            'prompt' => ['required', 'string', 'max:800'], 'choices' => ['nullable', 'array', 'max:6'], 'choices.*' => ['nullable', 'string', 'max:200'],
            'answer' => ['nullable', 'string', 'max:300'], 'grade' => ['nullable', 'string', 'max:40'], 'subject' => ['nullable', 'string', 'max:80'],
            'hint' => ['nullable', 'string', 'max:300'],
        ]);
        if (AiConfig::provider() === 'off' || ! AiConfig::configured() || ! \App\Support\AssistantAccess::aiAllowed($request->user())) {
            return response()->json(['ok' => false, 'message' => 'هوش مصنوعی برای مدرسه‌ی شما فعال نیست؛ شکل را با «📐 رسم» بسازید.']);
        }
        $r = \App\Support\FigureAi::suggest($d['prompt'], $d['choices'] ?? [], (string) ($d['answer'] ?? ''), $d['grade'] ?? null, $d['subject'] ?? null, (string) ($d['hint'] ?? ''));

        return response()->json($r['ok'] ? ['ok' => true, 'spec' => $r['spec'], 'svg' => Figure::svg($r['spec'])] : $r);
    }
}
