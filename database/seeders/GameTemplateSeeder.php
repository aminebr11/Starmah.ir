<?php

namespace Database\Seeders;

use App\Models\GameTemplate;
use Illuminate\Database\Seeder;

/** قالب‌های بازی (مکانیک‌ها) — سه قالب فعال + پنج قالبِ به‌زودی. */
class GameTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['key' => 'snake', 'name' => 'مار و پله آموزشی', 'icon' => '🎲', 'is_active' => true, 'sort' => 1,
                'description' => 'با پاسخِ درست تاس بینداز و مهره را جلو ببر؛ خانه‌های ویژه پاداش و جریمه دارند.',
                'config' => ['cells' => 30, 'dice' => 6]],
            ['key' => 'football', 'name' => 'مسابقه فوتبال آموزشی', 'icon' => '⚽', 'is_active' => true, 'sort' => 2,
                'description' => 'هر پاسخِ درست یک حمله‌ی موفق و شانس گل؛ تیمت را به قهرمانی برسان.',
                'config' => ['attacks' => 5]],
            ['key' => 'treasure', 'name' => 'گنج‌یابی آموزشی', 'icon' => '🗺️', 'is_active' => true, 'sort' => 3,
                'description' => 'با حل معماها صندوق‌ها را باز کن و به گنج پایانی برس.',
                'config' => ['chests' => 5]],
            // به‌زودی
            ['key' => 'space', 'name' => 'سفر فضایی', 'icon' => '🚀', 'is_active' => false, 'sort' => 4, 'description' => 'به‌زودی'],
            ['key' => 'detective', 'name' => 'کارآگاه کوچک', 'icon' => '🕵️', 'is_active' => false, 'sort' => 5, 'description' => 'به‌زودی'],
            ['key' => 'memory', 'name' => 'کارت حافظه و تطبیق', 'icon' => '🧠', 'is_active' => false, 'sort' => 6, 'description' => 'به‌زودی'],
            ['key' => 'wheel', 'name' => 'گردونه دانش', 'icon' => '🎡', 'is_active' => false, 'sort' => 7, 'description' => 'به‌زودی'],
            ['key' => 'speed', 'name' => 'مسابقه سرعتی', 'icon' => '⚡', 'is_active' => false, 'sort' => 8, 'description' => 'به‌زودی'],
        ];

        foreach ($templates as $t) {
            GameTemplate::updateOrCreate(['key' => $t['key']], $t);
        }
    }
}
