<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiChat;
use App\Support\AiConfig;
use App\Support\AiUsage;
use App\Support\Jalali;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** مرکزِ هوش مصنوعی: انتخابِ سرویس و مدل، آزمایشِ اتصال، قیمت و گزارشِ مصرف. */
class AiCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $mask = fn ($v) => $v ? '••••' . mb_substr($v, -4) : null;
        $active = AiConfig::provider();

        $providers = collect(AiConfig::PROVIDERS)->map(function ($m, $k) use ($mask, $active) {
            $test = json_decode((string) Setting::get("ai_test_$k"), true) ?: null;
            $last = DB::table('ai_usages')->where('provider', $k)->where('ok', true)->max('created_at');

            return [
                'key' => $k, 'label' => $m['label'], 'vendor' => $m['vendor'], 'emoji' => $m['emoji'], 'hint' => $m['hint'], 'site' => $m['site'],
                'family' => $m['family'], 'models' => $m['models'], 'model' => AiConfig::model($k), 'default' => $m['default'],
                'base' => $k === 'custom' ? Setting::get('ai_base_custom') : $m['base'],
                'has_key' => (bool) AiConfig::key($k), 'key_hint' => $mask(Setting::get($m['key'])) ?? (AiConfig::key($k) ? 'از فایلِ env' : null),
                'active' => $k === $active, 'configured' => AiConfig::configured($k),
                'test' => $test, 'last_ok' => $last ? Jalali::format(\Illuminate\Support\Carbon::parse($last), true) . ' ' . Jalali::fa(substr($last, 11, 5)) : null,
            ];
        })->values();

        return Inertia::render('Admin/AiCenter', [
            'active' => $active, 'providers' => $providers,
            'prices' => $this->prices(), 'currency' => Setting::get('ai_currency', 'تومان'),
            // گزارشِ مصرف «کارِ جانبی» است: اگر خطا بدهد (جدولِ ناموجود، داده‌ی ناقص)، تنظیماتِ هوش مصنوعی باید باز بماند
            'days' => $days, 'usage' => rescue(fn () => $this->usage($days), fn ($e) => ['error' => \App\Http\Controllers\Concerns\FriendlySaveErrors::explainError($e)], true),
            'features' => AiUsage::FEATURES,
            'server' => ['max_execution_time' => (int) ini_get('max_execution_time'), 'curl' => function_exists('curl_init')],
            'qtest' => json_decode((string) Setting::get('ai_testq_' . $active), true) ?: null,
            'tts' => ['engine' => \App\Services\SpeechService::engine(), 'on' => Setting::get('tts_enabled', '1') !== '0'],
        ]);
    }

    /** «بخوان برایم»: روشن/خاموش و آزمایشِ صدای فارسی. */
    public function tts(Request $request, \App\Services\SpeechService $speech): \Illuminate\Http\JsonResponse
    {
        if ($request->has('on')) {
            Setting::put('tts_enabled', $request->boolean('on') ? '1' : '0');
        }
        if (! $request->boolean('test')) {
            return response()->json(['ok' => true]);
        }
        if (! \App\Services\SpeechService::engine()) {
            return response()->json(['ok' => false, 'message' => 'برای صدای فارسی، کلیدِ OpenAI یا Gemini را در همین صفحه ثبت کنید (لازم نیست سرویسِ فعال باشد).']);
        }
        try {
            $url = $speech->urlFor('سلام! من ستاره ماه هستم. این یک آزمایشِ صدای فارسی است.');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => \App\Http\Controllers\Concerns\FriendlySaveErrors::explainError($e)]);
        }

        return response()->json($url ? ['ok' => true, 'url' => $url] : ['ok' => false, 'message' => 'سرویسِ صدا پاسخ نداد؛ اعتبارِ کلید یا دسترسیِ سرور به اینترنت را بررسی کنید.']);
    }

    /** ذخیره‌ی سرویسِ فعال، کلیدها، مدل‌ها و نشانیِ سرویسِ سازگار. */
    public function save(Request $request): RedirectResponse
    {
        $keys = array_keys(AiConfig::PROVIDERS);
        $data = $request->validate([
            'active' => ['required', 'in:off,' . implode(',', $keys)],
            'providers' => ['array'],
            'providers.*.key' => ['nullable', 'string', 'max:300'],
            'providers.*.model' => ['nullable', 'string', 'max:100'],
            'providers.*.base' => ['nullable', 'url', 'max:200'],
        ]);
        foreach ($data['providers'] ?? [] as $k => $p) {
            $m = AiConfig::PROVIDERS[$k] ?? null;
            if (! $m) continue;
            if (! empty($p['key'])) Setting::put($m['key'], trim($p['key']));
            if (array_key_exists('model', $p)) Setting::put($m['model'], trim((string) $p['model']) ?: $m['default']);
            if ($k === 'custom' && array_key_exists('base', $p)) Setting::put('ai_base_custom', trim((string) $p['base']));
        }
        if ($data['active'] !== 'off' && ! AiConfig::configured($data['active'])) {
            return back()->withErrors(['active' => 'برای فعال‌کردنِ «' . AiConfig::PROVIDERS[$data['active']]['label'] . '» ابتدا کلید' . ($data['active'] === 'custom' ? ' و نشانیِ سرویس' : '') . ' را وارد کنید.']);
        }
        Setting::put('ai_provider', $data['active']);

        return back()->with('flash', $data['active'] === 'off'
            ? 'هوش مصنوعی برای کلِ سامانه خاموش شد.'
            : 'سرویسِ فعال: ' . AiConfig::PROVIDERS[$data['active']]['label'] . ' — ' . AiConfig::model($data['active']) . ' ✅');
    }

    /** آزمایشِ زنده‌ی اتصال — یک پیامِ کوتاه می‌فرستد. */
    public function test(Request $request): JsonResponse
    {
        $p = $request->validate(['provider' => ['required', 'in:' . implode(',', array_keys(AiConfig::PROVIDERS))]])['provider'];
        AiUsage::$feature = 'test';
        $r = AiChat::send('تو یک دستیارِ آموزشیِ فارسی‌زبان هستی. خیلی کوتاه جواب بده.', 'سلام! در یک جمله‌ی کوتاه بگو آماده‌ای به بچه‌های دبستان کمک کنی.', 60, $p, 25);
        $result = [
            'ok' => $r['ok'], 'ms' => $r['ms'], 'model' => $r['model'], 'reply' => mb_substr($r['text'], 0, 200),
            'error' => $r['error'], 'in' => $r['in'], 'out' => $r['out'],
            'at' => Jalali::format(now(), true) . ' ' . Jalali::fa(now()->format('H:i')),
        ];
        Setting::put("ai_test_$p", json_encode($result, JSON_UNESCAPED_UNICODE));

        return response()->json($result);
    }

    /** آزمایشِ مسیرِ واقعیِ طراحیِ سؤال (همان که معلم‌ها استفاده می‌کنند) با ۲ سؤال. */
    public function testQuestions(Request $request, \App\Services\SmartExamAiService $ai): JsonResponse
    {
        AiUsage::$feature = 'test';
        $r = $ai->generate([
            'audience' => 'exam', 'types' => ['mc'], 'count' => 2, 'difficulty' => 'easy', 'bloom' => 'mixed',
            'level' => 'ابتدایی', 'grade' => 'چهارم', 'subject' => 'ریاضی', 'book' => 'ریاضی', 'topic' => 'جمع و تفریق', 'goal' => '',
            'kind' => 'practice', 'flavor' => '', 'instructions' => '', 'avoid' => [], 'sample' => false,
            'school_id' => null, 'teacher_id' => $request->user()->id,
        ]);
        $result = [
            'ok' => (bool) $r['ok'] && count($r['questions'] ?? []) > 0,
            'n' => count($r['questions'] ?? []), 'seconds' => $r['stats']['seconds'] ?? null,
            'message' => $r['message'] ?? null, 'sample' => ($r['questions'][0]['prompt'] ?? null),
            'at' => Jalali::format(now(), true) . ' ' . Jalali::fa(now()->format('H:i')),
        ];
        Setting::put('ai_testq_' . AiConfig::provider(), json_encode($result, JSON_UNESCAPED_UNICODE));

        return response()->json($result);
    }

    /** قیمتِ هر یک میلیون توکن (ورودی/خروجی) برای هر مدل. */
    public function prices(?Request $request = null): array|RedirectResponse
    {
        if ($request && $request->isMethod('post')) {
            $data = $request->validate([
                'currency' => ['required', 'in:تومان,دلار,ریال'],
                'prices' => ['array'],
                'prices.*.in' => ['nullable', 'numeric', 'min:0'],
                'prices.*.out' => ['nullable', 'numeric', 'min:0'],
            ]);
            $clean = [];
            foreach ($data['prices'] ?? [] as $model => $p) {
                if (($p['in'] ?? null) === null && ($p['out'] ?? null) === null) continue;
                $clean[mb_substr($model, 0, 100)] = ['in' => (float) ($p['in'] ?? 0), 'out' => (float) ($p['out'] ?? 0)];
            }
            Setting::put('ai_prices', json_encode($clean, JSON_UNESCAPED_UNICODE));
            Setting::put('ai_currency', $data['currency']);

            return back()->with('flash', 'قیمت‌ها ذخیره شد؛ هزینه‌ها دوباره محاسبه شدند 💰');
        }

        return json_decode((string) Setting::get('ai_prices'), true) ?: [];
    }

    public function savePrices(Request $request): RedirectResponse
    {
        return $this->prices($request);
    }

    /* ================= گزارشِ مصرف ================= */

    private function cost(Collection $rows): float
    {
        $prices = $this->prices();

        return round($rows->sum(fn ($r) => (($r->tin ?? 0) * ($prices[$r->model]['in'] ?? 0) + ($r->tout ?? 0) * ($prices[$r->model]['out'] ?? 0)) / 1_000_000), 2);
    }

    private function usage(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $q = fn () => DB::table('ai_usages')->where('created_at', '>=', $from);
        $agg = 'COUNT(*) as req, SUM(CASE WHEN ok THEN 1 ELSE 0 END) as okc, SUM(input_tokens) as tin, SUM(output_tokens) as tout, AVG(ms) as ms';

        // به تفکیکِ مدل (پایه‌ی هزینه)
        $byModel = $q()->groupBy('provider', 'model')->selectRaw("provider, model, $agg")->get();
        $totals = [
            'requests' => (int) $byModel->sum('req'), 'ok' => (int) $byModel->sum('okc'),
            'in' => (int) $byModel->sum('tin'), 'out' => (int) $byModel->sum('tout'),
            'cost' => $this->cost($byModel),
            'ms' => (int) round($q()->where('ok', true)->avg('ms') ?? 0),
        ];

        // روزانه
        $daily = $q()->selectRaw("DATE(created_at) as d, COUNT(*) as req, SUM(input_tokens + output_tokens) as tok")->groupBy('d')->pluck('tok', 'd');
        $dreq = $q()->selectRaw('DATE(created_at) as d, COUNT(*) as req')->groupBy('d')->pluck('req', 'd');
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $series[] = ['date' => $d->toDateString(), 'label' => Jalali::ymParts($d)['short'], 'value' => (int) ($daily[$d->toDateString()] ?? 0), 'req' => (int) ($dreq[$d->toDateString()] ?? 0)];
        }

        // کاربرد
        $byFeature = $q()->groupBy('feature', 'model')->selectRaw("feature, model, $agg")->get()->groupBy('feature')
            ->map(fn ($g, $f) => ['key' => $f ?: 'other', 'label' => AiUsage::FEATURES[$f] ?? AiUsage::FEATURES['other'],
                'requests' => (int) $g->sum('req'), 'tokens' => (int) ($g->sum('tin') + $g->sum('tout')), 'cost' => $this->cost($g)])
            ->sortByDesc('tokens')->values();

        // مدرسه‌ها + معلم‌هایشان (مصرفِ دانش‌آموز به نامِ معلمش)
        $bySchoolModel = $q()->groupBy('school_id', 'model')->selectRaw("school_id, model, $agg, COUNT(DISTINCT user_id) as users")->get()->groupBy('school_id');
        $schoolNames = School::pluck('name', 'id');
        $byTeacher = $q()->whereNotNull('teacher_id')->groupBy('teacher_id', 'role', 'model')->selectRaw("teacher_id, role, model, $agg")->get()->groupBy('teacher_id');
        $teachers = User::whereIn('id', $byTeacher->keys())->get(['id', 'name', 'school_id'])->keyBy('id');
        $teacherRows = $byTeacher->map(function ($g, $tid) use ($teachers) {
            $own = $g->where('role', Roles::TEACHER);
            $stu = $g->where('role', Roles::STUDENT);

            return [
                'id' => (int) $tid, 'name' => $teachers->get($tid)?->name ?? 'معلمِ حذف‌شده', 'school_id' => $teachers->get($tid)?->school_id,
                'own_tokens' => (int) ($own->sum('tin') + $own->sum('tout')), 'own_req' => (int) $own->sum('req'), 'own_cost' => $this->cost($own),
                'stu_tokens' => (int) ($stu->sum('tin') + $stu->sum('tout')), 'stu_req' => (int) $stu->sum('req'), 'stu_cost' => $this->cost($stu),
                'tokens' => (int) ($g->sum('tin') + $g->sum('tout')), 'cost' => $this->cost($g),
            ];
        })->sortByDesc('tokens')->values();
        $schools = $bySchoolModel->map(fn ($g, $sid) => [
            'id' => $sid ? (int) $sid : null, 'name' => $sid ? ($schoolNames[$sid] ?? 'مدرسه‌ی حذف‌شده') : 'بدونِ مدرسه (ادمین/مهمان)',
            'requests' => (int) $g->sum('req'), 'in' => (int) $g->sum('tin'), 'out' => (int) $g->sum('tout'),
            'tokens' => (int) ($g->sum('tin') + $g->sum('tout')), 'cost' => $this->cost($g), 'users' => (int) $g->max('users'),
            'teachers' => $teacherRows->where('school_id', $sid ? (int) $sid : null)->values(),
        ])->sortByDesc('tokens')->values();

        // پرمصرف‌ترین کاربران
        $byUser = $q()->whereNotNull('user_id')->groupBy('user_id', 'model')->selectRaw("user_id, model, $agg")->get()->groupBy('user_id');
        $users = User::with('roles:id,name')->whereIn('id', $byUser->keys())->get(['id', 'name', 'school_id'])->keyBy('id');
        // کاربرِ حذف‌شده هم ممکن است در سابقه‌ی مصرف باشد → get() به‌جای [] (وگرنه «Undefined array key» و خطای ۵۰۰)
        $topUsers = $byUser->map(fn ($g, $uid) => [
            'id' => (int) $uid, 'name' => $users->get($uid)?->name ?? 'کاربرِ حذف‌شده',
            'role' => \App\Services\VisitAnalytics::ROLE_LABELS[$users->get($uid)?->roles->first()?->name ?? ''] ?? '—',
            'school' => $schoolNames[$users->get($uid)?->school_id ?? 0] ?? '—',
            'requests' => (int) $g->sum('req'), 'tokens' => (int) ($g->sum('tin') + $g->sum('tout')), 'cost' => $this->cost($g),
        ])->sortByDesc('tokens')->take(30)->values();

        $models = $byModel->map(fn ($r) => [
            'provider' => $r->provider, 'label' => AiConfig::PROVIDERS[$r->provider]['label'] ?? $r->provider, 'model' => $r->model ?: '—',
            'requests' => (int) $r->req, 'ok' => (int) $r->okc, 'in' => (int) $r->tin, 'out' => (int) $r->tout, 'ms' => (int) round($r->ms ?? 0),
            'cost' => $this->cost(collect([$r])),
        ])->sortByDesc('requests')->values();

        $recentErrors = $q()->where('ok', false)->latest('created_at')->limit(8)->get(['provider', 'model', 'status', 'feature', 'created_at'])
            ->map(fn ($r) => ['provider' => AiConfig::PROVIDERS[$r->provider]['label'] ?? $r->provider, 'model' => $r->model, 'status' => $r->status,
                'feature' => AiUsage::FEATURES[$r->feature] ?? '', 'at' => Jalali::ymParts(\Illuminate\Support\Carbon::parse($r->created_at))['short'] . ' ' . Jalali::fa(substr($r->created_at, 11, 5)),
                'at_raw' => \Illuminate\Support\Carbon::parse($r->created_at)->timestamp]);

        return compact('totals', 'series', 'byFeature', 'schools', 'topUsers', 'models', 'recentErrors');
    }
}
