<?php

namespace App\Http\Controllers;

use App\Services\SpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «بخوان برایم» — فایلِ صوتیِ فارسیِ یک متن (وقتی دستگاه صدای فارسی ندارد). */
class SpeechController extends Controller
{
    public function __invoke(Request $request, SpeechService $speech): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $url = rescue(fn () => $speech->urlFor($data['text']), null, true);

        return $url
            ? response()->json(['url' => $url])
            : response()->json(['url' => null, 'message' => 'صدای فارسی فعلاً در دسترس نیست.'], 503);
    }
}
