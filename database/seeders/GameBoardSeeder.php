<?php

namespace Database\Seeders;

use App\Models\GameTemplate;
use Illuminate\Database\Seeder;

/**
 * محیط‌های بازیِ آماده — تختهٔ HTML/CSS که معلم هنگامِ ساختِ بازی انتخاب می‌کند.
 *
 * قراردادِ جای‌گیرها (موتورِ بازی پیش از نمایش جایگزین می‌کند):
 *   {{percent}} → ۰ تا ۱۰۰ با ارقامِ انگلیسی — تنها کلیدی که در calc() کار می‌کند
 *   {{pos}} {{total}} {{score}} → با ارقامِ فارسی؛ فقط به‌عنوانِ متن
 *   {{char}} → شخصیتی که خودِ دانش‌آموز انتخاب کرده
 *
 * چون موتور در هر سؤال کلِ بدنه را از نو می‌سازد، transition اجرا نمی‌شود؛
 * همهٔ حرکت‌ها @keyframes‌اند و از --from (کمی عقب‌تر) شروع می‌شوند.
 */
class GameBoardSeeder extends Seeder
{
    public function run(): void
    {
        $boards = [
            [
                'key' => 'board-snake', 'name' => 'مارپلهٔ دانایی', 'icon' => '🎲', 'sort' => 10,
                'description' => 'تختهٔ واقعیِ مار و پله با ۳۰ خانه؛ مهرهٔ دانش‌آموز روی مسیرِ مارپیچ راه می‌رود، ردِّ طلاییِ مسیرِ طی‌شده پشت سرش می‌ماند و ایستگاه‌های ۲۵٪ تا ۱۰۰٪ یکی‌یکی روشن می‌شوند.',
                'board_html' => <<<'BOARD_HTML'
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
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
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
BOARD_CSS,
            ],
            [
                'key' => 'board-football', 'name' => 'جامِ قهرمانانِ دانایی', 'icon' => '⚽', 'sort' => 11,
                'description' => 'ورزشگاهِ شبانه با نورافکن و تماشاگر؛ مهاجم با هر پاسخِ درست تا دروازه پیش می‌رود و در پاسخِ آخر گل می‌زند.',
                'board_html' => <<<'BOARD_HTML'
<div class="pk-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="pk-head">
        <div class="pk-brand">
            <div class="pk-logo">⚽</div>
            <div>
                <h2>جامِ قهرمانانِ دانایی</h2>
                <p>هر پاسخِ درست، یک قدم تا گل!</p>
            </div>
        </div>
        <div class="pk-score">
            <span>🏆</span>
            <div><strong>{{score}}</strong><small>امتیازِ تیم</small></div>
        </div>
    </header>

    <section class="pk-stats">
        <div class="pk-stat"><span>⚽</span><div><small>قدم‌های تا گل</small><b>{{pos}}</b></div></div>
        <div class="pk-stat"><span>🥅</span><div><small>کلِ حمله‌ها</small><b>{{total}}</b></div></div>
        <div class="pk-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="pk-stage">
      <div class="pk-scene">
        <svg class="pk-art" viewBox="0 0 620 350" aria-hidden="true">
            <defs>
                <linearGradient id="pkTurf" gradientUnits="userSpaceOnUse" x1="0" y1="74" x2="0" y2="350">
                    <stop offset="0" stop-color="#15803d"/><stop offset="1" stop-color="#22c55e"/>
                </linearGradient>
                <linearGradient id="pkTrail" gradientUnits="userSpaceOnUse" x1="560" y1="300" x2="310" y2="120">
                    <stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#f97316"/>
                </linearGradient>
            </defs>

            <!-- چمنِ راه‌راه با پرسپکتیو -->
            <path d="M0 74 H620 V350 H0 Z" fill="url(#pkTurf)"/>
            <g opacity=".14" fill="#fff">
                <path d="M250 74 H310 L360 350 H190 Z"/><path d="M370 74 H430 L530 350 H360 Z"/>
                <path d="M130 74 H190 L20 350 H0 V300 Z"/>
            </g>
            <!-- خطوطِ زمین -->
            <g fill="none" stroke="#ffffffcc" stroke-width="3">
                <path d="M78 350 V96 H542 V350"/>
                <path d="M196 96 V196 H424 V96"/>
                <path d="M254 96 V140 H366 V96"/>
                <path d="M258 196 A62 40 0 0 0 362 196"/>
            </g>
            <circle cx="310" cy="176" r="4" fill="#fff"/>

            <!-- سکو و تماشاگر -->
            <rect x="0" y="0" width="620" height="74" fill="#0f172a"/>
            <g class="pk-fans">
                <g fill="#f8fafc" opacity=".85">
                    <circle cx="30" cy="22" r="5"/><circle cx="70" cy="18" r="5"/><circle cx="110" cy="24" r="5"/>
                    <circle cx="150" cy="17" r="5"/><circle cx="190" cy="23" r="5"/><circle cx="230" cy="18" r="5"/>
                    <circle cx="270" cy="24" r="5"/><circle cx="310" cy="17" r="5"/><circle cx="350" cy="23" r="5"/>
                    <circle cx="390" cy="18" r="5"/><circle cx="430" cy="24" r="5"/><circle cx="470" cy="17" r="5"/>
                    <circle cx="510" cy="23" r="5"/><circle cx="550" cy="18" r="5"/><circle cx="590" cy="24" r="5"/>
                </g>
                <g fill="#fbbf24" opacity=".8">
                    <circle cx="50" cy="48" r="5"/><circle cx="90" cy="52" r="5"/><circle cx="130" cy="46" r="5"/>
                    <circle cx="170" cy="53" r="5"/><circle cx="210" cy="47" r="5"/><circle cx="250" cy="52" r="5"/>
                    <circle cx="290" cy="46" r="5"/><circle cx="330" cy="53" r="5"/><circle cx="370" cy="47" r="5"/>
                    <circle cx="410" cy="52" r="5"/><circle cx="450" cy="46" r="5"/><circle cx="490" cy="53" r="5"/>
                    <circle cx="530" cy="47" r="5"/><circle cx="570" cy="52" r="5"/>
                </g>
            </g>

            <!-- دروازه و تور -->
            <g>
                <rect x="222" y="74" width="176" height="62" fill="#0b1a3a" opacity=".55"/>
                <path d="M222 74 H398 M222 74 V136 M398 74 V136" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
                <g stroke="#ffffff80" stroke-width="1.6">
                    <path d="M234 74V136M250 74V136M266 74V136M282 74V136M298 74V136M314 74V136M330 74V136M346 74V136M362 74V136M378 74V136"/>
                    <path d="M222 88H398M222 102H398M222 116H398M222 130H398"/>
                </g>
            </g>

            <!-- ردِّ دویدنِ بازیکن -->
            <path class="pk-trail" pathLength="100" stroke="url(#pkTrail)" stroke-width="7" stroke-dasharray="100"
                  d="M560 300 C 500 282, 452 236, 400 198 C 360 168, 330 146, 310 128"/>
        </svg>

        <!-- نورافکن‌ها -->
        <span class="pk-light l1">💡</span><span class="pk-light l2">💡</span>

        <!-- دروازه‌بان و مدافع -->
        <span class="pk-keeper">🧤</span>
        <span class="pk-ref">🧑‍⚖️</span>
        <span class="pk-rival">🏃</span>

        <!-- تابلوی نتیجه -->
        <div class="pk-board"><span>میزبان</span><b>{{pos}}</b><i>—</i><b>۰</b><span>مهمان</span></div>

        <!-- توپ و بازیکن -->
        <div class="pk-ball">⚽</div>
        <div class="pk-hero"><div class="pk-hero-in"><span class="pk-avatar">{{char}}</span></div></div>

        <!-- گلِ پایانی -->
        <div class="pk-goal-fx">
            <span class="pk-goalword">گـــــل! 🎉</span>
            <span class="pk-conf c1">★</span><span class="pk-conf c2">●</span><span class="pk-conf c3">★</span>
        </div>
      </div>
    </div>

    <div class="pk-cheer"><span>🎙️</span><b>گزارشگر: توپ را می‌برد جلو… تماشاگرها بلند شدند!</b></div>

    <section class="pk-progress">
        <div class="pk-progress-info">
            <span>مسیر تا دروازهٔ حریف</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="pk-track">
            <span class="pk-fill"></span>
            <div class="pk-runner">{{char}}</div>
        </div>
        <div class="pk-progress-foot">
            <span>🟨 هر پاسخِ غلط یک خطاست · 🥅 پاسخِ آخر یعنی گل</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.pk-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#2563eb,#0f2452 58%,#061021);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.pk-game *,.pk-game *:before,.pk-game *:after{box-sizing:border-box}
.pk-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.pk-brand{display:flex;align-items:center;gap:12px;min-width:0}
.pk-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#bfdbfe);border:3px solid #facc15;border-radius:18px;box-shadow:0 8px 20px #0006;animation:pk-logo 3.6s ease-in-out infinite}
.pk-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.pk-brand p{margin:3px 0 0;color:#bfdbfe;font-size:12px}
.pk-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.pk-score span{font-size:24px}
.pk-score strong,.pk-score small{display:block}
.pk-score strong{color:#facc15;font-size:22px}
.pk-score small{margin-top:3px;color:#bfdbfe;font-size:9px}
.pk-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.pk-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.pk-stat>span{font-size:21px}
.pk-stat small,.pk-stat b{display:block}
.pk-stat small{color:#bfdbfe;font-size:9px}
.pk-stat b{margin-top:3px;font-size:17px}
.pk-stage{--s:1;width:calc(620px*var(--s));height:calc(350px*var(--s));margin-inline:auto}
.pk-scene{position:relative;width:620px;height:350px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:linear-gradient(180deg,#0b1a3a 0 18%,#14532d 18% 21%,#16a34a 21% 100%)}
.pk-art{position:absolute;inset:0;width:620px;height:350px;pointer-events:none}
.pk-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:pk-trail .85s cubic-bezier(.3,.9,.3,1) both}
.pk-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.pk-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:pk-hop .85s cubic-bezier(.3,.9,.3,1) both}
.pk-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#dbeafe);border:4px solid #facc15;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #facc1570}
.pk-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#bfdbfe;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.pk-cheer span{font-size:17px;flex:none}
.pk-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.pk-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#bfdbfe;font-size:11px}
.pk-progress-info b{color:#facc15}
.pk-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.pk-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#22c55e,#facc15,#f97316);border-radius:inherit;box-shadow:0 0 15px #facc1580;animation:pk-fill .85s cubic-bezier(.3,.9,.3,1) both}
.pk-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #facc15;border-radius:50%;box-shadow:0 6px 12px #0007;animation:pk-run .85s cubic-bezier(.3,.9,.3,1) both,pk-bob 1.6s 1s infinite}
.pk-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#8fa8d8;font-size:9px}
.pk-progress-foot b{color:#facc15;font-size:11px}
@keyframes pk-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes pk-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes pk-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes pk-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes pk-bob{50%{transform:translateY(-5px)}}
@keyframes pk-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.pk-fans{animation:pk-fans 2.4s ease-in-out infinite}
.pk-light{position:absolute;top:6px;font-size:22px;filter:drop-shadow(0 0 14px #fff);animation:pk-flick 3s ease-in-out infinite}
.pk-light.l1{inset-inline-start:calc(620px - 66px)}
.pk-light.l2{inset-inline-start:calc(620px - 600px);animation-delay:.6s}
.pk-keeper{position:absolute;top:96px;inset-inline-start:calc(620px - 334px);font-size:34px;filter:drop-shadow(0 4px 3px #0006);animation:pk-keeper 1.9s ease-in-out infinite}
.pk-ref{position:absolute;top:214px;inset-inline-start:calc(620px - 108px);font-size:23px;filter:drop-shadow(0 4px 3px #0006)}
.pk-rival{position:absolute;top:246px;inset-inline-start:calc(620px - 470px);font-size:26px;filter:drop-shadow(0 4px 3px #0006);animation:pk-rival 2.2s ease-in-out infinite;transform:scaleX(-1)}
.pk-board{position:absolute;top:84px;inset-inline-start:calc(620px - 600px);display:flex;align-items:center;gap:6px;padding:6px 11px;background:#020617d9;border:2px solid #facc1580;border-radius:12px;font-size:10px;color:#bfdbfe;box-shadow:0 6px 14px #0007}
.pk-board b{color:#facc15;font-size:16px}
.pk-board i{font-style:normal;color:#64748b}
.pk-ball{position:absolute;top:0;left:0;width:0;height:0;z-index:10;offset-path:path("M560 300 C 500 282, 452 236, 400 198 C 360 168, 330 146, 310 128");offset-rotate:0deg;offset-distance:calc(min(100,calc(var(--p) + 14))*1%);animation:pk-ball .85s cubic-bezier(.3,.9,.3,1) both}
.pk-ball:before{content:"⚽";position:absolute;left:-14px;top:-6px;font-size:27px;filter:drop-shadow(0 5px 3px #0007);animation:pk-spin .9s linear infinite}
.pk-hero{offset-path:path("M560 300 C 500 282, 452 236, 400 198 C 360 168, 330 146, 310 128");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:pk-move .85s cubic-bezier(.3,.9,.3,1) both}
@supports not (offset-path: path("M0 0")){.pk-hero{top:292px;inset-inline-start:calc(60px + (500px - 60px)*(100 - var(--p))/100)}.pk-ball{display:none}}
.pk-goal-fx{position:absolute;inset:0;pointer-events:none;opacity:clamp(0,calc((var(--p) - 99)*99),1)}
.pk-goalword{position:absolute;top:150px;inset-inline-start:calc(620px - 400px);width:180px;text-align:center;font-size:27px;font-weight:900;color:#fff;text-shadow:0 0 18px #facc15,0 4px 0 #b45309;animation:pk-pop 1.1s ease-in-out infinite}
.pk-conf{position:absolute;color:#fde047;font-size:19px;animation:pk-conf 1.5s ease-out infinite}
.pk-conf.c1{top:110px;inset-inline-start:calc(620px - 268px)}
.pk-conf.c2{top:96px;inset-inline-start:calc(620px - 330px);animation-delay:.3s;color:#fff}
.pk-conf.c3{top:118px;inset-inline-start:calc(620px - 390px);animation-delay:.6s}
@keyframes pk-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes pk-ball{from{offset-distance:calc(min(100,calc(var(--from) + 14))*1%)}to{offset-distance:calc(min(100,calc(var(--p) + 14))*1%)}}
@keyframes pk-spin{to{transform:rotate(360deg)}}
@keyframes pk-fans{50%{transform:translateY(-4px)}}
@keyframes pk-flick{0%,100%{opacity:1}45%{opacity:.55}}
@keyframes pk-keeper{0%,100%{transform:translateX(0)}50%{transform:translateX(13px) rotate(9deg)}}
@keyframes pk-rival{50%{transform:scaleX(-1) translateY(-6px)}}
@keyframes pk-pop{50%{transform:scale(1.09)}}
@keyframes pk-conf{0%{opacity:0;transform:translateY(0) scale(.5)}25%{opacity:1}100%{opacity:0;transform:translateY(62px) scale(1.25)}}
@media(max-width:1000px){.pk-stage{--s:.84}}
@media(max-width:820px){.pk-game{padding:12px;border-radius:22px}.pk-stage{--s:.68}.pk-brand h2{font-size:18px}.pk-brand p{font-size:9px}.pk-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.pk-stage{--s:.6}.pk-score{min-width:78px;padding:7px 10px}.pk-score>span{display:none}.pk-stat{display:block;padding:7px 2px;text-align:center}.pk-stat>span{font-size:16px}.pk-stat small{font-size:7px}.pk-stat b{font-size:14px}}
@media(max-width:560px){.pk-stage{--s:.52}.pk-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.pk-stage{--s:.46}}
@media(max-width:410px){.pk-stage{--s:.4}}
@media(max-width:350px){.pk-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.pk-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-treasure', 'name' => 'نقشهٔ گنجِ دانایی', 'icon' => '🗺️', 'sort' => 12,
                'description' => 'نقشهٔ کهنهٔ دزدانِ دریایی: با هر پاسخِ درست یک قدم روی مسیرِ نقطه‌چین جلو می‌روی و صندوق‌ها یکی‌یکی باز می‌شوند تا به ✗ برسی.',
                'board_html' => <<<'BOARD_HTML'
<div class="tz-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="tz-head">
        <div class="tz-brand">
            <div class="tz-logo">🗺️</div>
            <div>
                <h2>نقشهٔ گنجِ دانایی</h2>
                <p>هر پاسخِ درست، یک قدم تا صندوقِ گنج!</p>
            </div>
        </div>
        <div class="tz-score">
            <span>💎</span>
            <div><strong>{{score}}</strong><small>جواهر</small></div>
        </div>
    </header>

    <section class="tz-stats">
        <div class="tz-stat"><span>👣</span><div><small>قدم‌های رفته</small><b>{{pos}}</b></div></div>
        <div class="tz-stat"><span>🏴‍☠️</span><div><small>کلِ مسیر</small><b>{{total}}</b></div></div>
        <div class="tz-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="tz-stage">
      <div class="tz-scene">
        <svg class="tz-art" viewBox="0 0 620 360" aria-hidden="true">
            <defs>
                <radialGradient id="tzAge" cx=".5" cy=".45" r=".78">
                    <stop offset="0" stop-color="#f6e7c1"/><stop offset=".66" stop-color="#e3caa0"/>
                    <stop offset="1" stop-color="#b98f5c"/>
                </radialGradient>
                <linearGradient id="tzSea" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="360">
                    <stop offset="0" stop-color="#8fc4c9"/><stop offset="1" stop-color="#5b9aa4"/>
                </linearGradient>
            </defs>

            <!-- کاغذِ کهنه -->
            <rect width="620" height="360" fill="url(#tzAge)"/>
            <g opacity=".1" stroke="#7c4a12" stroke-width="1">
                <path d="M0 40H620M0 80H620M0 120H620M0 160H620M0 200H620M0 240H620M0 280H620M0 320H620"/>
                <path d="M40 0V360M120 0V360M200 0V360M280 0V360M360 0V360M440 0V360M520 0V360M600 0V360"/>
            </g>
            <g opacity=".3" fill="#8a6a3c">
                <ellipse cx="62" cy="56" rx="34" ry="26"/><ellipse cx="556" cy="318" rx="40" ry="28"/>
                <ellipse cx="228" cy="34" rx="26" ry="16"/><ellipse cx="98" cy="318" rx="24" ry="20"/>
                <ellipse cx="390" cy="326" rx="30" ry="18"/>
            </g>
            <g opacity=".5" fill="none" stroke="#7c4a12" stroke-width="2">
                <ellipse cx="62" cy="56" rx="34" ry="26"/><ellipse cx="556" cy="318" rx="40" ry="28"/>
            </g>
            <!-- سایهٔ لبه‌های سوخته -->
            <rect width="620" height="360" fill="none" stroke="#7c4a12" stroke-width="26" opacity=".18"/>
            <rect width="620" height="360" fill="none" stroke="#5a3408" stroke-width="10" opacity=".22"/>
            <!-- دریا -->
            <path d="M0 268 Q80 250 160 266 T330 262 T500 270 T620 256 V360 H0 Z" fill="url(#tzSea)" opacity=".75"/>
            <g stroke="#ffffffaa" stroke-width="2.4" fill="none" stroke-linecap="round" class="tz-waves">
                <path d="M40 300 q12 -7 24 0 t24 0"/><path d="M180 322 q12 -7 24 0 t24 0"/>
                <path d="M420 300 q12 -7 24 0 t24 0"/><path d="M540 334 q12 -7 24 0 t24 0"/>
            </g>
            <!-- خشکی -->
            <path d="M18 248 Q70 176 152 186 Q214 138 300 156 Q380 120 452 166 Q536 156 596 216 Q604 250 570 268 Q430 246 300 260 Q160 250 18 248 Z"
                  fill="#cdb078" stroke="#8a6a3c" stroke-width="3"/>
            <path d="M120 226 q34 -22 66 -4 q30 -24 66 -6" fill="none" stroke="#8a6a3c" stroke-width="2.6" opacity=".7"/>
            <!-- کوه و جنگل -->
            <g fill="#8a6a3c">
                <path d="M436 176 l26 -38 l26 38 z"/><path d="M478 180 l20 -28 l20 28 z"/>
            </g>
            <!-- مسیرِ نقطه‌چینِ کامل -->
            <path d="M556 300 C 500 300, 470 250, 430 236 C 380 218, 356 254, 306 242 C 254 230, 240 186, 190 190 C 140 194, 128 226, 96 214"
                  fill="none" stroke="#7c4a12" stroke-width="5" stroke-linecap="round" stroke-dasharray="2 13" opacity=".55"/>
            <!-- مسیرِ طی‌شده -->
            <path class="tz-trail" pathLength="100" stroke="#b91c1c" stroke-width="6" stroke-dasharray="100"
                  d="M556 300 C 500 300, 470 250, 430 236 C 380 218, 356 254, 306 242 C 254 230, 240 186, 190 190 C 140 194, 128 226, 96 214"/>

            <!-- گلِ‌قطب‌نما -->
            <g transform="translate(544,80)">
                <g class="tz-compass">
                    <circle r="36" fill="#f6e7c1" stroke="#7c4a12" stroke-width="3"/>
                    <circle r="27" fill="none" stroke="#7c4a12" stroke-width="1.4" opacity=".6"/>
                    <path d="M0 -30 L8 0 L0 30 L-8 0 Z" fill="#b91c1c"/>
                    <path d="M-30 0 L0 -8 L30 0 L0 8 Z" fill="#7c4a12"/>
                    <path d="M-20 -20 L4 -4 L20 20 L-4 4 Z" fill="#7c4a12" opacity=".45"/>
                    <circle r="4" fill="#f6e7c1" stroke="#7c4a12" stroke-width="2"/>
                </g>
                <text y="-40" text-anchor="middle" font-size="11" font-weight="900" fill="#7c4a12">N</text>
            </g>
            <!-- خطوطِ بادنما، مثلِ نقشه‌های قدیمی -->
            <g stroke="#7c4a12" stroke-width="1" opacity=".22">
                <path d="M544 80 L120 300 M544 80 L60 120 M544 80 L300 340 M544 80 L610 350"/>
            </g>
            <!-- مقیاسِ نقشه -->
            <g transform="translate(40,336)" opacity=".7">
                <path d="M0 0 H108" stroke="#7c4a12" stroke-width="3"/>
                <path d="M0 -6 V6 M36 -5 V5 M72 -5 V5 M108 -6 V6" stroke="#7c4a12" stroke-width="2.4"/>
                <text x="54" y="-11" text-anchor="middle" font-size="9" font-weight="700" fill="#7c4a12">۳ فرسنگ</text>
            </g>
        </svg>

        <!-- بازیگرانِ نقشه -->
        <span class="tz-actor tz-palm">🌴</span>
        <span class="tz-actor tz-palm2">🌴</span>
        <span class="tz-actor tz-ship">⛵</span>
        <span class="tz-actor tz-parrot">🦜</span>
        <span class="tz-actor tz-skull">💀</span>
        <span class="tz-actor tz-captain">🏴‍☠️</span>

        <!-- صندوق‌ها: بسته → باز -->
        <span class="tz-chest ch1"><i class="tz-shut">📦</i><i class="tz-open">💰</i></span>
        <span class="tz-chest ch2"><i class="tz-shut">📦</i><i class="tz-open">💰</i></span>
        <span class="tz-chest ch3"><i class="tz-shut">📦</i><i class="tz-open">💰</i></span>

        <!-- نشانِ گنج -->
        <span class="tz-x">✗</span>
        <span class="tz-prize">🏆</span>

        <div class="tz-hero"><div class="tz-hero-in"><span class="tz-avatar">{{char}}</span><span class="tz-glass">🔍</span></div></div>
      </div>
    </div>

    <div class="tz-cheer"><span>🧭</span><b>ناخدا: مسیر درست است ملوان! صندوقِ بعدی نزدیک است…</b></div>

    <section class="tz-progress">
        <div class="tz-progress-info">
            <span>مسیر تا نشانِ ✗</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="tz-track">
            <span class="tz-fill"></span>
            <div class="tz-runner">{{char}}</div>
        </div>
        <div class="tz-progress-foot">
            <span>📦 هر صندوق یک ایستگاه است · ✗ نشانِ گنج در انتهای مسیر</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.tz-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#a16207,#451a03 58%,#1c0d02);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.tz-game *,.tz-game *:before,.tz-game *:after{box-sizing:border-box}
.tz-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.tz-brand{display:flex;align-items:center;gap:12px;min-width:0}
.tz-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#fde68a);border:3px solid #fbbf24;border-radius:18px;box-shadow:0 8px 20px #0006;animation:tz-logo 3.6s ease-in-out infinite}
.tz-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.tz-brand p{margin:3px 0 0;color:#fde9c8;font-size:12px}
.tz-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.tz-score span{font-size:24px}
.tz-score strong,.tz-score small{display:block}
.tz-score strong{color:#fbbf24;font-size:22px}
.tz-score small{margin-top:3px;color:#fde9c8;font-size:9px}
.tz-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.tz-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.tz-stat>span{font-size:21px}
.tz-stat small,.tz-stat b{display:block}
.tz-stat small{color:#fde9c8;font-size:9px}
.tz-stat b{margin-top:3px;font-size:17px}
.tz-stage{--s:1;width:calc(620px*var(--s));height:calc(360px*var(--s));margin-inline:auto}
.tz-scene{position:relative;width:620px;height:360px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:#e9d5a8}
.tz-art{position:absolute;inset:0;width:620px;height:360px;pointer-events:none}
.tz-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:tz-trail .85s cubic-bezier(.3,.9,.3,1) both}
.tz-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.tz-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:tz-hop .85s cubic-bezier(.3,.9,.3,1) both}
.tz-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#fef3c7);border:4px solid #fbbf24;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #fbbf2470}
.tz-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#fde9c8;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.tz-cheer span{font-size:17px;flex:none}
.tz-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.tz-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#fde9c8;font-size:11px}
.tz-progress-info b{color:#fbbf24}
.tz-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.tz-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#65a30d,#fbbf24,#ea580c);border-radius:inherit;box-shadow:0 0 15px #fbbf2480;animation:tz-fill .85s cubic-bezier(.3,.9,.3,1) both}
.tz-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #fbbf24;border-radius:50%;box-shadow:0 6px 12px #0007;animation:tz-run .85s cubic-bezier(.3,.9,.3,1) both,tz-bob 1.6s 1s infinite}
.tz-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#c8a778;font-size:9px}
.tz-progress-foot b{color:#fbbf24;font-size:11px}
@keyframes tz-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes tz-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes tz-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes tz-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes tz-bob{50%{transform:translateY(-5px)}}
@keyframes tz-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.tz-scene{box-shadow:inset 0 0 0 4px #7c4a12,inset 0 0 60px #8a6a3c66,0 18px 38px #0007}
.tz-waves{animation:tz-wave 3.2s ease-in-out infinite}
.tz-compass{animation:tz-spin 9s ease-in-out infinite;transform-origin:548px 78px}
.tz-actor{position:absolute;filter:drop-shadow(0 4px 3px #00000055)}
.tz-palm{top:186px;inset-inline-start:calc(620px - 274px);font-size:27px;animation:tz-sway 3.4s ease-in-out infinite}
.tz-palm2{top:196px;inset-inline-start:calc(620px - 356px);font-size:22px;animation:tz-sway 3.4s .7s ease-in-out infinite}
.tz-ship{top:290px;inset-inline-start:calc(620px - 186px);font-size:29px;animation:tz-boat 3.8s ease-in-out infinite}
.tz-parrot{top:140px;inset-inline-start:calc(620px - 350px);font-size:21px;animation:tz-fly 4.4s ease-in-out infinite}
.tz-skull{top:230px;inset-inline-start:calc(620px - 404px);font-size:19px;opacity:.8}
.tz-captain{top:286px;inset-inline-start:calc(620px - 596px);font-size:25px;animation:tz-bobx 2.6s ease-in-out infinite}
.tz-chest{position:absolute;width:26px;height:26px;display:grid;place-items:center;filter:drop-shadow(0 4px 3px #00000055)}
.tz-chest i{grid-area:1/1;font-style:normal;font-size:23px}
.tz-shut{opacity:1}
.tz-open{opacity:0;font-size:25px}
.tz-chest.ch1{top:224px;inset-inline-start:calc(620px - 444px)}
.tz-chest.ch2{top:230px;inset-inline-start:calc(620px - 320px)}
.tz-chest.ch3{top:178px;inset-inline-start:calc(620px - 204px)}
.ch1 .tz-shut{opacity:clamp(0,calc((28 - var(--p))*99),1)}
.ch1 .tz-open{opacity:clamp(0,calc((var(--p) - 28)*99),1)}
.ch2 .tz-shut{opacity:clamp(0,calc((55 - var(--p))*99),1)}
.ch2 .tz-open{opacity:clamp(0,calc((var(--p) - 55)*99),1)}
.ch3 .tz-shut{opacity:clamp(0,calc((82 - var(--p))*99),1)}
.ch3 .tz-open{opacity:clamp(0,calc((var(--p) - 82)*99),1)}
.tz-x{position:absolute;top:196px;inset-inline-start:calc(620px - 114px);font-size:34px;font-weight:900;color:#b91c1c;text-shadow:0 2px 0 #7c1d1d;animation:tz-pulse 1.8s ease-in-out infinite}
.tz-prize{position:absolute;top:150px;inset-inline-start:calc(620px - 118px);font-size:28px;opacity:clamp(0,calc((var(--p) - 99)*99),1);filter:drop-shadow(0 0 12px #fbbf24);animation:tz-pop 1.2s ease-in-out infinite}
.tz-hero{offset-path:path("M556 300 C 500 300, 470 250, 430 236 C 380 218, 356 254, 306 242 C 254 230, 240 186, 190 190 C 140 194, 128 226, 96 214");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:tz-move .85s cubic-bezier(.3,.9,.3,1) both}
.tz-avatar{background:radial-gradient(circle at 35% 25%,#fff,#fef3c7);border-color:#7c4a12;box-shadow:0 8px 16px #0006,0 0 0 4px #e9d5a8}
.tz-glass{position:absolute;bottom:-3px;left:-4px;font-size:18px;filter:drop-shadow(0 2px 2px #0006);animation:tz-look 2.4s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.tz-hero{top:250px;inset-inline-start:calc(70px + (486px - 70px)*(100 - var(--p))/100)}}
@keyframes tz-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes tz-wave{50%{transform:translateX(7px)}}
@keyframes tz-spin{0%,100%{transform:rotate(-7deg)}50%{transform:rotate(7deg)}}
@keyframes tz-sway{0%,100%{transform:rotate(-5deg)}50%{transform:rotate(6deg)}}
@keyframes tz-boat{0%,100%{transform:translateY(0) rotate(-4deg)}50%{transform:translateY(-6px) rotate(5deg)}}
@keyframes tz-fly{0%,100%{transform:translate(0,0)}50%{transform:translate(-16px,-10px)}}
@keyframes tz-bobx{50%{transform:translateY(-5px)}}
@keyframes tz-look{0%,100%{transform:rotate(-12deg)}50%{transform:rotate(14deg) scale(1.1)}}
@keyframes tz-pulse{50%{transform:scale(1.16)}}
@keyframes tz-pop{50%{transform:translateY(-6px) scale(1.1)}}
@media(max-width:1000px){.tz-stage{--s:.84}}
@media(max-width:820px){.tz-game{padding:12px;border-radius:22px}.tz-stage{--s:.68}.tz-brand h2{font-size:18px}.tz-brand p{font-size:9px}.tz-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.tz-stage{--s:.6}.tz-score{min-width:78px;padding:7px 10px}.tz-score>span{display:none}.tz-stat{display:block;padding:7px 2px;text-align:center}.tz-stat>span{font-size:16px}.tz-stat small{font-size:7px}.tz-stat b{font-size:14px}}
@media(max-width:560px){.tz-stage{--s:.52}.tz-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.tz-stage{--s:.46}}
@media(max-width:410px){.tz-stage{--s:.4}}
@media(max-width:350px){.tz-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.tz-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-space', 'name' => 'کهکشان‌نوردِ دانایی', 'icon' => '🚀', 'sort' => 13,
                'description' => 'سفر در فضا روی مدارِ سیاره‌ها؛ با هر پاسخِ درست کپسول جلوتر می‌رود و سیارهٔ بعدی روشن می‌شود تا به ایستگاهِ پایانی برسی.',
                'board_html' => <<<'BOARD_HTML'
<div class="sp-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="sp-head">
        <div class="sp-brand">
            <div class="sp-logo">🚀</div>
            <div>
                <h2>کهکشان‌نوردِ دانایی</h2>
                <p>هر پاسخِ درست، یک سیاره جلوتر!</p>
            </div>
        </div>
        <div class="sp-score">
            <span>⚡</span>
            <div><strong>{{score}}</strong><small>سوختِ سفینه</small></div>
        </div>
    </header>

    <section class="sp-stats">
        <div class="sp-stat"><span>🪐</span><div><small>سیاره‌های رفته</small><b>{{pos}}</b></div></div>
        <div class="sp-stat"><span>🛰️</span><div><small>کلِ مدار</small><b>{{total}}</b></div></div>
        <div class="sp-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="sp-stage">
      <div class="sp-scene">
        <svg class="sp-art" viewBox="0 0 620 360" aria-hidden="true">
            <defs>
                <radialGradient id="spNeb" cx=".5" cy=".5" r=".5">
                    <stop offset="0" stop-color="#a855f7" stop-opacity=".55"/>
                    <stop offset="1" stop-color="#a855f7" stop-opacity="0"/>
                </radialGradient>
                <radialGradient id="spNeb2" cx=".5" cy=".5" r=".5">
                    <stop offset="0" stop-color="#22d3ee" stop-opacity=".45"/>
                    <stop offset="1" stop-color="#22d3ee" stop-opacity="0"/>
                </radialGradient>
                <linearGradient id="spOrb" gradientUnits="userSpaceOnUse" x1="566" y1="312" x2="62" y2="76">
                    <stop offset="0" stop-color="#22d3ee"/><stop offset=".6" stop-color="#818cf8"/><stop offset="1" stop-color="#f472b6"/>
                </linearGradient>
                <radialGradient id="spEarth" cx=".35" cy=".3" r=".8">
                    <stop offset="0" stop-color="#7dd3fc"/><stop offset="1" stop-color="#1d4ed8"/>
                </radialGradient>
                <radialGradient id="spMars" cx=".35" cy=".3" r=".8">
                    <stop offset="0" stop-color="#fca5a5"/><stop offset="1" stop-color="#b91c1c"/>
                </radialGradient>
                <radialGradient id="spSat" cx=".35" cy=".3" r=".8">
                    <stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#b45309"/>
                </radialGradient>
                <radialGradient id="spIce" cx=".35" cy=".3" r=".8">
                    <stop offset="0" stop-color="#bae6fd"/><stop offset="1" stop-color="#0e7490"/>
                </radialGradient>
            </defs>

            <ellipse cx="150" cy="96" rx="180" ry="110" fill="url(#spNeb)"/>
            <ellipse cx="500" cy="270" rx="160" ry="100" fill="url(#spNeb2)"/>

            <g class="sp-stars" fill="#fff">
                <circle cx="52" cy="46" r="1.7"/><circle cx="128" cy="150" r="1.3"/><circle cx="212" cy="58" r="2"/>
                <circle cx="286" cy="120" r="1.4"/><circle cx="352" cy="44" r="1.8"/><circle cx="430" cy="128" r="1.4"/>
                <circle cx="502" cy="52" r="2"/><circle cx="578" cy="150" r="1.5"/><circle cx="96" cy="246" r="1.6"/>
                <circle cx="244" cy="300" r="1.4"/><circle cx="400" cy="318" r="1.8"/><circle cx="540" cy="212" r="1.3"/>
                <circle cx="176" cy="196" r="1.2"/><circle cx="330" cy="248" r="1.5"/><circle cx="470" cy="196" r="1.4"/>
            </g>

            <!-- مدارِ کامل -->
            <path d="M566 312 C 470 302, 392 262, 330 208 C 268 154, 176 104, 62 76" fill="none" stroke="#ffffff30" stroke-width="3" stroke-dasharray="6 10"/>
            <!-- مدارِ طی‌شده -->
            <path class="sp-trail" pathLength="100" stroke="url(#spOrb)" stroke-width="5" stroke-dasharray="100" d="M566 312 C 470 302, 392 262, 330 208 C 268 154, 176 104, 62 76"/>

            <!-- سیاره‌ها روی مدار -->
            <g class="sp-planet p1"><circle cx="470" cy="286" r="24" fill="url(#spEarth)"/>
                <ellipse cx="470" cy="286" rx="24" ry="7" fill="none" stroke="#ffffff55" stroke-width="2"/></g>
            <g class="sp-planet p2"><circle cx="344" cy="222" r="20" fill="url(#spMars)"/></g>
            <g class="sp-planet p3"><circle cx="232" cy="146" r="26" fill="url(#spSat)"/>
                <ellipse cx="232" cy="146" rx="42" ry="11" fill="none" stroke="#fde68a" stroke-width="4" opacity=".85"/></g>
            <g class="sp-planet p4"><circle cx="118" cy="98" r="18" fill="url(#spIce)"/></g>

            <!-- ایستگاهِ فضایی -->
            <g class="sp-station">
                <circle cx="62" cy="76" r="15" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
                <path d="M38 76H18M86 76H106" stroke="#94a3b8" stroke-width="5" stroke-linecap="round"/>
                <rect x="10" y="64" width="12" height="24" rx="3" fill="#38bdf8"/>
                <rect x="102" y="64" width="12" height="24" rx="3" fill="#38bdf8"/>
            </g>
        </svg>

        <span class="sp-actor sp-alien">👽</span>
        <span class="sp-actor sp-ufo">🛸</span>
        <span class="sp-actor sp-sat">🛰️</span>
        <span class="sp-shoot"></span>

        <span class="sp-tag t1">🌍 زمین</span>
        <span class="sp-tag t2">🔴 مریخ</span>
        <span class="sp-tag t3">🪐 کیوان</span>
        <span class="sp-tag t4">🧊 یخی</span>

        <div class="sp-ship"><span>🚀</span></div>
        <div class="sp-hero"><div class="sp-hero-in"><span class="sp-avatar">{{char}}</span><span class="sp-helmet"></span></div></div>

        <div class="sp-dock">🎉 رسیدی به ایستگاه!</div>
      </div>
    </div>

    <div class="sp-cheer"><span>📡</span><b>مرکزِ کنترل: مسیر پایدار است کهکشان‌نورد — سیارهٔ بعدی در دیدرس!</b></div>

    <section class="sp-progress">
        <div class="sp-progress-info">
            <span>مدار تا ایستگاهِ پایانی</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="sp-track">
            <span class="sp-fill"></span>
            <div class="sp-runner">{{char}}</div>
        </div>
        <div class="sp-progress-foot">
            <span>🪐 هر سیاره یک ایستگاه · 🛰️ ایستگاه در انتهای مدار</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.sp-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#4338ca,#1e1b4b 55%,#05030f);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.sp-game *,.sp-game *:before,.sp-game *:after{box-sizing:border-box}
.sp-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.sp-brand{display:flex;align-items:center;gap:12px;min-width:0}
.sp-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#c7d2fe);border:3px solid #67e8f9;border-radius:18px;box-shadow:0 8px 20px #0006;animation:sp-logo 3.6s ease-in-out infinite}
.sp-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.sp-brand p{margin:3px 0 0;color:#c7d2fe;font-size:12px}
.sp-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.sp-score span{font-size:24px}
.sp-score strong,.sp-score small{display:block}
.sp-score strong{color:#67e8f9;font-size:22px}
.sp-score small{margin-top:3px;color:#c7d2fe;font-size:9px}
.sp-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.sp-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.sp-stat>span{font-size:21px}
.sp-stat small,.sp-stat b{display:block}
.sp-stat small{color:#c7d2fe;font-size:9px}
.sp-stat b{margin-top:3px;font-size:17px}
.sp-stage{--s:1;width:calc(620px*var(--s));height:calc(360px*var(--s));margin-inline:auto}
.sp-scene{position:relative;width:620px;height:360px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:radial-gradient(120% 90% at 20% 105%,#312e81 0%,#130f36 45%,#04020c 100%)}
.sp-art{position:absolute;inset:0;width:620px;height:360px;pointer-events:none}
.sp-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:sp-trail .85s cubic-bezier(.3,.9,.3,1) both}
.sp-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.sp-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:sp-hop .85s cubic-bezier(.3,.9,.3,1) both}
.sp-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#e0e7ff);border:4px solid #67e8f9;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #67e8f970}
.sp-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#c7d2fe;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.sp-cheer span{font-size:17px;flex:none}
.sp-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.sp-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#c7d2fe;font-size:11px}
.sp-progress-info b{color:#67e8f9}
.sp-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.sp-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#22d3ee,#818cf8,#f472b6);border-radius:inherit;box-shadow:0 0 15px #67e8f980;animation:sp-fill .85s cubic-bezier(.3,.9,.3,1) both}
.sp-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #67e8f9;border-radius:50%;box-shadow:0 6px 12px #0007;animation:sp-run .85s cubic-bezier(.3,.9,.3,1) both,sp-bob 1.6s 1s infinite}
.sp-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#8b93c9;font-size:9px}
.sp-progress-foot b{color:#67e8f9;font-size:11px}
@keyframes sp-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes sp-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes sp-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes sp-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes sp-bob{50%{transform:translateY(-5px)}}
@keyframes sp-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.sp-stars circle{animation:sp-tw 2.6s ease-in-out infinite}
.sp-stars circle:nth-child(3n){animation-delay:.7s}
.sp-stars circle:nth-child(3n+1){animation-delay:1.4s}
.sp-planet{transform-box:fill-box;transform-origin:center;animation:sp-float 5s ease-in-out infinite}
.sp-planet.p2{animation-delay:.8s}.sp-planet.p3{animation-delay:1.6s}.sp-planet.p4{animation-delay:2.4s}
.sp-planet.p1{opacity:clamp(.35,calc((var(--p) - 8)*99),1)}
.sp-planet.p2{opacity:clamp(.35,calc((var(--p) - 34)*99),1)}
.sp-planet.p3{opacity:clamp(.35,calc((var(--p) - 60)*99),1)}
.sp-planet.p4{opacity:clamp(.35,calc((var(--p) - 84)*99),1)}
.sp-station{animation:sp-float 6s ease-in-out infinite}
.sp-actor{position:absolute;filter:drop-shadow(0 0 10px #22d3ee88)}
.sp-alien{top:36px;inset-inline-start:calc(620px - 214px);font-size:22px;animation:sp-float 3.4s ease-in-out infinite}
.sp-ufo{top:250px;inset-inline-start:calc(620px - 152px);font-size:26px;animation:sp-ufo 6s ease-in-out infinite}
.sp-sat{top:64px;inset-inline-start:calc(620px - 420px);font-size:19px;animation:sp-float 4.2s ease-in-out infinite}
.sp-shoot{position:absolute;top:30px;inset-inline-start:calc(620px - 560px);width:64px;height:2px;background:linear-gradient(90deg,#fff,transparent);border-radius:2px;animation:sp-shoot 5s ease-in infinite}
.sp-tag{position:absolute;padding:2px 8px;font-size:9px;font-weight:800;color:#e0e7ff;background:#0f172ab8;border:1px solid #ffffff2e;border-radius:20px;white-space:nowrap}
.sp-tag.t1{top:318px;inset-inline-start:calc(620px - 508px)}
.sp-tag.t2{top:252px;inset-inline-start:calc(620px - 380px)}
.sp-tag.t3{top:182px;inset-inline-start:calc(620px - 272px)}
.sp-tag.t4{top:124px;inset-inline-start:calc(620px - 152px)}
.sp-ship{position:absolute;top:0;left:0;width:0;height:0;z-index:8;offset-path:path("M566 312 C 470 302, 392 262, 330 208 C 268 154, 176 104, 62 76");offset-rotate:auto;offset-distance:calc(min(100,calc(var(--p) + 6))*1%);animation:sp-shipm .85s cubic-bezier(.3,.9,.3,1) both}
.sp-ship span{position:absolute;left:-13px;top:-13px;font-size:24px;transform:rotate(-45deg);filter:drop-shadow(0 0 10px #f472b6)}
.sp-hero{offset-path:path("M566 312 C 470 302, 392 262, 330 208 C 268 154, 176 104, 62 76");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:sp-move .85s cubic-bezier(.3,.9,.3,1) both}
.sp-avatar{border-color:#67e8f9;box-shadow:0 8px 16px #000a,0 0 26px #22d3ee}
.sp-helmet{position:absolute;inset:-6px;border-radius:50%;border:2px solid #a5f3fc66;box-shadow:inset 0 6px 14px #ffffff3d;pointer-events:none}
.sp-dock{position:absolute;top:150px;inset-inline-start:calc(620px - 214px);padding:6px 12px;font-size:13px;font-weight:900;color:#04203a;background:#67e8f9;border-radius:20px;box-shadow:0 0 22px #22d3ee;opacity:clamp(0,calc((var(--p) - 99)*99),1);animation:sp-pop 1.2s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.sp-hero{top:300px;inset-inline-start:calc(50px + (520px - 50px)*(100 - var(--p))/100)}.sp-ship{display:none}}
@keyframes sp-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes sp-shipm{from{offset-distance:calc(min(100,calc(var(--from) + 6))*1%)}to{offset-distance:calc(min(100,calc(var(--p) + 6))*1%)}}
@keyframes sp-tw{0%,100%{opacity:.25}50%{opacity:1}}
@keyframes sp-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
@keyframes sp-ufo{0%,100%{transform:translate(0,0)}50%{transform:translate(28px,-14px)}}
@keyframes sp-shoot{0%{opacity:0;transform:translate(0,0)}8%{opacity:1}22%{opacity:0;transform:translate(-150px,70px)}100%{opacity:0}}
@keyframes sp-pop{50%{transform:scale(1.07)}}
@media(max-width:1000px){.sp-stage{--s:.84}}
@media(max-width:820px){.sp-game{padding:12px;border-radius:22px}.sp-stage{--s:.68}.sp-brand h2{font-size:18px}.sp-brand p{font-size:9px}.sp-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.sp-stage{--s:.6}.sp-score{min-width:78px;padding:7px 10px}.sp-score>span{display:none}.sp-stat{display:block;padding:7px 2px;text-align:center}.sp-stat>span{font-size:16px}.sp-stat small{font-size:7px}.sp-stat b{font-size:14px}}
@media(max-width:560px){.sp-stage{--s:.52}.sp-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.sp-stage{--s:.46}}
@media(max-width:410px){.sp-stage{--s:.4}}
@media(max-width:350px){.sp-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.sp-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-detective', 'name' => 'پروندهٔ کارآگاهِ کوچک', 'icon' => '🕵️', 'sort' => 14,
                'description' => 'تابلوی سرنخِ یک پروندهٔ مرموز؛ هر پاسخِ درست نخِ قرمز را جلو می‌برد و یک سرنخِ تازه از زیرِ برگهٔ «؟» بیرون می‌آید تا پرونده حل شود.',
                'board_html' => <<<'BOARD_HTML'
<div class="dt-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="dt-head">
        <div class="dt-brand">
            <div class="dt-logo">🕵️</div>
            <div>
                <h2>پروندهٔ کارآگاهِ کوچک</h2>
                <p>هر پاسخِ درست، یک سرنخِ تازه!</p>
            </div>
        </div>
        <div class="dt-score">
            <span>🔎</span>
            <div><strong>{{score}}</strong><small>امتیازِ کارآگاه</small></div>
        </div>
    </header>

    <section class="dt-stats">
        <div class="dt-stat"><span>📌</span><div><small>سرنخ‌های پیداشده</small><b>{{pos}}</b></div></div>
        <div class="dt-stat"><span>🗂️</span><div><small>کلِ پرونده</small><b>{{total}}</b></div></div>
        <div class="dt-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="dt-stage">
      <div class="dt-scene">
        <svg class="dt-art" viewBox="0 0 620 350" aria-hidden="true">
            <defs>
                <pattern id="dtCork" width="16" height="16" patternUnits="userSpaceOnUse">
                    <rect width="16" height="16" fill="#a8794a"/>
                    <circle cx="4" cy="5" r="1.7" fill="#8d6237" opacity=".8"/>
                    <circle cx="12" cy="11" r="1.4" fill="#c08f58" opacity=".7"/>
                    <circle cx="9" cy="3" r="1" fill="#8d6237" opacity=".6"/>
                </pattern>
                <radialGradient id="dtLamp" cx=".5" cy="0" r=".9">
                    <stop offset="0" stop-color="#fef3c7" stop-opacity=".5"/>
                    <stop offset="1" stop-color="#fef3c7" stop-opacity="0"/>
                </radialGradient>
            </defs>

            <rect width="620" height="350" fill="url(#dtCork)"/>
            <rect width="620" height="350" fill="url(#dtLamp)"/>
            <rect x="8" y="8" width="604" height="334" fill="none" stroke="#5b3d21" stroke-width="9" rx="8"/>

            <!-- نخِ کاملِ پرونده -->
            <path d="M556 268 L444 116 L332 272 L220 114 L96 256" fill="none" stroke="#7f1d1d" stroke-width="3" opacity=".35" stroke-dasharray="5 7"/>
            <!-- نخی که تا اینجا کشیده شده -->
            <path class="dt-trail" pathLength="100" stroke="#dc2626" stroke-width="4" stroke-dasharray="100" d="M556 268 L444 116 L332 272 L220 114 L96 256"/>
        </svg>

        <!-- برگه‌های سرنخ: «؟» تا وقتی نرسیده‌ای -->
        <div class="dt-card k1"><i class="dt-q">❓</i><i class="dt-c">👣</i><u>ردِّ پا</u></div>
        <div class="dt-card k2"><i class="dt-q">❓</i><i class="dt-c">🔑</i><u>کلیدِ گمشده</u></div>
        <div class="dt-card k3"><i class="dt-q">❓</i><i class="dt-c">🧤</i><u>دستکش</u></div>
        <div class="dt-card k4"><i class="dt-q">❓</i><i class="dt-c">📄</i><u>یادداشت</u></div>
        <div class="dt-card k5"><i class="dt-q">❓</i><i class="dt-c">🔓</i><u>پرونده حل شد</u></div>

        <span class="dt-actor dt-lamp">💡</span>
        <span class="dt-actor dt-dog">🐕</span>
        <span class="dt-actor dt-cat">🐈‍⬛</span>
        <span class="dt-stamp">محرمانه</span>

        <div class="dt-hero"><div class="dt-hero-in"><span class="dt-avatar">{{char}}</span><span class="dt-glass">🔎</span><span class="dt-hat">🎩</span></div></div>
      </div>
    </div>

    <div class="dt-cheer"><span>🕵️</span><b>کارآگاه: این سرنخ به بعدی وصل است… پرونده دارد باز می‌شود!</b></div>

    <section class="dt-progress">
        <div class="dt-progress-info">
            <span>مسیرِ پرونده تا حلِ معما</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="dt-track">
            <span class="dt-fill"></span>
            <div class="dt-runner">{{char}}</div>
        </div>
        <div class="dt-progress-foot">
            <span>📌 هر سرنخ یک قدم · 🔓 سرنخِ آخر یعنی پرونده حل شد</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.dt-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#475569,#1e293b 55%,#080c14);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.dt-game *,.dt-game *:before,.dt-game *:after{box-sizing:border-box}
.dt-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.dt-brand{display:flex;align-items:center;gap:12px;min-width:0}
.dt-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#e2e8f0);border:3px solid #f59e0b;border-radius:18px;box-shadow:0 8px 20px #0006;animation:dt-logo 3.6s ease-in-out infinite}
.dt-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.dt-brand p{margin:3px 0 0;color:#cbd5e1;font-size:12px}
.dt-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.dt-score span{font-size:24px}
.dt-score strong,.dt-score small{display:block}
.dt-score strong{color:#f59e0b;font-size:22px}
.dt-score small{margin-top:3px;color:#cbd5e1;font-size:9px}
.dt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.dt-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.dt-stat>span{font-size:21px}
.dt-stat small,.dt-stat b{display:block}
.dt-stat small{color:#cbd5e1;font-size:9px}
.dt-stat b{margin-top:3px;font-size:17px}
.dt-stage{--s:1;width:calc(620px*var(--s));height:calc(350px*var(--s));margin-inline:auto}
.dt-scene{position:relative;width:620px;height:350px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:#a8794a}
.dt-art{position:absolute;inset:0;width:620px;height:350px;pointer-events:none}
.dt-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:dt-trail .85s cubic-bezier(.3,.9,.3,1) both}
.dt-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.dt-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:dt-hop .85s cubic-bezier(.3,.9,.3,1) both}
.dt-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#e2e8f0);border:4px solid #f59e0b;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #f59e0b70}
.dt-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#cbd5e1;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.dt-cheer span{font-size:17px;flex:none}
.dt-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.dt-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#cbd5e1;font-size:11px}
.dt-progress-info b{color:#f59e0b}
.dt-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.dt-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#64748b,#f59e0b,#dc2626);border-radius:inherit;box-shadow:0 0 15px #f59e0b80;animation:dt-fill .85s cubic-bezier(.3,.9,.3,1) both}
.dt-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #f59e0b;border-radius:50%;box-shadow:0 6px 12px #0007;animation:dt-run .85s cubic-bezier(.3,.9,.3,1) both,dt-bob 1.6s 1s infinite}
.dt-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#8b98ab;font-size:9px}
.dt-progress-foot b{color:#f59e0b;font-size:11px}
@keyframes dt-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes dt-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes dt-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes dt-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes dt-bob{50%{transform:translateY(-5px)}}
@keyframes dt-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.dt-card{position:absolute;width:74px;padding:6px 4px 5px;display:grid;place-items:center;
  background:#fdfaf3;border:1px solid #d6c9ad;border-radius:5px;box-shadow:0 7px 14px #00000055;text-align:center}
.dt-card:before{content:"📌";position:absolute;top:-11px;font-size:15px;filter:drop-shadow(0 2px 2px #0006)}
.dt-card i{grid-area:1/1;font-style:normal;font-size:26px;line-height:1.2}
.dt-card u{grid-row:2;text-decoration:none;font-size:8.5px;font-weight:800;color:#44403c;margin-top:3px}
.dt-card .dt-c{opacity:0}
.dt-card.k1{top:214px;inset-inline-start:calc(620px - 594px);transform:rotate(-4deg)}
.dt-card.k2{top:62px;inset-inline-start:calc(620px - 482px);transform:rotate(3deg)}
.dt-card.k3{top:218px;inset-inline-start:calc(620px - 370px);transform:rotate(-3deg)}
.dt-card.k4{top:60px;inset-inline-start:calc(620px - 258px);transform:rotate(4deg)}
.dt-card.k5{top:202px;inset-inline-start:calc(620px - 134px);transform:rotate(-2deg)}
.k1 .dt-q{opacity:clamp(0,calc((3 - var(--p))*99),1)}   .k1 .dt-c{opacity:clamp(0,calc((var(--p) - 3)*99),1)}
.k2 .dt-q{opacity:clamp(0,calc((27 - var(--p))*99),1)}  .k2 .dt-c{opacity:clamp(0,calc((var(--p) - 27)*99),1)}
.k3 .dt-q{opacity:clamp(0,calc((52 - var(--p))*99),1)}  .k3 .dt-c{opacity:clamp(0,calc((var(--p) - 52)*99),1)}
.k4 .dt-q{opacity:clamp(0,calc((77 - var(--p))*99),1)}  .k4 .dt-c{opacity:clamp(0,calc((var(--p) - 77)*99),1)}
.k5 .dt-q{opacity:clamp(0,calc((98 - var(--p))*99),1)}  .k5 .dt-c{opacity:clamp(0,calc((var(--p) - 98)*99),1)}
.k5{box-shadow:0 7px 14px #00000055,0 0 calc(var(--p)*0.3px) #f59e0b}
.dt-actor{position:absolute;filter:drop-shadow(0 4px 4px #00000066)}
.dt-lamp{top:14px;inset-inline-start:calc(620px - 330px);font-size:24px;animation:dt-flick 4s ease-in-out infinite}
.dt-dog{top:288px;inset-inline-start:calc(620px - 200px);font-size:24px;animation:dt-sniff 2.8s ease-in-out infinite}
.dt-cat{top:292px;inset-inline-start:calc(620px - 540px);font-size:21px;animation:dt-sniff 3.4s .6s ease-in-out infinite}
.dt-stamp{position:absolute;top:296px;inset-inline-start:calc(620px - 400px);padding:3px 12px;color:#b91c1c;
  border:3px double #b91c1c;border-radius:5px;font-size:12px;font-weight:900;opacity:.6;transform:rotate(-9deg)}
.dt-hero{offset-path:path("M556 268 L444 116 L332 272 L220 114 L96 256");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:dt-move .85s cubic-bezier(.3,.9,.3,1) both}
.dt-avatar{border-color:#f59e0b;box-shadow:0 8px 16px #000a,0 0 0 4px #00000030}
.dt-glass{position:absolute;bottom:-4px;left:-6px;font-size:19px;filter:drop-shadow(0 2px 2px #0007);animation:dt-look 2.2s ease-in-out infinite}
.dt-hat{position:absolute;top:-17px;font-size:19px;filter:drop-shadow(0 2px 2px #0007)}
@supports not (offset-path: path("M0 0")){.dt-hero{top:190px;inset-inline-start:calc(60px + (500px - 60px)*(100 - var(--p))/100)}}
@keyframes dt-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes dt-flick{0%,100%{opacity:1}42%{opacity:.5}46%{opacity:.95}}
@keyframes dt-sniff{50%{transform:translateX(-11px)}}
@keyframes dt-look{0%,100%{transform:rotate(-14deg)}50%{transform:rotate(16deg) scale(1.12)}}
@media(max-width:1000px){.dt-stage{--s:.84}}
@media(max-width:820px){.dt-game{padding:12px;border-radius:22px}.dt-stage{--s:.68}.dt-brand h2{font-size:18px}.dt-brand p{font-size:9px}.dt-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.dt-stage{--s:.6}.dt-score{min-width:78px;padding:7px 10px}.dt-score>span{display:none}.dt-stat{display:block;padding:7px 2px;text-align:center}.dt-stat>span{font-size:16px}.dt-stat small{font-size:7px}.dt-stat b{font-size:14px}}
@media(max-width:560px){.dt-stage{--s:.52}.dt-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.dt-stage{--s:.46}}
@media(max-width:410px){.dt-stage{--s:.4}}
@media(max-width:350px){.dt-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.dt-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-wheel', 'name' => 'گردونهٔ دانش', 'icon' => '🎡', 'sort' => 15,
                'description' => 'یک گردونهٔ واقعی که با هر پاسخِ درست می‌چرخد؛ نشانگرِ بالا خانهٔ تازه را نشان می‌دهد و چراغ‌های دورِ گردونه یکی‌یکی روشن می‌شوند.',
                'board_html' => <<<'BOARD_HTML'
<div class="wh-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="wh-head">
        <div class="wh-brand">
            <div class="wh-logo">🎡</div>
            <div>
                <h2>گردونهٔ دانش</h2>
                <p>هر پاسخِ درست، یک چرخشِ گردونه!</p>
            </div>
        </div>
        <div class="wh-score">
            <span>🎟️</span>
            <div><strong>{{score}}</strong><small>بلیتِ جایزه</small></div>
        </div>
    </header>

    <section class="wh-stats">
        <div class="wh-stat"><span>🎯</span><div><small>چرخش‌ها</small><b>{{pos}}</b></div></div>
        <div class="wh-stat"><span>🎡</span><div><small>کلِ گردونه</small><b>{{total}}</b></div></div>
        <div class="wh-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="wh-stage">
      <div class="wh-scene">
        <svg class="wh-art" viewBox="0 0 620 360" aria-hidden="true">
            <defs>
                <radialGradient id="whGlow" cx=".5" cy=".5" r=".5">
                    <stop offset=".55" stop-color="#fde047" stop-opacity=".3"/>
                    <stop offset="1" stop-color="#fde047" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <circle cx="310" cy="190" r="185" fill="url(#whGlow)"/>

            <!-- پایهٔ گردونه -->
            <path d="M266 300 L310 206 L354 300 Z" fill="#7f1d1d"/>
            <rect x="238" y="298" width="144" height="16" rx="8" fill="#991b1b"/>

            <g class="wh-wheel">
                <circle cx="310" cy="190" r="128" fill="#fde047"/>
                <g class="wh-slices">
                    <path d="M310 190 L310 62 A128 128 0 0 1 400.5 99.5 Z" fill="#f43f5e"/>
                    <path d="M310 190 L400.5 99.5 A128 128 0 0 1 438 190 Z" fill="#fb923c"/>
                    <path d="M310 190 L438 190 A128 128 0 0 1 400.5 280.5 Z" fill="#facc15"/>
                    <path d="M310 190 L400.5 280.5 A128 128 0 0 1 310 318 Z" fill="#4ade80"/>
                    <path d="M310 190 L310 318 A128 128 0 0 1 219.5 280.5 Z" fill="#22d3ee"/>
                    <path d="M310 190 L219.5 280.5 A128 128 0 0 1 182 190 Z" fill="#818cf8"/>
                    <path d="M310 190 L182 190 A128 128 0 0 1 219.5 99.5 Z" fill="#c084fc"/>
                    <path d="M310 190 L219.5 99.5 A128 128 0 0 1 310 62 Z" fill="#f472b6"/>
                </g>
                <circle cx="310" cy="190" r="128" fill="none" stroke="#fff" stroke-width="7"/>
                <g fill="none" stroke="#ffffff90" stroke-width="2">
                    <path d="M310 190 L310 62M310 190 L400.5 99.5M310 190 L438 190M310 190 L400.5 280.5M310 190 L310 318M310 190 L219.5 280.5M310 190 L182 190M310 190 L219.5 99.5"/>
                </g>
                <g font-size="22" text-anchor="middle" dominant-baseline="central">
                    <text x="310" y="94">🍭</text><text x="376" y="122">⭐</text><text x="404" y="190">🎈</text>
                    <text x="376" y="258">🍀</text><text x="310" y="286">💎</text><text x="244" y="258">🎁</text>
                    <text x="216" y="190">🏅</text><text x="244" y="122">🎉</text>
                </g>
            </g>

            <!-- نشانگر -->
            <path class="wh-needle" d="M310 40 L327 76 L293 76 Z" fill="#fff" stroke="#be123c" stroke-width="3"/>
        </svg>

        <!-- چراغ‌های دورِ گردونه -->
        <div class="wh-lamps">
            <i class="wh-lamp b1"></i><i class="wh-lamp b2"></i><i class="wh-lamp b3"></i><i class="wh-lamp b4"></i>
            <i class="wh-lamp b5"></i><i class="wh-lamp b6"></i><i class="wh-lamp b7"></i><i class="wh-lamp b8"></i>
        </div>

        <span class="wh-actor wh-host">🤹</span>
        <span class="wh-actor wh-crowd">👏</span>
        <span class="wh-actor wh-tick">🎟️</span>

        <!-- مهرهٔ گردنده روی لبه -->
        <div class="wh-ball"><span>🔴</span></div>

        <!-- کودک در مرکزِ گردونه -->
        <div class="wh-hub"><span class="wh-avatar">{{char}}</span></div>

        <div class="wh-win">🏆 دورِ کامل!</div>
      </div>
    </div>

    <div class="wh-cheer"><span>📣</span><b>مجری: گردونه چرخید! نشانگر روی خانهٔ تازه ایستاد…</b></div>

    <section class="wh-progress">
        <div class="wh-progress-info">
            <span>چرخشِ گردونه تا دورِ کامل</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="wh-track">
            <span class="wh-fill"></span>
            <div class="wh-runner">{{char}}</div>
        </div>
        <div class="wh-progress-foot">
            <span>💡 هر چراغ یک پاسخِ درست · 🏆 دورِ کامل یعنی بردِ جایزهٔ بزرگ</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.wh-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#be123c,#4c0519 56%,#1c0209);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.wh-game *,.wh-game *:before,.wh-game *:after{box-sizing:border-box}
.wh-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.wh-brand{display:flex;align-items:center;gap:12px;min-width:0}
.wh-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#fecdd3);border:3px solid #fde047;border-radius:18px;box-shadow:0 8px 20px #0006;animation:wh-logo 3.6s ease-in-out infinite}
.wh-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.wh-brand p{margin:3px 0 0;color:#fecdd3;font-size:12px}
.wh-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.wh-score span{font-size:24px}
.wh-score strong,.wh-score small{display:block}
.wh-score strong{color:#fde047;font-size:22px}
.wh-score small{margin-top:3px;color:#fecdd3;font-size:9px}
.wh-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.wh-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.wh-stat>span{font-size:21px}
.wh-stat small,.wh-stat b{display:block}
.wh-stat small{color:#fecdd3;font-size:9px}
.wh-stat b{margin-top:3px;font-size:17px}
.wh-stage{--s:1;width:calc(620px*var(--s));height:calc(360px*var(--s));margin-inline:auto}
.wh-scene{position:relative;width:620px;height:360px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:radial-gradient(120% 100% at 50% 0%,#881337 0%,#3f0616 55%,#160208 100%)}
.wh-art{position:absolute;inset:0;width:620px;height:360px;pointer-events:none}
.wh-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:wh-trail .85s cubic-bezier(.3,.9,.3,1) both}
.wh-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.wh-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:wh-hop .85s cubic-bezier(.3,.9,.3,1) both}
.wh-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#ffe4e6);border:4px solid #fde047;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #fde04770}
.wh-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#fecdd3;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.wh-cheer span{font-size:17px;flex:none}
.wh-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.wh-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#fecdd3;font-size:11px}
.wh-progress-info b{color:#fde047}
.wh-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.wh-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#f43f5e,#fde047,#22c55e);border-radius:inherit;box-shadow:0 0 15px #fde04780;animation:wh-fill .85s cubic-bezier(.3,.9,.3,1) both}
.wh-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #fde047;border-radius:50%;box-shadow:0 6px 12px #0007;animation:wh-run .85s cubic-bezier(.3,.9,.3,1) both,wh-bob 1.6s 1s infinite}
.wh-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#d29aa6;font-size:9px}
.wh-progress-foot b{color:#fde047;font-size:11px}
@keyframes wh-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes wh-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes wh-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes wh-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes wh-bob{50%{transform:translateY(-5px)}}
@keyframes wh-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.wh-wheel{transform-box:view-box;transform-origin:310px 190px;transform:rotate(calc(var(--p)*3.6deg));animation:wh-spin .9s cubic-bezier(.25,.9,.25,1.05) both}
.wh-needle{transform-box:view-box;transform-origin:310px 76px;animation:wh-tick .9s ease-out both}
.wh-lamps{position:absolute;inset:0;pointer-events:none}
.wh-lamp{position:absolute;width:11px;height:11px;border-radius:50%;background:#7f1d1d;box-shadow:inset 0 0 3px #000}
.wh-lamp.on,.wh-lamp[class]{transition:none}
.wh-lamp.b1{top:36px;inset-inline-start:calc(620px - 316px);opacity:1;background:#fde047;box-shadow:0 0 10px #fde047}
.wh-lamp.b2{top:72px;inset-inline-start:calc(620px - 214px)}
.wh-lamp.b3{top:184px;inset-inline-start:calc(620px - 172px)}
.wh-lamp.b4{top:296px;inset-inline-start:calc(620px - 214px)}
.wh-lamp.b5{top:338px;inset-inline-start:calc(620px - 316px)}
.wh-lamp.b6{top:296px;inset-inline-start:calc(620px - 418px)}
.wh-lamp.b7{top:184px;inset-inline-start:calc(620px - 460px)}
.wh-lamp.b8{top:72px;inset-inline-start:calc(620px - 418px)}
.wh-lamp.b2{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 12)*99),1)}
.wh-lamp.b3{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 25)*99),1)}
.wh-lamp.b4{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 37)*99),1)}
.wh-lamp.b5{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 50)*99),1)}
.wh-lamp.b6{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 62)*99),1)}
.wh-lamp.b7{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 75)*99),1)}
.wh-lamp.b8{background:#fde047;box-shadow:0 0 10px #fde047;opacity:clamp(.18,calc((var(--p) - 87)*99),1)}
.wh-actor{position:absolute;filter:drop-shadow(0 4px 4px #00000066)}
.wh-host{top:272px;inset-inline-start:calc(620px - 574px);font-size:31px;animation:wh-host 2.4s ease-in-out infinite}
.wh-crowd{top:288px;inset-inline-start:calc(620px - 96px);font-size:27px;animation:wh-clap 1.5s ease-in-out infinite}
.wh-tick{top:44px;inset-inline-start:calc(620px - 96px);font-size:22px;animation:wh-host 3.2s ease-in-out infinite}
.wh-ball{position:absolute;top:0;left:0;width:0;height:0;z-index:8;offset-path:path("M310 66 A124 124 0 1 1 309.5 66");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:wh-ballm .9s cubic-bezier(.25,.9,.25,1.05) both}
.wh-ball span{position:absolute;left:-9px;top:-9px;font-size:17px;filter:drop-shadow(0 0 7px #fff)}
.wh-hub{position:absolute;top:164px;inset-inline-start:calc(620px - 336px);width:52px;height:52px;z-index:9;display:grid;place-items:center;animation:wh-hub .9s cubic-bezier(.25,.9,.25,1.05) both}
.wh-avatar{display:grid;place-items:center;width:52px;height:52px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#ffe4e6);border:4px solid #fde047;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #fde04780}
.wh-win{position:absolute;top:150px;inset-inline-start:calc(620px - 388px);width:156px;text-align:center;padding:7px 10px;font-size:15px;font-weight:900;color:#4c0519;background:#fde047;border-radius:20px;box-shadow:0 0 26px #fde047;opacity:clamp(0,calc((var(--p) - 99)*99),1);z-index:10;animation:wh-pop 1.2s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.wh-ball{display:none}}
@keyframes wh-spin{from{transform:rotate(calc(var(--from)*3.6deg))}to{transform:rotate(calc(var(--p)*3.6deg))}}
@keyframes wh-ballm{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes wh-tick{0%{transform:rotate(0)}18%{transform:rotate(-15deg)}40%{transform:rotate(9deg)}70%{transform:rotate(-4deg)}100%{transform:rotate(0)}}
@keyframes wh-hub{0%{transform:scale(1)}40%{transform:scale(1.14)}100%{transform:scale(1)}}
@keyframes wh-host{0%,100%{transform:translateY(0) rotate(-4deg)}50%{transform:translateY(-8px) rotate(5deg)}}
@keyframes wh-clap{50%{transform:scale(1.14)}}
@keyframes wh-pop{50%{transform:scale(1.07)}}
@media(max-width:1000px){.wh-stage{--s:.84}}
@media(max-width:820px){.wh-game{padding:12px;border-radius:22px}.wh-stage{--s:.68}.wh-brand h2{font-size:18px}.wh-brand p{font-size:9px}.wh-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.wh-stage{--s:.6}.wh-score{min-width:78px;padding:7px 10px}.wh-score>span{display:none}.wh-stat{display:block;padding:7px 2px;text-align:center}.wh-stat>span{font-size:16px}.wh-stat small{font-size:7px}.wh-stat b{font-size:14px}}
@media(max-width:560px){.wh-stage{--s:.52}.wh-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.wh-stage{--s:.46}}
@media(max-width:410px){.wh-stage{--s:.4}}
@media(max-width:350px){.wh-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.wh-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-speed', 'name' => 'پیستِ سرعتِ دانایی', 'icon' => '🏁', 'sort' => 16,
                'description' => 'مسابقهٔ اتومبیل‌رانی: با هر پاسخِ درست ماشین جلو می‌رود، عقربهٔ سرعت‌سنج بالا می‌رود و از حریف جلو می‌زنی تا به پرچمِ شطرنجی برسی.',
                'board_html' => <<<'BOARD_HTML'
<div class="rc-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="rc-head">
        <div class="rc-brand">
            <div class="rc-logo">🏁</div>
            <div>
                <h2>پیستِ سرعتِ دانایی</h2>
                <p>هر پاسخِ درست، یک دور جلوتر از حریف!</p>
            </div>
        </div>
        <div class="rc-score">
            <span>🏎️</span>
            <div><strong>{{score}}</strong><small>امتیازِ سرعت</small></div>
        </div>
    </header>

    <section class="rc-stats">
        <div class="rc-stat"><span>🛞</span><div><small>دورهای رفته</small><b>{{pos}}</b></div></div>
        <div class="rc-stat"><span>🏁</span><div><small>کلِ مسابقه</small><b>{{total}}</b></div></div>
        <div class="rc-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="rc-stage">
      <div class="rc-scene">
        <svg class="rc-art" viewBox="0 0 620 330" aria-hidden="true">
            <defs>
                <linearGradient id="rcSky" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="112">
                    <stop offset="0" stop-color="#0c4a6e"/><stop offset="1" stop-color="#f59e0b" stop-opacity=".45"/>
                </linearGradient>
            </defs>
            <rect width="620" height="112" fill="url(#rcSky)"/>
            <!-- شهر در دوردست -->
            <g fill="#0b2430" opacity=".9">
                <rect x="20" y="58" width="34" height="54"/><rect x="62" y="40" width="26" height="72"/>
                <rect x="96" y="70" width="40" height="42"/><rect x="150" y="48" width="30" height="64"/>
                <rect x="440" y="66" width="36" height="46"/><rect x="484" y="44" width="28" height="68"/>
                <rect x="520" y="72" width="44" height="40"/><rect x="574" y="54" width="26" height="58"/>
            </g>
            <g fill="#fde68a" opacity=".75">
                <rect x="66" y="50" width="5" height="6"/><rect x="76" y="62" width="5" height="6"/>
                <rect x="156" y="58" width="5" height="6"/><rect x="490" y="54" width="5" height="6"/>
                <rect x="498" y="72" width="5" height="6"/><rect x="580" y="64" width="5" height="6"/>
            </g>
            <!-- جایگاه تماشاگر -->
            <rect y="112" width="620" height="20" fill="#475569"/>
            <g fill="#e2e8f0" opacity=".8">
                <circle cx="40" cy="122" r="4"/><circle cx="80" cy="120" r="4"/><circle cx="120" cy="123" r="4"/>
                <circle cx="160" cy="120" r="4"/><circle cx="200" cy="123" r="4"/><circle cx="240" cy="120" r="4"/>
                <circle cx="280" cy="123" r="4"/><circle cx="320" cy="120" r="4"/><circle cx="360" cy="123" r="4"/>
                <circle cx="400" cy="120" r="4"/><circle cx="440" cy="123" r="4"/><circle cx="480" cy="120" r="4"/>
                <circle cx="520" cy="123" r="4"/><circle cx="560" cy="120" r="4"/><circle cx="600" cy="123" r="4"/>
            </g>
            <!-- آسفالت -->
            <rect y="132" width="620" height="152" fill="#1f2937"/>
            <path d="M0 140H620M0 276H620" stroke="#f8fafc" stroke-width="4"/>
            <g stroke="#facc15" stroke-width="4" stroke-dasharray="26 22" class="rc-lane">
                <path d="M0 176H620"/><path d="M0 240H620"/>
            </g>
            <path d="M0 208H620" stroke="#f8fafc" stroke-width="2" stroke-dasharray="16 26" opacity=".5"/>
            <!-- چمنِ پایین -->
            <rect y="284" width="620" height="46" fill="#14532d"/>
            <g fill="#166534"><circle cx="70" cy="308" r="9"/><circle cx="250" cy="316" r="7"/><circle cx="470" cy="306" r="10"/></g>

            <!-- خطِ پایانِ شطرنجی -->
            <g>
                <rect x="30" y="132" width="26" height="152" fill="#f8fafc"/>
                <g fill="#111827">
                    <rect x="30" y="132" width="13" height="19"/><rect x="43" y="151" width="13" height="19"/>
                    <rect x="30" y="170" width="13" height="19"/><rect x="43" y="189" width="13" height="19"/>
                    <rect x="30" y="208" width="13" height="19"/><rect x="43" y="227" width="13" height="19"/>
                    <rect x="30" y="246" width="13" height="19"/><rect x="43" y="265" width="13" height="19"/>
                </g>
            </g>

            <!-- ردِّ لاستیک -->
            <path class="rc-trail" pathLength="100" stroke="#f97316" stroke-width="6" stroke-dasharray="100" d="M566 244 L54 244" opacity=".75"/>
        </svg>

        <!-- سرعت‌سنج -->
        <div class="rc-gauge">
            <svg viewBox="0 0 100 58">
                <path d="M8 52 A42 42 0 0 1 92 52" fill="none" stroke="#0f172a" stroke-width="11" stroke-linecap="round"/>
                <path d="M8 52 A42 42 0 0 1 92 52" fill="none" stroke="#f97316" stroke-width="11" stroke-linecap="round"
                      pathLength="100" stroke-dasharray="100" stroke-dashoffset="calc(100px - var(--p)*1px)"/>
                <circle cx="50" cy="52" r="5" fill="#e2e8f0"/>
            </svg>
            <i class="rc-needle"></i>
            <b>{{percent}}٪</b>
        </div>

        <span class="rc-actor rc-flagman">🏴</span>
        <span class="rc-actor rc-crew">🧑‍🔧</span>
        <span class="rc-actor rc-light">🚦</span>
        <span class="rc-speedline s1"></span><span class="rc-speedline s2"></span><span class="rc-speedline s3"></span>

        <!-- حریف -->
        <div class="rc-rival"><span>🏎️</span></div>
        <!-- ماشینِ کودک -->
        <div class="rc-car"><span>🏎️</span></div>
        <div class="rc-hero"><div class="rc-hero-in"><span class="rc-avatar">{{char}}</span><span class="rc-helm">⛑️</span></div></div>

        <div class="rc-win">🏁 خطِ پایان!</div>
      </div>
    </div>

    <div class="rc-cheer"><span>📻</span><b>بی‌سیمِ تیم: سرعتت عالی است! حریف را پشتِ سر گذاشتی…</b></div>

    <section class="rc-progress">
        <div class="rc-progress-info">
            <span>پیست تا خطِ پایان</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="rc-track">
            <span class="rc-fill"></span>
            <div class="rc-runner">{{char}}</div>
        </div>
        <div class="rc-progress-foot">
            <span>🚦 هر پاسخِ درست یک شتاب · 🏁 پرچمِ شطرنجی در انتهای پیست</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.rc-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#0891b2,#083344 55%,#03161d);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.rc-game *,.rc-game *:before,.rc-game *:after{box-sizing:border-box}
.rc-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.rc-brand{display:flex;align-items:center;gap:12px;min-width:0}
.rc-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#a5f3fc);border:3px solid #f97316;border-radius:18px;box-shadow:0 8px 20px #0006;animation:rc-logo 3.6s ease-in-out infinite}
.rc-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.rc-brand p{margin:3px 0 0;color:#a5f3fc;font-size:12px}
.rc-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.rc-score span{font-size:24px}
.rc-score strong,.rc-score small{display:block}
.rc-score strong{color:#f97316;font-size:22px}
.rc-score small{margin-top:3px;color:#a5f3fc;font-size:9px}
.rc-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.rc-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.rc-stat>span{font-size:21px}
.rc-stat small,.rc-stat b{display:block}
.rc-stat small{color:#a5f3fc;font-size:9px}
.rc-stat b{margin-top:3px;font-size:17px}
.rc-stage{--s:1;width:calc(620px*var(--s));height:calc(330px*var(--s));margin-inline:auto}
.rc-scene{position:relative;width:620px;height:330px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:linear-gradient(180deg,#0e2a3a 0 34%,#334155 34% 40%,#1f2937 40% 86%,#14532d 86% 100%)}
.rc-art{position:absolute;inset:0;width:620px;height:330px;pointer-events:none}
.rc-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:rc-trail .85s cubic-bezier(.3,.9,.3,1) both}
.rc-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.rc-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:rc-hop .85s cubic-bezier(.3,.9,.3,1) both}
.rc-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#cffafe);border:4px solid #f97316;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #f9731670}
.rc-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#a5f3fc;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.rc-cheer span{font-size:17px;flex:none}
.rc-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.rc-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#a5f3fc;font-size:11px}
.rc-progress-info b{color:#f97316}
.rc-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.rc-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#06b6d4,#f97316,#ef4444);border-radius:inherit;box-shadow:0 0 15px #f9731680;animation:rc-fill .85s cubic-bezier(.3,.9,.3,1) both}
.rc-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #f97316;border-radius:50%;box-shadow:0 6px 12px #0007;animation:rc-run .85s cubic-bezier(.3,.9,.3,1) both,rc-bob 1.6s 1s infinite}
.rc-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#7fa8b5;font-size:9px}
.rc-progress-foot b{color:#f97316;font-size:11px}
@keyframes rc-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes rc-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes rc-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes rc-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes rc-bob{50%{transform:translateY(-5px)}}
@keyframes rc-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.rc-lane{animation:rc-dash .6s linear infinite}
.rc-gauge{position:absolute;top:12px;inset-inline-start:calc(620px - 610px);width:100px;padding:6px 6px 4px;background:#020617cc;border:2px solid #f9731666;border-radius:14px;text-align:center}
.rc-gauge svg{display:block;width:88px;margin:0 auto}
.rc-gauge b{display:block;margin-top:2px;color:#f97316;font-size:12px}
.rc-needle{position:absolute;bottom:22px;left:50%;width:3px;height:30px;background:#f8fafc;border-radius:2px;transform-origin:bottom center;transform:rotate(calc(-90deg + var(--p)*1.8deg));animation:rc-needle .85s cubic-bezier(.3,.9,.3,1) both}
.rc-actor{position:absolute;filter:drop-shadow(0 4px 4px #00000066)}
.rc-flagman{top:96px;inset-inline-start:calc(620px - 74px);font-size:24px;animation:rc-wave 1.1s ease-in-out infinite}
.rc-crew{top:292px;inset-inline-start:calc(620px - 148px);font-size:22px;animation:rc-bobx 2.4s ease-in-out infinite}
.rc-light{top:92px;inset-inline-start:calc(620px - 560px);font-size:23px;animation:rc-flick 2.2s ease-in-out infinite}
.rc-speedline{position:absolute;height:3px;background:linear-gradient(90deg,transparent,#a5f3fc);border-radius:3px;opacity:.65;animation:rc-zip .8s linear infinite}
.rc-speedline.s1{top:162px;inset-inline-start:calc(620px - 620px);width:90px}
.rc-speedline.s2{top:206px;inset-inline-start:calc(620px - 600px);width:130px;animation-delay:.25s}
.rc-speedline.s3{top:262px;inset-inline-start:calc(620px - 590px);width:70px;animation-delay:.5s}
.rc-rival{position:absolute;top:0;left:0;width:0;height:0;z-index:6;offset-path:path("M566 196 L54 196");offset-rotate:0deg;offset-distance:calc(max(0,calc(var(--p) - 15))*1%);animation:rc-rivalm .85s cubic-bezier(.3,.9,.3,1) both}
.rc-rival span{position:absolute;left:-17px;top:-15px;font-size:29px;transform:scaleX(-1);filter:drop-shadow(0 5px 3px #0007) grayscale(.35)}
.rc-car{position:absolute;top:0;left:0;width:0;height:0;z-index:7;offset-path:path("M566 244 L54 244");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:rc-move .85s cubic-bezier(.3,.9,.3,1) both}
.rc-car span{position:absolute;left:-20px;top:4px;font-size:34px;transform:scaleX(-1);filter:drop-shadow(0 6px 4px #0008)}
.rc-hero{offset-path:path("M566 244 L54 244");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:rc-move .85s cubic-bezier(.3,.9,.3,1) both}
.rc-hero-in{top:-46px}
.rc-avatar{width:44px;height:44px;font-size:25px;border-color:#f97316;box-shadow:0 8px 16px #000a,0 0 20px #f9731670}
.rc-helm{position:absolute;top:-15px;font-size:17px;filter:drop-shadow(0 2px 2px #0007)}
.rc-win{position:absolute;top:150px;inset-inline-start:calc(620px - 402px);width:172px;text-align:center;padding:7px 10px;font-size:15px;font-weight:900;color:#052e2b;background:#5eead4;border-radius:20px;box-shadow:0 0 26px #2dd4bf;opacity:clamp(0,calc((var(--p) - 99)*99),1);z-index:10;animation:rc-pop 1.2s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.rc-hero{top:200px;inset-inline-start:calc(54px + (512px - 54px)*(100 - var(--p))/100)}.rc-car,.rc-rival{display:none}}
@keyframes rc-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes rc-rivalm{from{offset-distance:calc(max(0,calc(var(--from) - 15))*1%)}to{offset-distance:calc(max(0,calc(var(--p) - 15))*1%)}}
@keyframes rc-needle{from{transform:rotate(calc(-90deg + var(--from)*1.8deg))}to{transform:rotate(calc(-90deg + var(--p)*1.8deg))}}
@keyframes rc-dash{to{stroke-dashoffset:-48}}
@keyframes rc-zip{0%{opacity:0;transform:translateX(0)}20%{opacity:.7}100%{opacity:0;transform:translateX(160px)}}
@keyframes rc-wave{0%,100%{transform:rotate(-16deg)}50%{transform:rotate(18deg)}}
@keyframes rc-bobx{50%{transform:translateY(-5px)}}
@keyframes rc-flick{0%,100%{opacity:1}50%{opacity:.55}}
@keyframes rc-pop{50%{transform:scale(1.07)}}
@media(max-width:1000px){.rc-stage{--s:.84}}
@media(max-width:820px){.rc-game{padding:12px;border-radius:22px}.rc-stage{--s:.68}.rc-brand h2{font-size:18px}.rc-brand p{font-size:9px}.rc-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.rc-stage{--s:.6}.rc-score{min-width:78px;padding:7px 10px}.rc-score>span{display:none}.rc-stat{display:block;padding:7px 2px;text-align:center}.rc-stat>span{font-size:16px}.rc-stat small{font-size:7px}.rc-stat b{font-size:14px}}
@media(max-width:560px){.rc-stage{--s:.52}.rc-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.rc-stage{--s:.46}}
@media(max-width:410px){.rc-stage{--s:.4}}
@media(max-width:350px){.rc-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.rc-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-mountain', 'name' => 'فتحِ قلهٔ دانایی', 'icon' => '🏔️', 'sort' => 17,
                'description' => 'صعود از مسیرِ مارپیچِ کوهستان؛ با هر پاسخِ درست یک پیچ بالاتر می‌روی، اردوگاه‌ها را رد می‌کنی و پرچم را روی قله می‌کاری.',
                'board_html' => <<<'BOARD_HTML'
<div class="mt-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="mt-head">
        <div class="mt-brand">
            <div class="mt-logo">🏔️</div>
            <div>
                <h2>فتحِ قلهٔ دانایی</h2>
                <p>هر پاسخِ درست، یک پیچ بالاتر!</p>
            </div>
        </div>
        <div class="mt-score">
            <span>🧗</span>
            <div><strong>{{score}}</strong><small>امتیازِ صعود</small></div>
        </div>
    </header>

    <section class="mt-stats">
        <div class="mt-stat"><span>📍</span><div><small>ارتفاعِ فعلی</small><b>{{pos}}</b></div></div>
        <div class="mt-stat"><span>🏔️</span><div><small>تا قله</small><b>{{total}}</b></div></div>
        <div class="mt-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="mt-stage">
      <div class="mt-scene">
        <svg class="mt-art" viewBox="0 0 620 380" aria-hidden="true">
            <defs>
                <linearGradient id="mtRock" gradientUnits="userSpaceOnUse" x1="0" y1="40" x2="0" y2="380">
                    <stop offset="0" stop-color="#94a3b8"/><stop offset=".45" stop-color="#64748b"/><stop offset="1" stop-color="#3f4b5e"/>
                </linearGradient>
                <linearGradient id="mtRock2" gradientUnits="userSpaceOnUse" x1="0" y1="90" x2="0" y2="380">
                    <stop offset="0" stop-color="#7c8ba1"/><stop offset="1" stop-color="#2b3647"/>
                </linearGradient>
            </defs>

            <circle cx="88" cy="66" r="30" fill="#fde68a" opacity=".9"/>
            <circle cx="88" cy="66" r="44" fill="#fde68a" opacity=".22"/>

            <!-- قلهٔ دورتر -->
            <path d="M0 380 L120 150 L214 262 L300 176 L410 380 Z" fill="url(#mtRock2)" opacity=".85"/>
            <!-- قلهٔ اصلی -->
            <path d="M40 380 L310 46 L590 380 Z" fill="url(#mtRock)"/>
            <!-- برف روی قله -->
            <path d="M310 46 L376 128 L344 118 L322 140 L296 112 L268 130 L244 122 Z" fill="#f8fafc"/>
            <!-- درزهای صخره -->
            <g stroke="#0f172a" stroke-width="2" opacity=".28" fill="none">
                <path d="M240 200 l40 46 l-24 40M392 214 l-34 44 l28 42M186 290 l52 30M430 300 l-46 26"/>
            </g>

            <!-- مسیرِ کاملِ صعود -->
            <path d="M554 356 L130 356 L130 302 L460 302 L460 248 L215 248 L215 194 L370 194 L370 140 L295 140 L310 62" fill="none" stroke="#1e293b" stroke-width="9" opacity=".3" stroke-linejoin="round" stroke-linecap="round"/>
            <path d="M554 356 L130 356 L130 302 L460 302 L460 248 L215 248 L215 194 L370 194 L370 140 L295 140 L310 62" fill="none" stroke="#e2e8f0" stroke-width="4" opacity=".55" stroke-dasharray="3 10" stroke-linecap="round"/>
            <!-- مسیرِ طی‌شده -->
            <path class="mt-trail" pathLength="100" stroke="#fbbf24" stroke-width="6" stroke-dasharray="100" d="M554 356 L130 356 L130 302 L460 302 L460 248 L215 248 L215 194 L370 194 L370 140 L295 140 L310 62"/>

            <!-- درخت‌ها در دامنه -->
            <g fill="#166534">
                <path d="M96 366 l14 -26 l14 26 z"/><path d="M104 352 l6 -18 l6 18 z"/>
                <path d="M520 368 l13 -24 l13 24 z"/><path d="M528 356 l5 -16 l5 16 z"/>
                <path d="M146 374 l11 -20 l11 20 z"/>
            </g>
        </svg>

        <span class="mt-actor mt-eagle">🦅</span>
        <span class="mt-actor mt-goat">🐐</span>
        <span class="mt-actor mt-cloud c1">☁️</span>
        <span class="mt-actor mt-cloud c2">☁️</span>

        <!-- اردوگاه‌ها -->
        <span class="mt-camp cp1">⛺</span>
        <span class="mt-camp cp2">⛺</span>
        <span class="mt-camp cp3">🧗</span>

        <!-- قله -->
        <span class="mt-flag">🚩</span>
        <div class="mt-alt"><b>{{percent}}٪</b><small>ارتفاع</small></div>

        <div class="mt-hero"><div class="mt-hero-in"><span class="mt-avatar">{{char}}</span><span class="mt-pack">🎒</span></div></div>

        <div class="mt-win">🏔️ قله فتح شد!</div>
      </div>
    </div>

    <div class="mt-cheer"><span>📻</span><b>بی‌سیمِ اردوگاه: هوا صاف است کوهنورد — به پیچِ بعدی برس!</b></div>

    <section class="mt-progress">
        <div class="mt-progress-info">
            <span>مسیرِ صعود تا قله</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="mt-track">
            <span class="mt-fill"></span>
            <div class="mt-runner">{{char}}</div>
        </div>
        <div class="mt-progress-foot">
            <span>⛺ اردوگاه‌ها ایستگاهِ استراحت‌اند · 🚩 پرچم روی قله منتظرِ توست</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.mt-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#0ea5e9,#075985 55%,#052030);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.mt-game *,.mt-game *:before,.mt-game *:after{box-sizing:border-box}
.mt-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.mt-brand{display:flex;align-items:center;gap:12px;min-width:0}
.mt-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#bae6fd);border:3px solid #fbbf24;border-radius:18px;box-shadow:0 8px 20px #0006;animation:mt-logo 3.6s ease-in-out infinite}
.mt-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.mt-brand p{margin:3px 0 0;color:#bae6fd;font-size:12px}
.mt-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.mt-score span{font-size:24px}
.mt-score strong,.mt-score small{display:block}
.mt-score strong{color:#fbbf24;font-size:22px}
.mt-score small{margin-top:3px;color:#bae6fd;font-size:9px}
.mt-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.mt-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.mt-stat>span{font-size:21px}
.mt-stat small,.mt-stat b{display:block}
.mt-stat small{color:#bae6fd;font-size:9px}
.mt-stat b{margin-top:3px;font-size:17px}
.mt-stage{--s:1;width:calc(620px*var(--s));height:calc(380px*var(--s));margin-inline:auto}
.mt-scene{position:relative;width:620px;height:380px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:linear-gradient(180deg,#0c4a6e 0%,#0ea5e9 34%,#7dd3fc 62%,#e0f2fe 100%)}
.mt-art{position:absolute;inset:0;width:620px;height:380px;pointer-events:none}
.mt-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:mt-trail .85s cubic-bezier(.3,.9,.3,1) both}
.mt-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.mt-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:mt-hop .85s cubic-bezier(.3,.9,.3,1) both}
.mt-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#e0f2fe);border:4px solid #fbbf24;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #fbbf2470}
.mt-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#bae6fd;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.mt-cheer span{font-size:17px;flex:none}
.mt-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.mt-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#bae6fd;font-size:11px}
.mt-progress-info b{color:#fbbf24}
.mt-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.mt-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#38bdf8,#a3e635,#fbbf24);border-radius:inherit;box-shadow:0 0 15px #fbbf2480;animation:mt-fill .85s cubic-bezier(.3,.9,.3,1) both}
.mt-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #fbbf24;border-radius:50%;box-shadow:0 6px 12px #0007;animation:mt-run .85s cubic-bezier(.3,.9,.3,1) both,mt-bob 1.6s 1s infinite}
.mt-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#8ab4c9;font-size:9px}
.mt-progress-foot b{color:#fbbf24;font-size:11px}
@keyframes mt-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes mt-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes mt-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes mt-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes mt-bob{50%{transform:translateY(-5px)}}
@keyframes mt-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.mt-actor{position:absolute;filter:drop-shadow(0 4px 4px #00000044)}
.mt-eagle{top:86px;inset-inline-start:calc(620px - 172px);font-size:23px;animation:mt-fly 6s ease-in-out infinite}
.mt-goat{top:322px;inset-inline-start:calc(620px - 546px);font-size:21px;animation:mt-bobx 3s ease-in-out infinite}
.mt-cloud{font-size:26px;opacity:.85}
.mt-cloud.c1{top:126px;inset-inline-start:calc(620px - 600px);animation:mt-drift 11s linear infinite}
.mt-cloud.c2{top:186px;inset-inline-start:calc(620px - 118px);font-size:21px;animation:mt-drift 15s 2s linear infinite}
.mt-camp{position:absolute;font-size:21px;filter:drop-shadow(0 3px 3px #00000055)}
.mt-camp.cp1{top:286px;inset-inline-start:calc(620px - 478px);opacity:clamp(.3,calc((var(--p) - 24)*99),1)}
.mt-camp.cp2{top:206px;inset-inline-start:calc(620px - 236px);opacity:clamp(.3,calc((var(--p) - 50)*99),1)}
.mt-camp.cp3{top:124px;inset-inline-start:calc(620px - 392px);opacity:clamp(.3,calc((var(--p) - 76)*99),1)}
.mt-flag{position:absolute;top:24px;inset-inline-start:calc(620px - 330px);font-size:25px;filter:drop-shadow(0 3px 3px #0006);animation:mt-wave 1.6s ease-in-out infinite}
.mt-alt{position:absolute;top:18px;inset-inline-start:calc(620px - 600px);padding:5px 11px;text-align:center;background:#0c4a6ecc;border:2px solid #fbbf2466;border-radius:12px}
.mt-alt b{display:block;color:#fbbf24;font-size:15px}
.mt-alt small{display:block;color:#bae6fd;font-size:8px}
.mt-hero{offset-path:path("M554 356 L130 356 L130 302 L460 302 L460 248 L215 248 L215 194 L370 194 L370 140 L295 140 L310 62");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:mt-move .85s cubic-bezier(.3,.9,.3,1) both}
.mt-avatar{border-color:#fbbf24;box-shadow:0 8px 16px #000a,0 0 0 4px #ffffff40}
.mt-pack{position:absolute;bottom:-2px;right:-6px;font-size:17px;filter:drop-shadow(0 2px 2px #0006)}
.mt-win{position:absolute;top:196px;inset-inline-start:calc(620px - 396px);width:172px;text-align:center;padding:7px 10px;font-size:15px;font-weight:900;color:#053345;background:#7dd3fc;border-radius:20px;box-shadow:0 0 26px #38bdf8;opacity:clamp(0,calc((var(--p) - 99)*99),1);z-index:10;animation:mt-pop 1.2s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.mt-hero{top:330px;inset-inline-start:calc(60px + (500px - 60px)*(100 - var(--p))/100)}}
@keyframes mt-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes mt-fly{0%,100%{transform:translate(0,0)}50%{transform:translate(-40px,22px)}}
@keyframes mt-bobx{50%{transform:translateY(-6px)}}
@keyframes mt-drift{from{transform:translateX(0)}to{transform:translateX(-180px)}}
@keyframes mt-wave{0%,100%{transform:rotate(-8deg)}50%{transform:rotate(10deg)}}
@keyframes mt-pop{50%{transform:scale(1.07)}}
@media(max-width:1000px){.mt-stage{--s:.84}}
@media(max-width:820px){.mt-game{padding:12px;border-radius:22px}.mt-stage{--s:.68}.mt-brand h2{font-size:18px}.mt-brand p{font-size:9px}.mt-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.mt-stage{--s:.6}.mt-score{min-width:78px;padding:7px 10px}.mt-score>span{display:none}.mt-stat{display:block;padding:7px 2px;text-align:center}.mt-stat>span{font-size:16px}.mt-stat small{font-size:7px}.mt-stat b{font-size:14px}}
@media(max-width:560px){.mt-stage{--s:.52}.mt-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.mt-stage{--s:.46}}
@media(max-width:410px){.mt-stage{--s:.4}}
@media(max-width:350px){.mt-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.mt-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-rocket', 'name' => 'پروازِ موشک تا ماه', 'icon' => '🌙', 'sort' => 18,
                'description' => 'شمارشِ معکوس تمام شد! با هر پاسخِ درست موشک بالاتر می‌رود، مرحله‌های پرواز جدا می‌شوند و در پاسخِ آخر روی ماه می‌نشینی.',
                'board_html' => <<<'BOARD_HTML'
<div class="rk-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="rk-head">
        <div class="rk-brand">
            <div class="rk-logo">🌙</div>
            <div>
                <h2>پروازِ موشک تا ماه</h2>
                <p>هر پاسخِ درست، چند کیلومتر بالاتر!</p>
            </div>
        </div>
        <div class="rk-score">
            <span>🔥</span>
            <div><strong>{{score}}</strong><small>سوخت</small></div>
        </div>
    </header>

    <section class="rk-stats">
        <div class="rk-stat"><span>📏</span><div><small>ارتفاع</small><b>{{pos}}</b></div></div>
        <div class="rk-stat"><span>🌙</span><div><small>تا ماه</small><b>{{total}}</b></div></div>
        <div class="rk-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="rk-stage">
      <div class="rk-scene">
        <svg class="rk-art" viewBox="0 0 620 380" aria-hidden="true">
            <defs>
                <radialGradient id="rkMoon" cx=".36" cy=".32" r=".8">
                    <stop offset="0" stop-color="#fffbeb"/><stop offset="1" stop-color="#cbd5e1"/>
                </radialGradient>
                <radialGradient id="rkEarth" cx=".4" cy=".2" r=".9">
                    <stop offset="0" stop-color="#93c5fd"/><stop offset=".55" stop-color="#2563eb"/><stop offset="1" stop-color="#1e3a8a"/>
                </radialGradient>
                <linearGradient id="rkPath" gradientUnits="userSpaceOnUse" x1="318" y1="356" x2="286" y2="54">
                    <stop offset="0" stop-color="#f97316"/><stop offset=".5" stop-color="#a78bfa"/><stop offset="1" stop-color="#fcd34d"/>
                </linearGradient>
            </defs>

            <g class="rk-stars" fill="#fff">
                <circle cx="56" cy="40" r="1.8"/><circle cx="148" cy="96" r="1.3"/><circle cx="236" cy="34" r="1.6"/>
                <circle cx="404" cy="72" r="1.9"/><circle cx="492" cy="30" r="1.4"/><circle cx="566" cy="104" r="1.7"/>
                <circle cx="96" cy="164" r="1.4"/><circle cx="470" cy="150" r="1.5"/><circle cx="540" cy="212" r="1.2"/>
                <circle cx="118" cy="236" r="1.3"/><circle cx="392" cy="212" r="1.4"/><circle cx="196" cy="128" r="1.1"/>
            </g>

            <!-- ماه -->
            <g>
                <circle cx="122" cy="74" r="56" fill="url(#rkMoon)"/>
                <g fill="#94a3b8" opacity=".55">
                    <circle cx="104" cy="58" r="11"/><circle cx="142" cy="88" r="8"/>
                    <circle cx="112" cy="98" r="6"/><circle cx="150" cy="52" r="5"/>
                </g>
                <circle cx="122" cy="74" r="70" fill="#fffbeb" opacity=".1"/>
            </g>

            <!-- زمین -->
            <circle cx="310" cy="580" r="250" fill="url(#rkEarth)"/>
            <path d="M120 430 q90 -34 190 -12 q120 26 200 4" fill="none" stroke="#86efac" stroke-width="16" opacity=".5"/>
            <ellipse cx="310" cy="352" rx="300" ry="24" fill="#93c5fd" opacity=".25"/>

            <!-- مسیرِ پرواز -->
            <path d="M318 356 C 318 304, 282 252, 306 192 C 326 140, 290 108, 286 54" fill="none" stroke="#ffffff28" stroke-width="3" stroke-dasharray="5 10"/>
            <path class="rk-trail" pathLength="100" stroke="url(#rkPath)" stroke-width="5" stroke-dasharray="100" d="M318 356 C 318 304, 282 252, 306 192 C 326 140, 290 108, 286 54"/>

            <!-- خط‌کشِ ارتفاع -->
            <g stroke="#c7d2fe" stroke-width="2" opacity=".45">
                <path d="M596 60 V352"/>
                <path d="M584 60H596M584 133H596M584 206H596M584 279H596M584 352H596"/>
            </g>
            <g fill="#c7d2fe" font-size="9" text-anchor="end" opacity=".7">
                <text x="580" y="63">۱۰۰</text><text x="580" y="136">۷۵</text>
                <text x="580" y="209">۵۰</text><text x="580" y="282">۲۵</text><text x="580" y="355">۰</text>
            </g>
        </svg>

        <span class="rk-actor rk-sat">🛰️</span>
        <span class="rk-actor rk-star1">✨</span>
        <span class="rk-actor rk-ctrl">🗼</span>
        <span class="rk-actor rk-alien">👾</span>

        <!-- مرحله‌های جداشده -->
        <span class="rk-stage-off s1">💨</span>
        <span class="rk-stage-off s2">💨</span>

        <!-- نشانگرِ ارتفاع -->
        <div class="rk-gauge"><b>{{percent}}٪</b><i></i></div>

        <div class="rk-ship"><span class="rk-rocket">🚀</span><span class="rk-flame">🔥</span></div>
        <div class="rk-hero"><div class="rk-hero-in"><span class="rk-avatar">{{char}}</span><span class="rk-helmet"></span></div></div>

        <div class="rk-win">🌙 فرود روی ماه!</div>
      </div>
    </div>

    <div class="rk-cheer"><span>🛰️</span><b>مرکزِ پرتاب: موتورها پایدارند — ادامه بده فضانورد!</b></div>

    <section class="rk-progress">
        <div class="rk-progress-info">
            <span>ارتفاع تا سطحِ ماه</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="rk-track">
            <span class="rk-fill"></span>
            <div class="rk-runner">{{char}}</div>
        </div>
        <div class="rk-progress-foot">
            <span>🛰️ هر ایستگاه یک مرحلهٔ پرواز · 🌙 فرود روی ماه در پاسخِ آخر</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.rk-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#1d4ed8,#111c44 55%,#040713);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.rk-game *,.rk-game *:before,.rk-game *:after{box-sizing:border-box}
.rk-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.rk-brand{display:flex;align-items:center;gap:12px;min-width:0}
.rk-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#dbeafe);border:3px solid #fcd34d;border-radius:18px;box-shadow:0 8px 20px #0006;animation:rk-logo 3.6s ease-in-out infinite}
.rk-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.rk-brand p{margin:3px 0 0;color:#c7d2fe;font-size:12px}
.rk-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.rk-score span{font-size:24px}
.rk-score strong,.rk-score small{display:block}
.rk-score strong{color:#fcd34d;font-size:22px}
.rk-score small{margin-top:3px;color:#c7d2fe;font-size:9px}
.rk-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.rk-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.rk-stat>span{font-size:21px}
.rk-stat small,.rk-stat b{display:block}
.rk-stat small{color:#c7d2fe;font-size:9px}
.rk-stat b{margin-top:3px;font-size:17px}
.rk-stage{--s:1;width:calc(620px*var(--s));height:calc(380px*var(--s));margin-inline:auto}
.rk-scene{position:relative;width:620px;height:380px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:linear-gradient(180deg,#02030c 0%,#0b1338 44%,#1e3a8a 76%,#3b82f6 100%)}
.rk-art{position:absolute;inset:0;width:620px;height:380px;pointer-events:none}
.rk-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:rk-trail .85s cubic-bezier(.3,.9,.3,1) both}
.rk-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.rk-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:rk-hop .85s cubic-bezier(.3,.9,.3,1) both}
.rk-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#e0e7ff);border:4px solid #fcd34d;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #fcd34d70}
.rk-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#c7d2fe;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.rk-cheer span{font-size:17px;flex:none}
.rk-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.rk-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#c7d2fe;font-size:11px}
.rk-progress-info b{color:#fcd34d}
.rk-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.rk-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#3b82f6,#a78bfa,#fcd34d);border-radius:inherit;box-shadow:0 0 15px #fcd34d80;animation:rk-fill .85s cubic-bezier(.3,.9,.3,1) both}
.rk-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #fcd34d;border-radius:50%;box-shadow:0 6px 12px #0007;animation:rk-run .85s cubic-bezier(.3,.9,.3,1) both,rk-bob 1.6s 1s infinite}
.rk-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#8f9ad0;font-size:9px}
.rk-progress-foot b{color:#fcd34d;font-size:11px}
@keyframes rk-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes rk-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes rk-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes rk-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes rk-bob{50%{transform:translateY(-5px)}}
@keyframes rk-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.rk-stars circle{animation:rk-tw 2.8s ease-in-out infinite}
.rk-stars circle:nth-child(3n){animation-delay:.9s}
.rk-stars circle:nth-child(3n+1){animation-delay:1.7s}
.rk-actor{position:absolute;filter:drop-shadow(0 0 9px #ffffff55)}
.rk-sat{top:150px;inset-inline-start:calc(620px - 120px);font-size:22px;animation:rk-orbit 8s ease-in-out infinite}
.rk-star1{top:220px;inset-inline-start:calc(620px - 528px);font-size:18px;animation:rk-tw 3s ease-in-out infinite}
.rk-ctrl{top:318px;inset-inline-start:calc(620px - 552px);font-size:26px;filter:drop-shadow(0 4px 4px #0007)}
.rk-alien{top:96px;inset-inline-start:calc(620px - 520px);font-size:20px;animation:rk-orbit 6s 1s ease-in-out infinite}
.rk-stage-off{position:absolute;font-size:19px;opacity:0}
.rk-stage-off.s1{top:280px;inset-inline-start:calc(620px - 350px);opacity:clamp(0,calc((var(--p) - 33)*99),.8)}
.rk-stage-off.s2{top:176px;inset-inline-start:calc(620px - 330px);opacity:clamp(0,calc((var(--p) - 66)*99),.8)}
.rk-gauge{position:absolute;top:20px;inset-inline-start:calc(620px - 600px);width:78px;padding:6px 8px;text-align:center;background:#02030ccc;border:2px solid #fcd34d55;border-radius:12px}
.rk-gauge b{display:block;color:#fcd34d;font-size:14px}
.rk-gauge i{display:block;height:5px;margin-top:4px;border-radius:5px;background:#1e293b;overflow:hidden;position:relative}
.rk-gauge i:after{content:"";position:absolute;inset:0;width:calc(var(--p)*1%);background:linear-gradient(90deg,#f97316,#fcd34d);border-radius:5px}
.rk-ship{position:absolute;top:0;left:0;width:0;height:0;z-index:8;offset-path:path("M318 356 C 318 304, 282 252, 306 192 C 326 140, 290 108, 286 54");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:rk-move .85s cubic-bezier(.3,.9,.3,1) both}
.rk-rocket{position:absolute;left:-16px;top:-4px;font-size:31px;transform:rotate(-45deg);filter:drop-shadow(0 0 12px #fcd34d)}
.rk-flame{position:absolute;left:-11px;top:24px;font-size:21px;transform:rotate(180deg);filter:drop-shadow(0 0 10px #f97316);animation:rk-flame .25s ease-in-out infinite alternate}
.rk-hero{offset-path:path("M318 356 C 318 304, 282 252, 306 192 C 326 140, 290 108, 286 54");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:rk-move .85s cubic-bezier(.3,.9,.3,1) both}
.rk-hero-in{left:-22px;top:-52px;width:44px;height:44px}
.rk-avatar{width:44px;height:44px;font-size:25px;border-color:#fcd34d;box-shadow:0 8px 16px #000a,0 0 22px #fcd34d80}
.rk-helmet{position:absolute;inset:-5px;border-radius:50%;border:2px solid #bfdbfe66;box-shadow:inset 0 5px 12px #ffffff40}
.rk-win{position:absolute;top:196px;inset-inline-start:calc(620px - 400px);width:180px;text-align:center;padding:7px 10px;font-size:15px;font-weight:900;color:#1e1b4b;background:#fcd34d;border-radius:20px;box-shadow:0 0 26px #fcd34d;opacity:clamp(0,calc((var(--p) - 99)*99),1);z-index:10;animation:rk-pop 1.2s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.rk-hero{top:340px;inset-inline-start:calc(280px)}.rk-ship{display:none}}
@keyframes rk-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes rk-tw{0%,100%{opacity:.25}50%{opacity:1}}
@keyframes rk-orbit{0%,100%{transform:translate(0,0)}50%{transform:translate(-26px,16px)}}
@keyframes rk-flame{to{transform:rotate(135deg) scale(1.22)}}
@keyframes rk-pop{50%{transform:scale(1.07)}}
@media(max-width:1000px){.rk-stage{--s:.84}}
@media(max-width:820px){.rk-game{padding:12px;border-radius:22px}.rk-stage{--s:.68}.rk-brand h2{font-size:18px}.rk-brand p{font-size:9px}.rk-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.rk-stage{--s:.6}.rk-score{min-width:78px;padding:7px 10px}.rk-score>span{display:none}.rk-stat{display:block;padding:7px 2px;text-align:center}.rk-stat>span{font-size:16px}.rk-stat small{font-size:7px}.rk-stat b{font-size:14px}}
@media(max-width:560px){.rk-stage{--s:.52}.rk-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.rk-stage{--s:.46}}
@media(max-width:410px){.rk-stage{--s:.4}}
@media(max-width:350px){.rk-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.rk-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-stars', 'name' => 'صورتِ فلکیِ دانایی', 'icon' => '⭐', 'sort' => 19,
                'description' => 'یک صورتِ فلکیِ ناتمام در آسمانِ شب؛ با هر پاسخِ درست ستارهٔ بعدی روشن می‌شود و خطِ نورانی بینِ ستاره‌ها کشیده می‌شود.',
                'board_html' => <<<'BOARD_HTML'
<div class="st-game" dir="rtl" style="--p:{{percent}};--from:max(0,calc(var(--p) - 7))">

    <header class="st-head">
        <div class="st-brand">
            <div class="st-logo">⭐</div>
            <div>
                <h2>صورتِ فلکیِ دانایی</h2>
                <p>هر پاسخِ درست، یک ستارهٔ تازه!</p>
            </div>
        </div>
        <div class="st-score">
            <span>✨</span>
            <div><strong>{{score}}</strong><small>نورِ ستاره</small></div>
        </div>
    </header>

    <section class="st-stats">
        <div class="st-stat"><span>⭐</span><div><small>ستاره‌های روشن</small><b>{{pos}}</b></div></div>
        <div class="st-stat"><span>🌌</span><div><small>کلِ صورتِ فلکی</small><b>{{total}}</b></div></div>
        <div class="st-stat"><span>📈</span><div><small>پیشرفت</small><b>{{percent}}٪</b></div></div>
    </section>

    <div class="st-stage">
      <div class="st-scene">
        <svg class="st-art" viewBox="0 0 620 330" aria-hidden="true">
            <defs>
                <radialGradient id="stNeb" cx=".5" cy=".5" r=".5">
                    <stop offset="0" stop-color="#6366f1" stop-opacity=".5"/>
                    <stop offset="1" stop-color="#6366f1" stop-opacity="0"/>
                </radialGradient>
                <radialGradient id="stNeb2" cx=".5" cy=".5" r=".5">
                    <stop offset="0" stop-color="#22d3ee" stop-opacity=".4"/>
                    <stop offset="1" stop-color="#22d3ee" stop-opacity="0"/>
                </radialGradient>
                <linearGradient id="stLine" gradientUnits="userSpaceOnUse" x1="560" y1="268" x2="62" y2="192">
                    <stop offset="0" stop-color="#22d3ee"/><stop offset=".55" stop-color="#a78bfa"/><stop offset="1" stop-color="#fde047"/>
                </linearGradient>
            </defs>

            <ellipse cx="180" cy="90" rx="190" ry="110" fill="url(#stNeb)"/>
            <ellipse cx="480" cy="240" rx="170" ry="100" fill="url(#stNeb2)"/>

            <g class="st-dust" fill="#fff">
                <circle cx="40" cy="60" r="1.2"/><circle cx="112" cy="128" r="1"/><circle cx="176" cy="56" r="1.4"/>
                <circle cx="256" cy="160" r="1.1"/><circle cx="342" cy="60" r="1.3"/><circle cx="430" cy="140" r="1"/>
                <circle cx="516" cy="72" r="1.5"/><circle cx="592" cy="124" r="1.1"/><circle cx="84" cy="228" r="1.3"/>
                <circle cx="240" cy="292" r="1.1"/><circle cx="392" cy="300" r="1.4"/><circle cx="556" cy="212" r="1.2"/>
                <circle cx="150" cy="300" r="1"/><circle cx="470" cy="60" r="1.2"/><circle cx="310" cy="210" r="1.1"/>
            </g>

            <!-- خطوطِ کم‌رنگِ صورتِ فلکی -->
            <path d="M560 268 L486 148 L392 232 L308 108 L214 214 L136 96 L62 192" fill="none" stroke="#ffffff22" stroke-width="2.5" stroke-dasharray="4 8"/>
            <!-- خطِ کشیده‌شده -->
            <path class="st-trail" pathLength="100" stroke="url(#stLine)" stroke-width="4" stroke-dasharray="100" d="M560 268 L486 148 L392 232 L308 108 L214 214 L136 96 L62 192"/>

            <!-- ستاره‌ها -->
            <g class="st-nodes">
                <g class="st-node n1"><circle cx="560" cy="268" r="6"/><path d="M560 252 V284 M544 268 H576"/></g>
                <g class="st-node n2"><circle cx="486" cy="148" r="7"/><path d="M486 130 V166 M468 148 H504"/></g>
                <g class="st-node n3"><circle cx="392" cy="232" r="6"/><path d="M392 216 V248 M376 232 H408"/></g>
                <g class="st-node n4"><circle cx="308" cy="108" r="8"/><path d="M308 88 V128 M288 108 H328"/></g>
                <g class="st-node n5"><circle cx="214" cy="214" r="6"/><path d="M214 198 V230 M198 214 H230"/></g>
                <g class="st-node n6"><circle cx="136" cy="96" r="7"/><path d="M136 78 V114 M118 96 H154"/></g>
                <g class="st-node n7"><circle cx="62" cy="192" r="9"/><path d="M62 170 V214 M40 192 H84"/></g>
            </g>
        </svg>

        <span class="st-actor st-scope">🔭</span>
        <span class="st-actor st-owl">🦉</span>
        <span class="st-actor st-planet">🪐</span>
        <span class="st-shoot"></span>

        <div class="st-comet"><span>☄️</span></div>
        <div class="st-hero"><div class="st-hero-in"><span class="st-avatar">{{char}}</span><span class="st-spark">✨</span></div></div>

        <div class="st-win">🌌 صورتِ فلکی کامل شد!</div>
      </div>
    </div>

    <div class="st-cheer"><span>🔭</span><b>رصدخانه: ستارهٔ تازه‌ای روشن شد — نقشهٔ آسمان کامل‌تر شد!</b></div>

    <section class="st-progress">
        <div class="st-progress-info">
            <span>صورتِ فلکی تا کاملِ شدن</span>
            <b>{{pos}} از {{total}}</b>
        </div>
        <div class="st-track">
            <span class="st-fill"></span>
            <div class="st-runner">{{char}}</div>
        </div>
        <div class="st-progress-foot">
            <span>✨ هر ستاره یک پاسخِ درست · 🌌 با آخرین ستاره نقشه کامل می‌شود</span>
            <b>{{percent}}٪</b>
        </div>
    </section>

</div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.st-game{box-sizing:border-box;width:min(100%,980px);margin:18px auto;padding:18px;overflow:hidden;color:#fff;font-family:Vazirmatn,Tahoma,Arial,sans-serif;background:radial-gradient(circle at 50% -12%,#312e81,#0f172a 55%,#020617);border:1px solid #ffffff2e;border-radius:30px;box-shadow:0 28px 70px #00000073}
.st-game *,.st-game *:before,.st-game *:after{box-sizing:border-box}
.st-head{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:14px}
.st-brand{display:flex;align-items:center;gap:12px;min-width:0}
.st-logo{display:grid;place-items:center;width:58px;height:58px;flex:none;font-size:30px;background:linear-gradient(145deg,#fff,#e0e7ff);border:3px solid #fde047;border-radius:18px;box-shadow:0 8px 20px #0006;animation:st-logo 3.6s ease-in-out infinite}
.st-brand h2{margin:0;font-size:clamp(19px,3vw,28px);line-height:1.5}
.st-brand p{margin:3px 0 0;color:#c7d2fe;font-size:12px}
.st-score{display:flex;align-items:center;gap:9px;min-width:118px;flex:none;padding:10px 14px;text-align:center;background:#ffffff16;border:1px solid #ffffff36;border-radius:17px}
.st-score span{font-size:24px}
.st-score strong,.st-score small{display:block}
.st-score strong{color:#fde047;font-size:22px}
.st-score small{margin-top:3px;color:#c7d2fe;font-size:9px}
.st-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:13px}
.st-stat{display:flex;align-items:center;justify-content:center;gap:8px;padding:10px;background:#ffffff14;border:1px solid #ffffff26;border-radius:15px}
.st-stat>span{font-size:21px}
.st-stat small,.st-stat b{display:block}
.st-stat small{color:#c7d2fe;font-size:9px}
.st-stat b{margin-top:3px;font-size:17px}
.st-stage{--s:1;width:calc(620px*var(--s));height:calc(330px*var(--s));margin-inline:auto}
.st-scene{position:relative;width:620px;height:330px;transform:scale(var(--s));transform-origin:top right;border-radius:24px;overflow:hidden;box-shadow:inset 0 0 0 4px #ffffffcc,0 18px 38px #0007;background:radial-gradient(120% 110% at 50% 110%,#1e1b4b 0%,#0b1027 48%,#020617 100%)}
.st-art{position:absolute;inset:0;width:620px;height:330px;pointer-events:none}
.st-trail{fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:100;stroke-dashoffset:calc(100px - var(--p)*1px);animation:st-trail .85s cubic-bezier(.3,.9,.3,1) both}
.st-hero{position:absolute;top:0;left:0;width:0;height:0;z-index:9}
.st-hero-in{position:absolute;left:-26px;top:-26px;width:52px;height:52px;display:grid;place-items:center;animation:st-hop .85s cubic-bezier(.3,.9,.3,1) both}
.st-avatar{display:grid;place-items:center;width:50px;height:50px;font-size:28px;background:radial-gradient(circle at 35% 25%,#fff,#eef2ff);border:4px solid #fde047;border-radius:50%;box-shadow:0 8px 16px #000a,0 0 22px #fde04770}
.st-cheer{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;padding:9px 12px;color:#c7d2fe;background:#ffffff12;border:1px solid #ffffff2e;border-radius:14px;font-size:11px;font-weight:700;text-align:center}
.st-cheer span{font-size:17px;flex:none}
.st-progress{margin-top:12px;padding:13px 15px;background:#ffffff13;border:1px solid #ffffff27;border-radius:18px}
.st-progress-info{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px;color:#c7d2fe;font-size:11px}
.st-progress-info b{color:#fde047}
.st-track{position:relative;height:14px;background:#00000080;border:2px solid #ffffff38;border-radius:99px}
.st-fill{display:block;height:100%;max-width:100%;width:calc(var(--p)*1%);background:linear-gradient(90deg,#6366f1,#22d3ee,#fde047);border-radius:inherit;box-shadow:0 0 15px #fde04780;animation:st-fill .85s cubic-bezier(.3,.9,.3,1) both}
.st-runner{position:absolute;top:-25px;inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px));width:42px;height:42px;display:grid;place-items:center;font-size:24px;background:#fff;border:3px solid #fde047;border-radius:50%;box-shadow:0 6px 12px #0007;animation:st-run .85s cubic-bezier(.3,.9,.3,1) both,st-bob 1.6s 1s infinite}
.st-progress-foot{display:flex;justify-content:space-between;gap:10px;margin-top:9px;color:#8b93c9;font-size:9px}
.st-progress-foot b{color:#fde047;font-size:11px}
@keyframes st-trail{from{stroke-dashoffset:calc(100px - var(--from)*1px)}to{stroke-dashoffset:calc(100px - var(--p)*1px)}}
@keyframes st-fill{from{width:calc(var(--from)*1%)}to{width:calc(var(--p)*1%)}}
@keyframes st-run{from{inset-inline-start:clamp(0px,calc(var(--from)*1% - 21px),calc(100% - 42px))}to{inset-inline-start:clamp(0px,calc(var(--p)*1% - 21px),calc(100% - 42px))}}
@keyframes st-hop{0%{transform:translateY(0) scale(1)}35%{transform:translateY(-17px) scale(1.1)}65%{transform:translateY(-5px) scale(.97)}100%{transform:translateY(0) scale(1)}}
@keyframes st-bob{50%{transform:translateY(-5px)}}
@keyframes st-logo{0%,74%{transform:rotate(-4deg)}82%{transform:rotate(9deg) scale(1.07)}90%{transform:rotate(-7deg)}100%{transform:rotate(-4deg)}}
.st-dust circle{animation:st-tw 3s ease-in-out infinite}
.st-dust circle:nth-child(3n){animation-delay:1s}
.st-dust circle:nth-child(3n+1){animation-delay:2s}
.st-node circle{fill:#fde047}
.st-node path{stroke:#fde047;stroke-width:2;opacity:.75;stroke-linecap:round}
.st-node{filter:drop-shadow(0 0 9px #fde047);transform-box:fill-box;transform-origin:center;animation:st-pulse 2.6s ease-in-out infinite}
.st-node.n1{opacity:clamp(.14,calc((var(--p) - 1)*99),1)}
.st-node.n2{opacity:clamp(.14,calc((var(--p) - 17)*99),1);animation-delay:.3s}
.st-node.n3{opacity:clamp(.14,calc((var(--p) - 33)*99),1);animation-delay:.6s}
.st-node.n4{opacity:clamp(.14,calc((var(--p) - 49)*99),1);animation-delay:.9s}
.st-node.n5{opacity:clamp(.14,calc((var(--p) - 64)*99),1);animation-delay:1.2s}
.st-node.n6{opacity:clamp(.14,calc((var(--p) - 80)*99),1);animation-delay:1.5s}
.st-node.n7{opacity:clamp(.14,calc((var(--p) - 96)*99),1);animation-delay:1.8s}
.st-actor{position:absolute;filter:drop-shadow(0 0 10px #a78bfa88)}
.st-scope{top:266px;inset-inline-start:calc(620px - 594px);font-size:30px;animation:st-scope 5s ease-in-out infinite}
.st-owl{top:44px;inset-inline-start:calc(620px - 74px);font-size:21px;animation:st-blink 4.4s ease-in-out infinite}
.st-planet{top:278px;inset-inline-start:calc(620px - 300px);font-size:23px;animation:st-float 5.5s ease-in-out infinite}
.st-shoot{position:absolute;top:34px;inset-inline-start:calc(620px - 470px);width:70px;height:2px;background:linear-gradient(90deg,#fff,transparent);border-radius:2px;animation:st-shoot 6s ease-in infinite}
.st-comet{position:absolute;top:0;left:0;width:0;height:0;z-index:8;offset-path:path("M560 268 L486 148 L392 232 L308 108 L214 214 L136 96 L62 192");offset-rotate:auto;offset-distance:calc(min(100,calc(var(--p) + 7))*1%);animation:st-cometm .85s cubic-bezier(.3,.9,.3,1) both}
.st-comet span{position:absolute;left:-12px;top:-12px;font-size:22px;filter:drop-shadow(0 0 12px #fde047)}
.st-hero{offset-path:path("M560 268 L486 148 L392 232 L308 108 L214 214 L136 96 L62 192");offset-rotate:0deg;offset-distance:calc(var(--p)*1%);animation:st-move .85s cubic-bezier(.3,.9,.3,1) both}
.st-avatar{border-color:#fde047;box-shadow:0 8px 16px #000a,0 0 26px #fde04790}
.st-spark{position:absolute;top:-16px;right:-12px;font-size:16px;animation:st-tw 1.8s ease-in-out infinite}
.st-win{position:absolute;top:150px;inset-inline-start:calc(620px - 410px);width:200px;text-align:center;padding:7px 10px;font-size:15px;font-weight:900;color:#1e1b4b;background:#fde047;border-radius:20px;box-shadow:0 0 26px #fde047;opacity:clamp(0,calc((var(--p) - 99)*99),1);z-index:10;animation:st-pop 1.2s ease-in-out infinite}
@supports not (offset-path: path("M0 0")){.st-hero{top:200px;inset-inline-start:calc(60px + (498px - 60px)*(100 - var(--p))/100)}.st-comet{display:none}}
@keyframes st-move{from{offset-distance:calc(var(--from)*1%)}to{offset-distance:calc(var(--p)*1%)}}
@keyframes st-cometm{from{offset-distance:calc(min(100,calc(var(--from) + 7))*1%)}to{offset-distance:calc(min(100,calc(var(--p) + 7))*1%)}}
@keyframes st-tw{0%,100%{opacity:.3}50%{opacity:1}}
@keyframes st-pulse{50%{transform:scale(1.12)}}
@keyframes st-float{50%{transform:translateY(-9px)}}
@keyframes st-scope{0%,100%{transform:rotate(-6deg)}50%{transform:rotate(7deg)}}
@keyframes st-blink{48%{transform:scale(1)}52%{transform:scale(.88)}}
@keyframes st-shoot{0%{opacity:0;transform:translate(0,0)}6%{opacity:1}20%{opacity:0;transform:translate(-160px,80px)}100%{opacity:0}}
@keyframes st-pop{50%{transform:scale(1.07)}}
@media(max-width:1000px){.st-stage{--s:.84}}
@media(max-width:820px){.st-game{padding:12px;border-radius:22px}.st-stage{--s:.68}.st-brand h2{font-size:18px}.st-brand p{font-size:9px}.st-logo{width:45px;height:45px;font-size:23px}}
@media(max-width:660px){.st-stage{--s:.6}.st-score{min-width:78px;padding:7px 10px}.st-score>span{display:none}.st-stat{display:block;padding:7px 2px;text-align:center}.st-stat>span{font-size:16px}.st-stat small{font-size:7px}.st-stat b{font-size:14px}}
@media(max-width:560px){.st-stage{--s:.52}.st-cheer{font-size:9.5px;padding:8px}}
@media(max-width:470px){.st-stage{--s:.46}}
@media(max-width:410px){.st-stage{--s:.4}}
@media(max-width:350px){.st-stage{--s:.34}}
@media(prefers-reduced-motion:reduce){.st-game *{animation-duration:.01ms!important;animation-iteration-count:1!important}}
BOARD_CSS,
            ],
            [
                'key' => 'board-neon', 'name' => 'نوار نئونی', 'icon' => '💠', 'sort' => 30,
                'description' => 'نوار پیشرفتِ درخشانِ نئونی — ساده، سبک و پرانرژی؛ برای وقتی که می‌خواهید تمامِ صفحه برای سؤال بماند.',
                'board_html' => <<<'BOARD_HTML'
<div class="nz"><div class="nz-track"><div class="nz-fill" style="width:{{percent}}%"></div><span class="nz-char">{{char}}</span></div><div class="nz-info"><span>پیشرفت {{pos}}/{{total}}</span><span class="nz-score">⚡ {{score}}</span></div></div>
BOARD_HTML,
                'board_css' => <<<'BOARD_CSS'
.nz{padding:6px 4px}.nz-track{position:relative;height:30px;border-radius:20px;background:#0b1024;box-shadow:inset 0 0 12px #000;overflow:hidden}.nz-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#22d3ee,#a855f7,#ec4899);box-shadow:0 0 16px #a855f7}.nz-char{position:absolute;top:50%;inset-inline-end:8px;transform:translateY(-50%);font-size:20px;filter:drop-shadow(0 0 6px #fff)}.nz-info{display:flex;justify-content:space-between;margin-top:8px;font-weight:800;font-size:13px;color:#e5e7ff}.nz-score{color:#fbbf24}
BOARD_CSS,
            ],
        ];

        foreach ($boards as $b) {
            GameTemplate::updateOrCreate(['key' => $b['key']], array_merge($b, ['is_active' => true, 'config' => ['cells' => 20]]));
        }
    }
}
