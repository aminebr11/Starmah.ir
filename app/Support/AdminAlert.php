<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * پیامکِ هر اعلانِ تازه برای ادمینِ کل.
 *
 * هر چیزی که در زنگوله‌ی ادمینِ کل می‌نشیند (پیام، درخواستِ ثبت‌نامِ مدرسه،
 * پرداخت، اطلاعیه‌ی مدرسه‌ها) به شماره‌ی موبایلِ خودِ او هم پیامک می‌شود.
 * هر نوع را می‌شود در «سامانه‌ی پیامک» خاموش کرد. هرگز خطا پرتاب نمی‌کند.
 */
class AdminAlert
{
    public const TYPES = [
        'message'        => ['label' => '💬 پیامِ تازه', 'hint' => 'هر پیامی که کسی برای ادمینِ کل می‌فرستد'],
        'school_request' => ['label' => '🏫 درخواستِ ثبت‌نامِ مدرسه', 'hint' => 'مدرسه‌ی تازه فرمِ ثبت‌نام را پر کرد'],
        'payment'        => ['label' => '💳 پرداختِ موفق', 'hint' => 'پرداختِ اشتراک از درگاه تأیید شد'],
        'announcement'   => ['label' => '📢 اطلاعیه‌ی مدرسه‌ها', 'hint' => 'اطلاعیه‌ی عمومی که مدیرِ یک مدرسه منتشر می‌کند'],
    ];

    /** @return array<string,bool> */
    public static function settings(): array
    {
        $raw = json_decode((string) Setting::get('admin_sms_alerts', ''), true);
        $raw = is_array($raw) ? $raw : [];
        $out = [];
        foreach (array_keys(self::TYPES) as $k) {
            $out[$k] = (bool) ($raw[$k] ?? true);   // پیش‌فرض: همه روشن
        }

        return $out;
    }

    public static function send(string $type, string $text, ?int $exceptUserId = null): void
    {
        try {
            if (! isset(self::TYPES[$type]) || ! (self::settings()[$type] ?? false) || ! SmsGateway::gatewayReady()) {
                return;
            }
            $admins = User::role(Roles::SUPER_ADMIN)->whereNotNull('phone')->where('phone', '!=', '')
                ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))->get();
            if ($admins->isEmpty()) {
                return;
            }
            $body = Str::limit(trim($text), 300) . "\nستاره ماه";
            SmsGateway::sendMany(null, null, $admins->map(fn ($u) => ['user' => $u, 'phone' => $u->phone])->all(), $body, 'admin_' . $type);
        } catch (\Throwable $e) {
            Log::warning('admin alert sms failed: ' . $e->getMessage());
        }
    }
}
