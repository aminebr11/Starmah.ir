<?php

namespace App\Support;

use App\Models\OnlineRoom;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * دسترسی و تنظیماتِ «کلاسِ آنلاین» — سه لایه:
 *   ادمینِ کل   → کلاسِ آنلاین را برای هر مدرسه روشن/خاموش می‌کند و سقفِ نفرات را می‌گذارد؛
 *                 حالتِ رسانه (داخلی یا سرورِ LiveKit) و سرورهای اتصال را تنظیم می‌کند.
 *   مدیرِ مدرسه → به هر معلم اجازه‌ی برگزاری می‌دهد، ناظر تعیین می‌کند و هر کلاسی را
 *                 می‌بیند، واردش می‌شود یا می‌بندد.
 *   معلم/دانش‌آموز → معلمِ مجاز کلاس می‌سازد؛ دانش‌آموزانِ همان کلاس وارد می‌شوند.
 */
class OnlineAccess
{
    /** @var array<int,array|null> */
    private static array $schools = [];

    public static function ready(): bool
    {
        return OnlineRoom::ready() && DbSchema::hasTable('online_schools') && DbSchema::hasTable('online_hosts');
    }

    /** ردیفِ دسترسیِ مدرسه (یا null). */
    public static function school(?int $schoolId): ?array
    {
        if (! $schoolId || ! self::ready()) {
            return null;
        }
        if (! array_key_exists($schoolId, self::$schools)) {
            $row = DB::table('online_schools')->where('school_id', $schoolId)->first();
            self::$schools[$schoolId] = $row ? ['enabled' => (bool) $row->enabled, 'max' => (int) $row->max_participants] : null;
        }

        return self::$schools[$schoolId];
    }

    public static function forget(): void
    {
        self::$schools = [];
    }

    public static function schoolEnabled(?int $schoolId): bool
    {
        return (bool) (self::school($schoolId)['enabled'] ?? false);
    }

    public static function maxParticipants(?int $schoolId): int
    {
        return (int) (self::school($schoolId)['max'] ?? self::defaultMax());
    }

    private static function hostRow(User $user): ?object
    {
        return self::ready() ? DB::table('online_hosts')->where('user_id', $user->id)->first() : null;
    }

    /** معلمی که مدیرِ مدرسه‌اش اجازه‌ی برگزاری داده. */
    public static function canHost(?User $user): bool
    {
        if (! $user || ! $user->hasRole(Roles::TEACHER) || ! self::schoolEnabled($user->school_id)) {
            return false;
        }

        return (bool) (self::hostRow($user)?->can_host ?? false);
    }

    /** مدیرِ مدرسه، ناظرِ تعیین‌شده یا ادمینِ کل. */
    public static function canSupervise(?User $user, ?OnlineRoom $room = null): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }
        $school = $room?->school_id ?? $user->school_id;
        if ((int) $user->school_id !== (int) $school || ! self::schoolEnabled($school)) {
            return false;
        }
        if ($user->hasRole(Roles::SCHOOL_ADMIN)) {
            return true;
        }

        return (bool) (self::hostRow($user)?->supervisor ?? false);
    }

    /**
     * نقشِ این کاربر در این کلاس: host | observer | student، یا null یعنی راه ندارد.
     */
    public static function roleIn(User $user, OnlineRoom $room): ?string
    {
        if ((int) $room->teacher_id === (int) $user->id) {
            return 'host';
        }
        if (self::canSupervise($user, $room)) {
            return 'observer';
        }
        if ($user->isStudent() && (int) $user->school_id === (int) $room->school_id && self::schoolEnabled($room->school_id)
            && in_array((int) $user->id, $room->audienceIds(), true)) {
            return 'student';
        }

        return null;
    }

    /** آیا منوی «کلاسِ آنلاین» برای این کاربر نشان داده شود؟ */
    public static function menuFor(?User $user): bool
    {
        if (! $user || ! self::ready()) {
            return false;
        }
        if ($user->hasRole(Roles::SUPER_ADMIN)) {
            return true;
        }

        return self::schoolEnabled($user->school_id)
            && ($user->isStudent() || self::canHost($user) || self::canSupervise($user));
    }

    // ── تنظیماتِ سراسری (ادمینِ کل) ────────────────────────────────────

    public static function defaultMax(): int
    {
        return max(2, (int) Setting::get('online_default_max', 60));
    }

    /** builtin (بدونِ سرورِ جدا) یا livekit (سرورِ رسانه‌ی جدا). */
    public static function media(): string
    {
        return Setting::get('online_media', 'builtin') === 'livekit' && self::livekit() ? 'livekit' : 'builtin';
    }

    /** تنظیماتِ LiveKit اگر کامل باشد. */
    public static function livekit(): ?array
    {
        $url = trim((string) Setting::get('online_livekit_url', ''));
        $key = trim((string) Setting::get('online_livekit_key', ''));
        $secret = self::secret();
        if ($url === '' || $key === '' || $secret === '') {
            return null;
        }

        return ['url' => $url, 'key' => $key, 'secret' => $secret];
    }

    public static function secret(): string
    {
        $v = (string) Setting::get('online_livekit_secret', '');
        if ($v === '') {
            return '';
        }
        try {
            return Crypt::decryptString($v);
        } catch (\Throwable) {
            return $v;   // اگر رمزنگاری نشده ذخیره شده باشد
        }
    }

    /** سقفِ پهنای باندِ ارسالیِ معلم در حالتِ داخلی (کیلوبیت بر ثانیه). */
    public static function budgetKbps(): int
    {
        return max(500, min(20000, (int) Setting::get('online_budget_kbps', 3000)));
    }

    public static function recordingAllowed(): bool
    {
        return (bool) Setting::get('online_recording', true);
    }

    /**
     * سرورهای STUN/TURN برای WebRTC. هر خط: «نشانی» یا «نشانی نام‌کاربری رمز».
     *
     * @return array<int,array{urls:string,username?:string,credential?:string}>
     */
    public static function iceServers(): array
    {
        $raw = (string) Setting::get('online_ice', "stun:stun.l.google.com:19302\nstun:stun.cloudflare.com:3478");
        $out = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $p = preg_split('/\s+/', trim($line));
            if (! $p || $p[0] === '' || ! preg_match('/^(stun|turns?):/i', $p[0])) {
                continue;
            }
            $s = ['urls' => $p[0]];
            if (isset($p[1], $p[2])) {
                $s['username'] = $p[1];
                $s['credential'] = $p[2];
            }
            $out[] = $s;
        }

        return $out;
    }
}
