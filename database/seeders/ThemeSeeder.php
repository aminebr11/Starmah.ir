<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * دو دنیای راه‌اندازی: فوتبال و ماشین.
 * توکن‌های skin از دموی طراحی تأییدشده گرفته شده‌اند تا فرانت دقیقاً همان ظاهر را بسازد.
 * افزودن دنیای جدید در آینده = افزودن یک ردیف اینجا.
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        // تم پایه‌ی برند (هویت پلتفرم: سرمه‌ای + طلایی). تم پیش‌فرض پیش از انتخاب دنیا.
        Theme::updateOrCreate(['key' => 'brand'], [
            'name'  => 'ستاره ماه',
            'emoji' => '🌙',
            'sort'  => 0,
            'skin'  => [
                'bg1' => '#0e1c3d', 'bg2' => '#152a55',
                'p1' => '#f5b53f', 'p2' => '#e09b22',
                'acc' => '#ffd87a', 'acc2' => '#4a90d9',
                'ring' => '#f5b53f', 'mascot' => '🌙', 'hero' => '⭐',
                'pattern' => 'stars',
            ],
            'narrative' => [
                'xp_unit'       => 'ستاره',
                'xp_label'      => 'ستاره‌های این فصل',
                'level'         => 'مرحله',
                'league'        => 'کهکشان ستاره‌ها',
                'rank_title'    => 'ستاره',
                'next_tier'     => 'تا ستاره‌ی بعدی',
                'mission_title' => 'تمرین امروز',
                'play_label'    => 'تمرین',
                'leaderboard'   => 'جدول ستاره‌ها',
                'streak'        => 'زنجیره',
                'reward_title'  => 'آفرین! ⭐',
            ],
            'content_pools' => [
                'team' => ['ستاره‌ها', 'کهکشان'],
                'unit' => ['ستاره', 'امتیاز'],
                'hero' => ['ستاره‌شناس', 'کاشف'],
            ],
        ]);

        Theme::updateOrCreate(['key' => 'football'], [
            'name'  => 'فوتبال',
            'emoji' => '⚽',
            'sort'  => 1,
            'skin'  => [
                'bg1' => '#0a2a18', 'bg2' => '#0e3a22',
                'p1' => '#1fd968', 'p2' => '#0f9d4f',
                'acc' => '#ffd23f', 'acc2' => '#ff3b3b',
                'ring' => '#1fd968', 'mascot' => '⚽', 'hero' => '🏟️',
                'pattern' => 'pitch',
            ],
            'narrative' => [
                'xp_unit'       => 'گل',
                'xp_label'      => 'گل‌های این فصل',
                'level'         => 'لیگ',
                'league'        => 'لیگ برتر',
                'rank_title'    => 'قهرمان',
                'next_tier'     => 'تا صعود به لیگ قهرمانان',
                'mission_title' => 'مسابقه‌ی امروز',
                'play_label'    => 'مسابقه',
                'leaderboard'   => 'جدول لیگ',
                'streak'        => 'زنجیره‌ی برد',
                'reward_title'  => 'گل! آفرین 🎉',
            ],
            'content_pools' => [
                'team' => ['قرمزها', 'آبی‌ها', 'تیم ملی', 'سبزها'],
                'unit' => ['گل', 'پاس گل'],
                'hero' => ['مهاجم', 'دروازه‌بان', 'کاپیتان'],
            ],
        ]);

        Theme::updateOrCreate(['key' => 'cars'], [
            'name'  => 'ماشین و مسابقه',
            'emoji' => '🏎️',
            'sort'  => 2,
            'skin'  => [
                'bg1' => '#0a0e1c', 'bg2' => '#10182e',
                'p1' => '#13e2ff', 'p2' => '#0b86ff',
                'acc' => '#ff2e63', 'acc2' => '#ffd23f',
                'ring' => '#13e2ff', 'mascot' => '🏎️', 'hero' => '🏁',
                'pattern' => 'speed',
            ],
            'narrative' => [
                'xp_unit'       => 'نیترو',
                'xp_label'      => 'نیتروی این گرنپری',
                'level'         => 'گرنپری',
                'league'        => 'گرنپری طلایی',
                'rank_title'    => 'صدرنشین',
                'next_tier'     => 'تا سکوی قهرمانی 🏁',
                'mission_title' => 'مسابقه‌ی امروز',
                'play_label'    => 'پیست',
                'leaderboard'   => 'گرید مسابقه',
                'streak'        => 'دور سریع',
                'reward_title'  => 'خط پایان! 🏁',
            ],
            'content_pools' => [
                'team' => ['ماشین قرمز', 'ماشین آبی', 'تیم نیترو'],
                'unit' => ['کیلومتر', 'دور'],
                'hero' => ['راننده', 'مکانیک', 'قهرمان پیست'],
            ],
        ]);
    }
}
