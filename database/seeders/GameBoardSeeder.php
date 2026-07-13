<?php

namespace Database\Seeders;

use App\Models\GameTemplate;
use Illuminate\Database\Seeder;

/** محیط‌های بازیِ آمادهٔ زیبا (تختهٔ HTML/CSS با انیمیشن) — قابل انتخاب توسط معلم. */
class GameBoardSeeder extends Seeder
{
    public function run(): void
    {
        $boards = [
            [
                'key' => 'board-neon', 'name' => 'نوار نئونی', 'icon' => '💠', 'sort' => 20,
                'description' => 'نوار پیشرفتِ درخشانِ نئونی — مدرن و پرانرژی.',
                'board_html' => '<div class="nz"><div class="nz-track"><div class="nz-fill" style="width:{{percent}}%"></div><span class="nz-char">{{char}}</span></div><div class="nz-info"><span>پیشرفت {{pos}}/{{total}}</span><span class="nz-score">⚡ {{score}}</span></div></div>',
                'board_css' => '.nz{padding:6px 4px}.nz-track{position:relative;height:30px;border-radius:20px;background:#0b1024;box-shadow:inset 0 0 12px #000;overflow:hidden}.nz-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#22d3ee,#a855f7,#ec4899);box-shadow:0 0 16px #a855f7;transition:width .7s cubic-bezier(.2,.9,.3,1.2)}.nz-char{position:absolute;top:50%;inset-inline-end:8px;transform:translateY(-50%);font-size:20px;filter:drop-shadow(0 0 6px #fff)}.nz-info{display:flex;justify-content:space-between;margin-top:8px;font-weight:800;font-size:13px;color:#e5e7ff}.nz-score{color:#fbbf24}',
            ],
            [
                'key' => 'board-mountain', 'name' => 'صعود به قله', 'icon' => '🏔️', 'sort' => 21,
                'description' => 'کوهنوردی تا پرچمِ قله؛ با هر پاسخ درست بالاتر می‌روی.',
                'board_html' => '<div class="mt"><div class="mt-scene"><div class="mt-peak">🚩</div><div class="mt-climber" style="bottom:calc({{percent}}% - 6px);inset-inline-start:calc({{percent}}% - 12px)">{{char}}</div></div><p class="mt-t">⛰️ ارتفاع {{pos}} از {{total}} — امتیاز {{score}}</p></div>',
                'board_css' => '.mt-scene{position:relative;height:120px;border-radius:14px;overflow:hidden;background:linear-gradient(#bae6fd,#e0f2fe);clip-path:polygon(0 100%,50% 8%,100% 100%)}.mt-scene::after{content:"";position:absolute;inset:0;background:linear-gradient(135deg,#94a3b8,#475569);opacity:.25;clip-path:polygon(0 100%,50% 8%,100% 100%)}.mt-peak{position:absolute;top:2px;inset-inline-start:50%;transform:translateX(-50%);font-size:22px}.mt-climber{position:absolute;font-size:22px;transition:all .8s cubic-bezier(.3,1,.4,1);filter:drop-shadow(0 3px 4px rgba(0,0,0,.4))}.mt-t{text-align:center;font-weight:800;font-size:13px;margin-top:8px;color:#fff}',
            ],
            [
                'key' => 'board-rocket', 'name' => 'موشک تا ماه', 'icon' => '🚀', 'sort' => 22,
                'description' => 'موشک با سوختِ امتیاز تا ماه بالا می‌رود؛ ستاره‌ها چشمک می‌زنند.',
                'board_html' => '<div class="rk"><div class="rk-sky"><span class="rk-moon">🌙</span><span class="rk-star s1">✦</span><span class="rk-star s2">✦</span><span class="rk-star s3">✦</span><span class="rk-ship" style="bottom:calc({{percent}}% - 10px)">{{char}}🚀</span></div><p class="rk-t">🌌 {{pos}}/{{total}} — سوخت امتیاز: {{score}}</p></div>',
                'board_css' => '.rk-sky{position:relative;height:130px;border-radius:14px;overflow:hidden;background:radial-gradient(120% 90% at 50% 100%,#1e293b,#020617)}.rk-moon{position:absolute;top:8px;inset-inline-start:50%;transform:translateX(-50%);font-size:26px;filter:drop-shadow(0 0 10px #fde68a)}.rk-star{position:absolute;color:#e2e8f0;font-size:11px;animation:rk-tw 1.6s ease-in-out infinite}.rk-star.s1{top:24px;inset-inline-start:22%}.rk-star.s2{top:50px;inset-inline-end:26%;animation-delay:.5s}.rk-star.s3{top:74px;inset-inline-start:36%;animation-delay:1s}@keyframes rk-tw{0%,100%{opacity:.3}50%{opacity:1}}.rk-ship{position:absolute;inset-inline-start:50%;transform:translateX(-50%);font-size:22px;transition:bottom .8s cubic-bezier(.3,1,.4,1)}.rk-t{text-align:center;font-weight:800;font-size:13px;margin-top:8px;color:#fff}',
            ],
            [
                'key' => 'board-stars', 'name' => 'مسیر ستاره‌ای', 'icon' => '⭐', 'sort' => 23,
                'description' => 'در آسمانِ پرستاره ستاره جمع کن تا به انتها برسی.',
                'board_html' => '<div class="sp"><div class="sp-row"><i class="sp-dot" style="--w:{{percent}}"></i><span class="sp-char" style="inset-inline-start:calc({{percent}}% - 12px)">{{char}}</span></div><p class="sp-t">⭐ {{pos}} از {{total}} ستاره — {{score}} امتیاز</p></div>',
                'board_css' => ".sp-row{position:relative;height:26px;border-radius:20px;background:#1e1b4b;overflow:hidden}.sp-dot{position:absolute;inset-inline-start:0;top:0;height:100%;width:calc(var(--w)*1%);background:linear-gradient(90deg,#facc15,#f59e0b);border-radius:20px;transition:width .7s}.sp-char{position:absolute;top:50%;transform:translateY(-50%);font-size:20px;transition:inset-inline-start .7s}.sp-t{text-align:center;font-weight:800;font-size:13px;margin-top:8px;color:#fff}",
            ],
        ];

        foreach ($boards as $b) {
            GameTemplate::updateOrCreate(['key' => $b['key']], array_merge($b, ['is_active' => true, 'config' => ['cells' => 20]]));
        }
    }
}
