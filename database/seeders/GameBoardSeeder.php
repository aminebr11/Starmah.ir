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
                'key' => 'board-snake', 'name' => 'مارپلهٔ دانایی', 'icon' => '🎲', 'sort' => 10,
                'description' => 'تختهٔ واقعیِ مار و پله با ۳۰ خانه؛ مهرهٔ دانش‌آموز روی مسیرِ مارپیچ راه می‌رود، '
                    . 'ردِّ طلاییِ مسیرِ طی‌شده پشت سرش می‌ماند و ایستگاه‌های ۲۵٪ تا ۱۰۰٪ یکی‌یکی روشن می‌شوند.',
                'board_html' => <<<'SNAKE_HTML'
<div class="sl-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="sl-head">
        <div class="sl-brand">
            <div class="sl-dice">🎲</div>
            <div>
                <h2>مارپلهٔ دانایی</h2>
                <p>هر پاسخِ درست، یک خانه جلوتر — مواظبِ مارها باش!</p>
            </div>
        </div>

        <div class="sl-score">
            <span>⚡</span>
            <div>
                <strong>{{score}}</strong>
                <small>امتیاز</small>
            </div>
        </div>
    </header>

    <section class="sl-stats">
        <div class="sl-stat">
            <span>📍</span>
            <div><small>خانهٔ فعلی</small><b>{{pos}}</b></div>
        </div>
        <div class="sl-stat">
            <span>🏁</span>
            <div><small>تا خطِ پایان</small><b>{{total}}</b></div>
        </div>
        <div class="sl-stat">
            <span>📈</span>
            <div><small>پیشرفت</small><b>{{percent}}٪</b></div>
        </div>
    </section>

    <div class="sl-stage">
      <div class="sl-board">

        <!-- ۳۰ خانه، چیدمانِ مارپیچ: ردیفِ پایین ۱ تا ۶ و بعد برعکس -->
        <div class="sl-grid">
            <i class="sl-cell" data-n="۲۵"></i><i class="sl-cell sl-snake-head" data-n="۲۶"></i>
            <i class="sl-cell" data-n="۲۷"></i><i class="sl-cell" data-n="۲۸"></i>
            <i class="sl-cell sl-ladder-top" data-n="۲۹"></i><i class="sl-cell sl-finish" data-n="۳۰"><u class="sl-flag">🏆</u></i>

            <i class="sl-cell" data-n="۲۴"></i><i class="sl-cell" data-n="۲۳"></i>
            <i class="sl-cell" data-n="۲۲"></i><i class="sl-cell" data-n="۲۱"></i>
            <i class="sl-cell sl-ladder-foot" data-n="۲۰"></i><i class="sl-cell" data-n="۱۹"></i>

            <i class="sl-cell sl-snake-tail" data-n="۱۳"></i><i class="sl-cell" data-n="۱۴"></i>
            <i class="sl-cell sl-ladder-top" data-n="۱۵"></i><i class="sl-cell" data-n="۱۶"></i>
            <i class="sl-cell" data-n="۱۷"></i><i class="sl-cell sl-snake-head" data-n="۱۸"></i>

            <i class="sl-cell" data-n="۱۲"></i><i class="sl-cell" data-n="۱۱"></i>
            <i class="sl-cell" data-n="۱۰"></i><i class="sl-cell sl-snake-tail" data-n="۹"></i>
            <i class="sl-cell" data-n="۸"></i><i class="sl-cell" data-n="۷"></i>

            <i class="sl-cell sl-start" data-n="۱"><u class="sl-flag">🚩</u></i><i class="sl-cell" data-n="۲"></i>
            <i class="sl-cell sl-ladder-foot" data-n="۳"></i><i class="sl-cell" data-n="۴"></i>
            <i class="sl-cell" data-n="۵"></i><i class="sl-cell" data-n="۶"></i>
        </div>

        <!-- مارها و نردبان‌ها، دقیقاً روی مرکزِ خانه‌ها -->
        <svg class="sl-art" viewBox="0 0 620 416" aria-hidden="true">
            <defs>
                <linearGradient id="slWood" gradientUnits="userSpaceOnUse" x1="0" y1="416" x2="0" y2="0">
                    <stop offset="0" stop-color="#d97706"/><stop offset=".5" stop-color="#fbbf24"/><stop offset="1" stop-color="#fde68a"/>
                </linearGradient>
                <linearGradient id="slSnakeA" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#34d399"/><stop offset="1" stop-color="#047857"/>
                </linearGradient>
                <linearGradient id="slSnakeB" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#c084fc"/><stop offset="1" stop-color="#6d28d9"/>
                </linearGradient>
            </defs>

            <!-- نردبان ۳ ← ۱۵ -->
            <g class="sl-lad">
                <line x1="349" y1="364" x2="349" y2="208"/><line x1="370" y1="364" x2="370" y2="208"/>
                <line x1="349" y1="338" x2="370" y2="338"/><line x1="349" y1="312" x2="370" y2="312"/>
                <line x1="349" y1="286" x2="370" y2="286"/><line x1="349" y1="260" x2="370" y2="260"/>
                <line x1="349" y1="234" x2="370" y2="234"/>
            </g>
            <!-- نردبان ۲۰ ← ۲۹ -->
            <g class="sl-lad">
                <line x1="151" y1="130" x2="151" y2="52"/><line x1="172" y1="130" x2="172" y2="52"/>
                <line x1="151" y1="110" x2="172" y2="110"/><line x1="151" y1="91" x2="172" y2="91"/>
                <line x1="151" y1="71" x2="172" y2="71"/>
            </g>

            <!-- مارِ سبز: سرِ ۲۶ ← دمِ ۱۳ -->
            <path class="sl-snake" stroke="url(#slSnakeA)"
                  d="M458.5 62 C 500 110, 430 140, 470 178 C 505 210, 540 198, 557.5 200"/>
            <!-- مارِ بنفش: سرِ ۱۸ ← دمِ ۹ -->
            <path class="sl-snake" stroke="url(#slSnakeB)"
                  d="M62.5 218 C 110 250, 160 232, 200 268 C 225 290, 245 288, 260.5 286"/>

            <!-- ردِّ پایی که کودک تا اینجا رفته -->
            <path class="sl-trail"
                  d="M557.5 364 H62.5 V286 H557.5 V208 H62.5 V130 H557.5 V52 H62.5"/>
        </svg>

        <!-- شخصیت‌های تخته -->
        <span class="sl-actor sl-monkey">🐒</span>
        <span class="sl-actor sl-owl">🦉</span>
        <span class="sl-actor sl-snakeface-a">🐍</span>
        <span class="sl-actor sl-snakeface-b">🐍</span>

        <!-- مهرهٔ کودک — روی مسیرِ مارپیچ حرکت می‌کند -->
        <div class="sl-token">
            <div class="sl-token-in">
                <span class="sl-avatar">{{char}}</span>
                <span class="sl-shadow"></span>
            </div>
        </div>

        <!-- ایستگاه‌هایی که با عبورِ کودک روشن می‌شوند -->
        <div class="sl-checks">
            <span class="sl-check c25">⭐</span>
            <span class="sl-check c50">⭐</span>
            <span class="sl-check c75">⭐</span>
            <span class="sl-check c100">👑</span>
        </div>

      </div>
    </div>

    <div class="sl-cheer">
        <span>🎙️</span><b>آفرین! مهره‌ات یک خانه جلو رفت — تا خانهٔ آخر ادامه بده.</b>
    </div>

    <section class="sl-progress">
        <div class="sl-progress-info">
            <span>مسیر تا خانهٔ آخر</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="sl-track">
            <span class="sl-fill"></span>
            <div class="sl-runner">{{char}}</div>
        </div>
        <div class="sl-progress-foot">
            <span>🪜 نردبان یعنی جهشِ رو به جلو · 🐍 مار یعنی یک بار دیگر تمرین کن</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
SNAKE_HTML,
                'board_css' => <<<'SNAKE_CSS'
.sl-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#7c3aed,#312e81 55%,#140b2e);border:1px solid #ffffff33;border-radius:30px;box-shadow:0 28px 70px #10062a73}
.sl-game *,.sl-game *:before,.sl-game *:after{box-sizing:border-box}

.sl-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.sl-brand{display:flex;align-items:center;gap:12px}
.sl-dice{display:grid;place-items:center;width:58px;height:58px;font-size:31px;background:linear-gradient(145deg,#fff,#ddd6fe);border:3px solid #fbbf24;border-radius:18px;box-shadow:0 8px 20px #0005;animation:sl-roll 3.4s ease-in-out infinite}
.sl-brand h2{margin:0;color:#fff;font-size:clamp(19px,3vw,28px)}
.sl-brand p{margin:4px 0 0;color:#ddd6fe;font-size:12px}
.sl-score{display:flex;align-items:center;gap:9px;min-width:120px;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.sl-score span{font-size:25px}
.sl-score strong,.sl-score small{display:block}
.sl-score strong{color:#fde047;font-size:22px}
.sl-score small{margin-top:3px;color:#ddd6fe;font-size:9px}

.sl-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.sl-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.sl-stat>span{font-size:21px}
.sl-stat small,.sl-stat b{display:block}
.sl-stat small{color:#cfc6f5;font-size:9px}
.sl-stat b{margin-top:3px;color:#fff;font-size:17px}

/* ── صحنه: تخته اندازهٔ ثابت دارد تا مختصاتِ مسیر دقیق بماند و با
   مقیاس برای نمایشگرِ کوچک‌تر کوچک می‌شود ── */
.sl-stage{--s:1;width:calc(620px*var(--s));height:calc(416px*var(--s));margin-inline:auto}
.sl-board{position:relative;width:620px;height:416px;transform:scale(var(--s));transform-origin:top right;background:linear-gradient(160deg,#1e1b4b,#0b1026 60%,#1b1038);border-radius:26px;box-shadow:inset 0 0 0 4px #ffffffd0,inset 0 0 40px #4c1d95aa,0 18px 38px #0007;overflow:hidden}
.sl-board:before{position:absolute;inset:0;content:"";opacity:.4;background:radial-gradient(1.6px 1.6px at 12% 18%,#fff,transparent 60%),radial-gradient(1.4px 1.4px at 78% 30%,#fde68a,transparent 60%),radial-gradient(1.8px 1.8px at 44% 72%,#fff,transparent 60%),radial-gradient(1.3px 1.3px at 88% 82%,#a5b4fc,transparent 60%)}

.sl-grid{position:absolute;inset:16px;display:grid;grid-template-columns:repeat(6,1fr);grid-template-rows:repeat(5,1fr);gap:6px}
.sl-cell{position:relative;display:grid;place-items:center;border-radius:12px;font-style:normal;background:#ffffff12;border:1px solid #ffffff24;box-shadow:inset 0 -3px 0 #00000030}
.sl-cell:nth-child(odd){background:#ffffff1c}
.sl-cell:after{content:attr(data-n);position:absolute;top:4px;inset-inline-end:7px;color:#c7bdf0;font-size:10px;font-weight:900}
.sl-start{background:linear-gradient(145deg,#22c55e55,#16653455);border-color:#4ade8088}
.sl-finish{background:linear-gradient(145deg,#fbbf2455,#b4530955);border-color:#fcd34d99}
.sl-ladder-foot,.sl-ladder-top{background:linear-gradient(145deg,#f59e0b40,#78350f40);border-color:#fbbf2480}
.sl-snake-head,.sl-snake-tail{background:linear-gradient(145deg,#34d39940,#064e3b40);border-color:#6ee7b780}
.sl-flag{position:absolute;top:3px;inset-inline-start:5px;font-style:normal;font-size:23px;filter:drop-shadow(0 3px 3px #0008)}

.sl-art{position:absolute;inset:0;width:620px;height:416px;pointer-events:none}
.sl-lad line{stroke:url(#slWood);stroke-width:5;stroke-linecap:round;filter:drop-shadow(0 2px 2px #0007)}
.sl-snake{fill:none;stroke-width:11;stroke-linecap:round;opacity:.92;filter:drop-shadow(0 3px 3px #0007)}
.sl-trail{fill:none;stroke:#fde047;stroke-width:6;stroke-linecap:round;stroke-linejoin:round;opacity:.85;filter:drop-shadow(0 0 8px #fbbf24);stroke-dasharray:2787;animation:sl-trail .85s cubic-bezier(.3,.9,.3,1) both}

.sl-actor{position:absolute;font-size:23px;filter:drop-shadow(0 3px 3px #0008)}
.sl-monkey{top:252px;inset-inline-start:calc(620px - 380px);animation:sl-climb 2.6s ease-in-out infinite}
.sl-owl{top:26px;inset-inline-start:calc(620px - 146px);animation:sl-blink 4s ease-in-out infinite}
.sl-snakeface-a{top:44px;inset-inline-start:calc(620px - 476px)}
.sl-snakeface-b{top:198px;inset-inline-start:calc(620px - 82px);transform:scaleX(-1)}

/* ── مهرهٔ کودک روی مسیرِ مارپیچ ── */
.sl-token{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.sl-token-in{position:absolute;left:-27px;top:-27px;width:54px;height:54px;display:grid;place-items:center;animation:sl-hop .85s cubic-bezier(.3,.9,.3,1) both}
.sl-avatar{display:grid;place-items:center;width:52px;height:52px;font-size:29px;background:radial-gradient(circle at 35% 25%,#fff,#e9d5ff);border:4px solid #fbbf24;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 0 5px #fbbf2440,0 0 22px #fbbf2470}
.sl-shadow{position:absolute;bottom:-9px;width:34px;height:8px;background:#00000066;border-radius:50%;filter:blur(2px)}

@supports (offset-path: path("M0 0")){
  .sl-token{offset-path:path("M557.5 364 H62.5 V286 H557.5 V208 H62.5 V130 H557.5 V52 H62.5");offset-rotate:0deg;animation:sl-move .85s cubic-bezier(.3,.9,.3,1) both}
}
@supports not (offset-path: path("M0 0")){
  /* مرورگرِ قدیمی: مهره روی همان ردیفِ اولِ تخته می‌ماند و نوارِ پایین مسیر را نشان می‌دهد */
  .sl-token{top:364px;inset-inline-start:calc(62.5px + (557.5px - 62.5px) * (100 - var(--p)) / 100)}
}

.sl-checks{position:absolute;inset:0;pointer-events:none}
.sl-check{position:absolute;font-size:19px;filter:drop-shadow(0 0 7px #fde047)}
.sl-check.c25{top:276px;inset-inline-start:calc(620px - 196px);opacity:clamp(0,calc((var(--p) - 25)*99),1)}
.sl-check.c50{top:198px;inset-inline-start:calc(620px - 320px);opacity:clamp(0,calc((var(--p) - 50)*99),1)}
.sl-check.c75{top:120px;inset-inline-start:calc(620px - 444px);opacity:clamp(0,calc((var(--p) - 75)*99),1)}
.sl-check.c100{top:11px;inset-inline-start:calc(620px - 76px);z-index:10;font-size:23px;opacity:clamp(0,calc((var(--p) - 99)*99),1);animation:sl-crown 1.4s ease-in-out infinite}

.sl-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#ede9fe;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700}
.sl-cheer span{font-size:17px}

.sl-progress{margin-top:14px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.sl-progress-info{display:flex;justify-content:space-between;margin-bottom:8px;color:#e9e3ff;font-size:11px}
.sl-progress-info b{color:#fde047}
.sl-track{position:relative;height:14px;background:#0a061780;border:2px solid #ffffff38;border-radius:99px}
.sl-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#22c55e,#fbbf24,#f97316);border-radius:inherit;box-shadow:0 0 15px #fbbf2480;animation:sl-fill .85s cubic-bezier(.3,.9,.3,1) both}
.sl-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #fbbf24;border-radius:50%;box-shadow:0 6px 12px #0007;animation:sl-run .85s cubic-bezier(.3,.9,.3,1) both,sl-bounce 1.6s 1s infinite}
.sl-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#b6a9e4;font-size:9px}
.sl-progress-foot b{color:#86efac;font-size:11px}

@keyframes sl-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes sl-trail{from{stroke-dashoffset:calc(2787px - var(--from)*27.87px)}to{stroke-dashoffset:calc(2787px - var(--p)*27.87px)}}
@keyframes sl-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes sl-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes sl-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-20px) scale(1.12)}65%{transform:translateY(-6px) scale(.96)}100%{transform:translateY(0) scale(1)}}
@keyframes sl-say{to{opacity:clamp(0,calc(var(--p)*99),1);transform:translateY(-4px)}}
@keyframes sl-roll{0%,72%{transform:rotate(-5deg)}80%{transform:rotate(12deg) scale(1.08)}88%{transform:rotate(-10deg)}100%{transform:rotate(-5deg)}}
@keyframes sl-climb{50%{transform:translateY(-16px)}}
@keyframes sl-blink{48%{transform:scale(1)}52%{transform:scale(.88)}}
@keyframes sl-crown{50%{transform:translateY(-5px) scale(1.12)}}
@keyframes sl-bounce{50%{transform:translateY(-5px)}}

@media(max-width:1000px){.sl-stage{--s:.84}}
@media(max-width:820px){.sl-game{padding:12px;border-radius:22px}.sl-stage{--s:.68}.sl-brand h2{font-size:18px}.sl-brand p{font-size:9px}.sl-dice{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.sl-stage{--s:.6}.sl-score{min-width:80px;padding:7px 10px}.sl-score>span{display:none}.sl-stat{display:block;padding:7px 2px;text-align:center}.sl-stat>span{font-size:16px}.sl-stat small{font-size:7px}.sl-stat b{font-size:14px}}
@media(max-width:560px){.sl-stage{--s:.52}.sl-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.sl-stage{--s:.46}}
@media(max-width:410px){.sl-stage{--s:.4}}
@media(max-width:350px){.sl-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.sl-game *{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}.sl-token{offset-distance:calc(var(--p)*1%)}.sl-trail{stroke-dashoffset:calc(2787px - var(--p)*27.87px)}}
SNAKE_CSS,
            ],
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
