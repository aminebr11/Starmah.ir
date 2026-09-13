<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SmsMessage;
use App\Services\SmsService;
use App\Support\Jalali;
use App\Support\SmsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * پنلِ پیامکِ ادمینِ کل — درگاه، دسترسیِ مدرسه‌ها، مصرف و سابقه.
 *
 * کلیدِ API هرگز به فرانت برنمی‌گردد؛ فقط چهار رقمِ آخرش نشان داده
 * می‌شود تا ادمین بداند کدام کلید نشسته است.
 */
class SmsAdminController extends Controller
{
    public function index(Request $request, SmsService $sms): Response
    {
        $mask = fn ($v) => $v ? '••••••••' . mb_substr((string) $v, -4) : '';
        $S = fn ($k, $d = null) => \App\Models\Setting::get($k, $d);

        $schools = School::orderBy('name')->get()->map(fn (School $s) => [
            'id'        => $s->id,
            'name'      => $s->name,
            'city'      => $s->city,
            'status'    => $s->status,
            'enabled'   => (bool) $s->sms_enabled,
            'quota'     => $s->sms_quota,
            'sender'    => $s->sms_sender,
            'used'      => SmsGateway::usedBySchool($s->id),
            'remaining' => SmsGateway::remainingForSchool($s),
        ]);

        return Inertia::render('Admin/Sms', [
            'gateway' => [
                'enabled'      => (bool) $S('sms_enabled', false),
                'provider'     => $S('sms_provider', 'off'),
                'sender'       => $S('sms_sender', ''),
                'api_key_set'  => (bool) $S('sms_api_key'),
                'api_key_hint' => $mask($S('sms_api_key')),
                'ready'        => $sms->enabled(),
                'endpoint'     => SmsService::IRANSMS_URL,
            ],
            'schools' => $schools,
            'stats'   => [
                'window'  => SmsGateway::WINDOW_DAYS,
                'sent'    => (int) SmsMessage::withoutGlobalScopes()->where('status', 'sent')
                    ->where('created_at', '>=', now()->subDays(SmsGateway::WINDOW_DAYS))->sum('segments'),
                'failed'  => SmsMessage::withoutGlobalScopes()->where('status', 'failed')
                    ->where('created_at', '>=', now()->subDays(SmsGateway::WINDOW_DAYS))->count(),
                'schools' => $schools->where('enabled', true)->count(),
            ],
            'log'    => $this->log(),
            'events' => SmsGateway::EVENTS,
        ]);
    }

    /** آخرین پیامک‌های کلِ سامانه. */
    private function log(): array
    {
        return SmsMessage::withoutGlobalScopes()->with(['school:id,name', 'sender:id,name'])
            ->latest()->limit(60)->get()
            ->map(fn ($m) => [
                'id'      => $m->id,
                'school'  => $m->school?->name,
                'sender'  => $m->sender?->name ?? 'سامانه',
                'phone'   => $m->phone,
                'body'    => \Illuminate\Support\Str::limit($m->body, 80),
                'kind'    => SmsGateway::EVENTS[$m->kind]['label'] ?? 'ارسالِ دستی',
                'status'  => $m->status,
                'error'   => $m->error,
                'segments' => $m->segments,
                'date'    => Jalali::format($m->created_at, true) . ' ساعت ' . Jalali::fa($m->created_at->format('H:i')),
            ])->all();
    }

    /** تنظیمِ درگاه (سراسری). */
    public function storeGateway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sms_enabled'  => ['boolean'],
            'sms_provider' => ['nullable', 'in:off,iransms,custom'],
            'sms_sender'   => ['nullable', 'string', 'max:40'],
            'sms_api_key'  => ['nullable', 'string', 'max:300'],
        ]);

        $put = fn ($k, $v) => \App\Models\Setting::put($k, $v);
        $put('sms_enabled', $request->boolean('sms_enabled'));
        $put('sms_provider', $data['sms_provider'] ?? 'off');
        $put('sms_sender', $data['sms_sender'] ?? '');
        // کلیدِ خالی یعنی «دست نزن»، تا ذخیره‌ی دوباره کلید را پاک نکند
        if (! empty($data['sms_api_key'])) {
            $put('sms_api_key', $data['sms_api_key']);
        }

        return back()->with('flash', 'تنظیماتِ درگاهِ پیامک ذخیره شد ✅');
    }

    /** دسترسی و سهمیه‌ی یک مدرسه. */
    public function updateSchool(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            'sms_enabled' => ['boolean'],
            'sms_quota'   => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sms_sender'  => ['nullable', 'string', 'max:40'],
        ]);

        $school->update([
            'sms_enabled' => $request->boolean('sms_enabled'),
            'sms_quota'   => $data['sms_quota'] ?? null,
            'sms_sender'  => $data['sms_sender'] ?: null,
        ]);

        return back()->with('flash', "دسترسیِ پیامکِ «{$school->name}» به‌روز شد ✅");
    }

    /** ارسالِ پیامکِ آزمایشی به یک شماره. */
    public function test(Request $request, SmsService $sms): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $res = $sms->test($request->string('phone'));

        return back()->with('flash', ['type' => $res['ok'] ? 'ok' : 'error', 'message' => $res['message']]);
    }
}
