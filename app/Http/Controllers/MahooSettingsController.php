<?php

namespace App\Http\Controllers;

use App\Support\MahooAbilities;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «🌙 ماهو» — تنظیماتِ دستیار:
 *   ادمینِ کل   → کارهای مجاز در کلِ سامانه، ورودِ نمایشی، صدا و خوش‌آمدِ گفتاری.
 *   مدیرِ مدرسه → دیده‌شدنِ ماهو و هوش مصنوعی برای معلم‌ها/دانش‌آموزان + کارهای مجازِ هر گروه.
 */
class MahooSettingsController extends Controller
{
    private function defs(): array
    {
        return collect(MahooAbilities::LIST)->map(fn ($d, $k) => ['key' => $k, 'title' => $d[0], 'desc' => $d[1], 'roles' => $d[2]])->values()->all();
    }

    public function show(Request $request): Response
    {
        $u = $request->user();
        $isSuper = $u->hasRole(Roles::SUPER_ADMIN);
        $school = $isSuper ? null : $u->school;
        abort_unless($isSuper || ($u->hasRole(Roles::SCHOOL_ADMIN) && $school), 403);

        return Inertia::render('Mahoo/Settings', [
            'scope' => $isSuper ? 'global' : 'school',
            'defs' => $this->defs(),
            'roles' => MahooAbilities::SCHOOL_ROLES,
            'global' => MahooAbilities::global(),
            'school' => $school ? MahooAbilities::school((int) $school->id) : null,
            'access' => $school ? [
                'students' => $school->assistant_students !== false,
                'teachers' => $school->assistant_teachers !== false,
                'ai' => $school->assistant_ai !== false,
            ] : null,
            'aiReady' => app(\App\Services\AiContentService::class)->isConfigured(),
            'voiceReady' => \App\Services\SpeechService::available(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $u = $request->user();
        $isSuper = $u->hasRole(Roles::SUPER_ADMIN);
        $school = $isSuper ? null : $u->school;
        abort_unless($isSuper || ($u->hasRole(Roles::SCHOOL_ADMIN) && $school), 403);

        $data = $request->validate([
            'abilities' => ['present', 'array'],
            'entrance' => ['boolean'], 'sound' => ['boolean'], 'voice' => ['boolean'],
            'access' => ['nullable', 'array'], 'access.students' => ['boolean'], 'access.teachers' => ['boolean'], 'access.ai' => ['boolean'],
        ]);
        $look = ['entrance' => $request->boolean('entrance'), 'sound' => $request->boolean('sound'), 'voice' => $request->boolean('voice')];

        if ($isSuper) {
            MahooAbilities::saveGlobal($data['abilities'], $look);
        } else {
            MahooAbilities::saveSchool((int) $school->id, $data['abilities'], $look);
            if (isset($data['access'])) {
                $school->update([
                    'assistant_students' => (bool) ($data['access']['students'] ?? true),
                    'assistant_teachers' => (bool) ($data['access']['teachers'] ?? true),
                    'assistant_ai' => (bool) ($data['access']['ai'] ?? true),
                ]);
            }
        }

        return back(303)->with('flash', '🌙 تنظیماتِ ماهو ذخیره شد.');
    }
}
