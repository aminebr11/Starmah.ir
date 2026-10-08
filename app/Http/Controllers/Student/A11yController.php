<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\A11y;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ذخیره‌ی «بخوان برایم» و «متنِ درشت» در تنظیماتِ کاربر. */
class A11yController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'readAloud' => ['sometimes', 'boolean'],
            'largeText' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        $settings = $user->settings ?? [];
        $settings['a11y'] = array_merge(A11y::for($user), $data);
        $user->forceFill(['settings' => $settings])->save();

        return response()->json(A11y::for($user->fresh()));
    }
}
