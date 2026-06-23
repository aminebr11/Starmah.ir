<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * تم پایه‌ی برند + چهار «گروه/تیم» رقابتی (مطابق طرح ارائه‌شده).
 * دانش‌آموز یکی از چهار تیم را انتخاب می‌کند و در کلاسش با بقیه‌ی تیم‌ها رقابت می‌کند.
 * افزودن تیم جدید = افزودن یک ردیف اینجا (یا از پنل ادمین).
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        // تم پایه‌ی برند (هویت پلتفرم)
        Theme::updateOrCreate(['key' => 'brand'], [
            'name' => 'ستاره ماه', 'emoji' => '🌙', 'sort' => 0,
            'skin' => ['bg1' => '#0e1c3d', 'bg2' => '#152a55', 'p1' => '#f5b53f', 'p2' => '#e09b22', 'acc' => '#ffd87a', 'acc2' => '#4a90d9', 'ring' => '#f5b53f', 'mascot' => '🌙', 'hero' => '⭐', 'pattern' => 'stars'],
            'narrative' => ['xp_unit' => 'ستاره', 'xp_label' => 'ستاره‌های این فصل', 'league' => 'کهکشان', 'rank_title' => 'ستاره', 'leaderboard' => 'جدول ستاره‌ها', 'mission_title' => 'تمرین امروز', 'reward_title' => 'آفرین! ⭐', 'subtitle' => 'STARMAH', 'tagline' => ''],
            'content_pools' => ['team' => ['ستاره‌ها'], 'unit' => ['ستاره'], 'hero' => ['کاشف']],
        ]);

        $groups = [
            [
                'key' => 'fire-strikers', 'name' => 'تیم شلیک آتشین', 'subtitle' => 'FIRE STRIKERS',
                'emoji' => '🔥', 'sort' => 1, 'tagline' => 'فوتبال قرمز · قدرت و شور',
                'skin' => ['bg1' => '#2a0c0c', 'bg2' => '#4a1212', 'p1' => '#ff4d3d', 'p2' => '#c81e1e', 'acc' => '#ffb03a', 'acc2' => '#ff7847', 'ring' => '#ff4d3d', 'mascot' => '🔥', 'hero' => '⚽', 'character' => '🦁', 'pattern' => 'pitch',
                    'hdr1' => '#8B0000', 'hdr2' => '#FF4500', 'border' => '#FFD700', 'font' => "'Bebas Neue', sans-serif", 'hover' => '#FFD700', 'sub_en' => 'RED FOOTBALL TEAM', 'nav' => ['HOME', 'ABOUT', 'PLAYERS', 'MATCHES', 'GALLERY', 'CONTACT']],
                'unit' => 'گل', 'league' => 'لیگ آتش',
                'pools' => ['team' => ['قرمزها', 'شیرها'], 'unit' => ['گل'], 'hero' => ['مهاجم', 'کاپیتان']],
            ],
            [
                'key' => 'blue-thunders', 'name' => 'تیم صاعقه آبی', 'subtitle' => 'BLUE THUNDERS',
                'emoji' => '⚡', 'sort' => 2, 'tagline' => 'فوتبال آبی · سرعت و دقت',
                'skin' => ['bg1' => '#07142e', 'bg2' => '#0e2a5e', 'p1' => '#2e8bff', 'p2' => '#1657c8', 'acc' => '#4fd2ff', 'acc2' => '#7cc0ff', 'ring' => '#2e8bff', 'mascot' => '⚡', 'hero' => '⚽', 'character' => '🐉', 'pattern' => 'pitch',
                    'hdr1' => '#001F3F', 'hdr2' => '#0074D9', 'border' => '#00BFFF', 'font' => "'Poppins', sans-serif", 'hover' => '#00BFFF', 'sub_en' => 'BLUE FOOTBALL TEAM', 'nav' => ['HOME', 'ABOUT', 'PLAYERS', 'MATCHES', 'GALLERY', 'CONTACT']],
                'unit' => 'گل', 'league' => 'لیگ صاعقه',
                'pools' => ['team' => ['آبی‌ها', 'اژدها'], 'unit' => ['گل'], 'hero' => ['ستاره', 'گلزن']],
            ],
            [
                'key' => 'creeper-warriors', 'name' => 'تیم جنگجویان کریپر', 'subtitle' => 'CREEPER WARRIORS',
                'emoji' => '🟩', 'sort' => 3, 'tagline' => 'دنیای ماینکرفت · ساخت و ساز',
                'skin' => ['bg1' => '#0e2a16', 'bg2' => '#143d20', 'p1' => '#5bbf4a', 'p2' => '#2e7d32', 'acc' => '#a5f36a', 'acc2' => '#7be05a', 'ring' => '#5bbf4a', 'mascot' => '🟩', 'hero' => '⛏️', 'character' => '🧟', 'pattern' => 'blocks',
                    'hdr1' => '#1B5E20', 'hdr2' => '#4CAF50', 'border' => '#8BC34A', 'font' => "'Press Start 2P', cursive", 'hover' => '#C6FF00', 'sub_en' => 'MINECRAFT TEAM', 'nav' => ['HOME', 'ABOUT', 'PLAYERS', 'SERVERS', 'GALLERY', 'CONTACT']],
                'unit' => 'زمرد', 'league' => 'لیگ کریپر',
                'pools' => ['team' => ['کریپرها', 'جنگجوها'], 'unit' => ['زمرد', 'بلوک'], 'hero' => ['ماینر', 'سازنده']],
            ],
            [
                'key' => 'super-speed', 'name' => 'تیم هوپر اسپید', 'subtitle' => 'SUPER SPEED',
                'emoji' => '🏎️', 'sort' => 4, 'tagline' => 'ماشین‌ها · سرعت و هیجان',
                'skin' => ['bg1' => '#0a0e1c', 'bg2' => '#15182e', 'p1' => '#ff2e63', 'p2' => '#0b86ff', 'acc' => '#13e2ff', 'acc2' => '#ffd23f', 'ring' => '#13e2ff', 'mascot' => '🏎️', 'hero' => '🏁', 'character' => '🏎️', 'pattern' => 'speed',
                    'hdr1' => '#1E1E1E', 'hdr2' => '#FF6F00', 'border' => '#FFA500', 'font' => "'Rajdhani', sans-serif", 'hover' => '#FFA500', 'sub_en' => 'RACING TEAM', 'nav' => ['HOME', 'ABOUT', 'DRIVERS', 'RACES', 'GALLERY', 'CONTACT']],
                'unit' => 'نیترو', 'league' => 'گرنپری',
                'pools' => ['team' => ['اسپیدرها'], 'unit' => ['نیترو', 'دور'], 'hero' => ['راننده', 'قهرمان پیست']],
            ],
        ];

        foreach ($groups as $g) {
            $g['skin']['subtitle'] = $g['subtitle'];
            Theme::updateOrCreate(['key' => $g['key']], [
                'name'  => $g['name'],
                'emoji' => $g['emoji'],
                'sort'  => $g['sort'],
                'skin'  => $g['skin'],
                'narrative' => [
                    'xp_unit' => $g['unit'], 'xp_label' => $g['unit'] . '‌های این فصل',
                    'level' => 'مرحله', 'league' => $g['league'], 'rank_title' => 'قهرمان',
                    'next_tier' => 'تا مرحله‌ی بعد', 'mission_title' => 'مأموریت امروز',
                    'play_label' => 'مسابقه', 'leaderboard' => 'جدول رقابت', 'streak' => 'زنجیره',
                    'reward_title' => 'آفرین! ' . $g['emoji'],
                    'subtitle' => $g['subtitle'], 'tagline' => $g['tagline'],
                ],
                'content_pools' => $g['pools'],
            ]);
        }
    }
}
