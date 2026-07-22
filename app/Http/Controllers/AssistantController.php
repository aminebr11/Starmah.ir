<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** دستیارِ هوشمندِ هر کاربر — نقطه‌ی گفت‌وگو. */
class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message'          => ['required', 'string', 'max:1000'],
            'history'          => ['nullable', 'array', 'max:20'],
            'history.*.role'   => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $result = $assistant->reply($request->user(), $data['history'] ?? [], $data['message']);

        return response()->json($result);
    }
}
