<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Services\EarlyWarningService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** مدیرِ مدرسه: «🚨 هشدارِ زودهنگام». */
class EarlyWarningController extends Controller
{
    public function index(Request $request, EarlyWarningService $svc): Response
    {
        $schoolId = (int) $request->user()->school_id;

        return Inertia::render('SchoolAdmin/EarlyWarning', $svc->forSchool($schoolId, $request->boolean('refresh')));
    }
}
