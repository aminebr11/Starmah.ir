# -*- coding: utf-8 -*-
"""Generates the HyperFrames project for the kids' online-class tutorial (vertical, Persian, RTL).

Scene starts and voice anchors live here once, so the scenes, the voice clips and the sound
effects always agree. Run:  python3 build.py   (from brag-output/)
"""
import json
import os
import re

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "composition")
W, H = 1080, 1920
FA = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")

# (id, duration) — the voice is the clock; each scene = 0.35 s lead + voice + tail
SCENES = [("s01", 9.7), ("s02", 8.3), ("s03", 7.7), ("s04", 6.2), ("s05", 7.1), ("s06", 8.0),
          ("s07", 8.6), ("s08", 6.3), ("s09", 6.5), ("s10", 6.7), ("s11", 7.9), ("s12", 8.9)]
V = {"s01": 0.3}            # voice offset inside a scene (default 0.35)
VOICE_LEN = {"s01": 8.59, "s02": 6.72, "s03": 6.59, "s04": 5.03, "s05": 5.99, "s06": 6.89, "s07": 7.46,
             "s08": 5.13, "s09": 5.33, "s10": 5.54, "s11": 6.17, "s12": 6.35}
# pauses inside each voice clip (voice-local seconds) → Mahoo's mouth rests there
PAUSES = {
    "s01": [(0.67, 1.11), (2.20, 3.54), (4.45, 5.63), (7.03, 7.23)],
    "s02": [(1.65, 1.98), (3.91, 4.58), (5.38, 5.70)],
    "s03": [(3.23, 3.60), (4.98, 5.37)],
    "s04": [(2.24, 2.64), (3.21, 3.77)],
    "s05": [(0.76, 1.09), (2.65, 3.13)],
    "s06": [(1.39, 1.73), (3.20, 4.68), (5.47, 5.70)],
    "s07": [(1.73, 2.09), (3.67, 4.59), (5.96, 6.24)],
    "s08": [(1.10, 1.49), (2.21, 2.73)],
    "s09": [(2.88, 3.41), (4.06, 4.40)],
    "s10": [(1.20, 1.55), (2.83, 3.57)],
    "s11": [(1.15, 1.69), (3.62, 4.76)],
    "s12": [(0.92, 1.14), (2.90, 3.44), (4.33, 4.76)],
}
START = {}
t = 0.0
for sid, d in SCENES:
    START[sid] = round(t, 3)
    t += d
TOTAL = round(t, 3)
PHONE_IN, PHONE_OUT = START["s02"], START["s12"]


def vo(sid):
    return V.get(sid, 0.35)


def words(text, cls="w"):
    """Wrap words in spans for per-word entrances; <em>…</em> keeps its accent."""
    out = []
    for part in re.split(r"(<em>.*?</em>)", text):
        if not part:
            continue
        if part.startswith("<em>"):
            inner = part[4:-5]
            out.append("<em>" + " ".join(f'<span class="{cls}">{x}</span>' for x in inner.split()) + "</em>")
        else:
            out.append(" ".join(f'<span class="{cls}">{x}</span>' for x in part.split()))
    return " ".join(out)


# ─────────────────────────────── shared CSS ────────────────────────────────
CSS = r"""
@font-face{font-family:'Vazirmatn';src:url('assets/fonts/Vazirmatn-Medium.woff2') format('woff2');font-weight:500}
@font-face{font-family:'Vazirmatn';src:url('assets/fonts/Vazirmatn-Bold.woff2') format('woff2');font-weight:700}
@font-face{font-family:'Vazirmatn';src:url('assets/fonts/Vazirmatn-ExtraBold.woff2') format('woff2');font-weight:800}
@font-face{font-family:'Vazirmatn';src:url('assets/fonts/Vazirmatn-Black.woff2') format('woff2');font-weight:900}
:root{--ink:#231a45;--mut:#6b6290;--pri:#7c3aed;--bg:#f4f1ff;--yel:#ffd84d;--grn:#16a34a;--grn2:#22c55e;--red:#dc2626;--line:#ece6fb}
*{margin:0;padding:0;box-sizing:border-box}
html,body{margin:0;width:1080px;height:1920px;overflow:hidden;background:#170b3d}
body,#root{font-family:'Vazirmatn',sans-serif;color:var(--ink)}
.ground{position:absolute;inset:0;background:radial-gradient(130% 75% at 50% 0%,#5b2bb8 0%,#331778 42%,#170b3d 100%)}
.stars{position:absolute;left:0;top:-200px;width:1080px;height:2400px;opacity:.75;
  background-image:radial-gradient(circle at 20px 30px,#fff 2.2px,transparent 3px),radial-gradient(circle at 140px 110px,#ffe9a8 1.6px,transparent 2.4px),
  radial-gradient(circle at 70px 170px,#fff 1.2px,transparent 2px);background-size:180px 200px,260px 230px,150px 190px}
/* سرتیتر */
.head{position:absolute;right:64px;top:150px;width:720px;direction:rtl;text-align:right;color:#fff}
.chip{display:inline-flex;align-items:center;gap:12px;background:rgba(255,255,255,.16);border-radius:99px;padding:8px 26px 10px;font-weight:800;font-size:36px;color:#fff}
.title{font-weight:900;font-size:76px;line-height:1.22;margin-top:16px;letter-spacing:-.5px}
.title em,.big em{font-style:normal;color:var(--yel)}
.w{display:inline-block}
/* صفحه‌ی گوشی */
.scr{position:absolute;left:150px;top:520px;width:780px;height:1300px;border-radius:58px;overflow:hidden;background:var(--bg);direction:rtl}
.layer{position:absolute;inset:0}
.topbar{position:absolute;left:0;right:0;top:0;height:170px;background:linear-gradient(120deg,#2e1a7a,#6d28d9);color:#fff;padding:62px 34px 0;display:flex;align-items:flex-start;gap:16px}
.topbar .tt{flex:1}
.topbar b{display:block;font-weight:900;font-size:36px;line-height:1.2}
.topbar small{display:block;font-weight:700;font-size:28px;color:#e4dcff;margin-top:2px}
.topbar .live{display:inline-block;width:14px;height:14px;border-radius:50%;background:#ff4d6d;margin-left:8px;vertical-align:middle}
.topbar .cnt{background:rgba(255,255,255,.18);border-radius:22px;padding:8px 18px;font-weight:800;font-size:28px}
.bar{position:absolute;left:0;right:0;bottom:0;height:190px;background:#fff;display:flex;justify-content:center;gap:14px;padding-top:24px;box-shadow:0 -2px 0 var(--line)}
.cb{width:146px;height:136px;border-radius:32px;background:#f1edfb;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;font-weight:800;font-size:28px;color:var(--ink)}
.cb span{font-size:54px;line-height:1}
.cb.on{background:#dcfce7}
.cbring{position:absolute;inset:0;border-radius:32px;box-shadow:inset 0 0 0 5px var(--grn2);opacity:0}
.cb{position:relative}
.card{background:#fff;border-radius:40px;box-shadow:0 0 0 2px var(--line),0 10px 0 #e3dbfa}
.btn{display:flex;align-items:center;justify-content:center;border-radius:36px;color:#fff;font-weight:900;font-size:40px;height:112px;background:linear-gradient(135deg,#a78bfa,#7c3aed);box-shadow:0 10px 0 #5b21b6}
.toast{position:absolute;left:40px;right:40px;top:196px;border-radius:30px;padding:22px 28px;font-weight:900;font-size:32px;text-align:center}
.toast.ok{background:#dcfce7;color:#14532d;box-shadow:0 0 0 3px #86efac}
.toast.bad{background:#fee2e2;color:#7f1d1d;box-shadow:0 0 0 3px #fca5a5}
.finger{position:absolute;font-size:150px;line-height:1;z-index:40;filter:drop-shadow(0 14px 14px rgba(20,10,60,.45))}
.tile{position:absolute;border-radius:30px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:120px}
.tile .nm{position:absolute;bottom:14px;right:16px;background:rgba(0,0,0,.5);color:#fff;font-weight:800;font-size:28px;border-radius:99px;padding:4px 16px}
.tile .ring{position:absolute;inset:0;border-radius:30px;box-shadow:inset 0 0 0 8px var(--grn2),0 0 0 0 rgba(34,197,94,.0);opacity:0}
.big{font-weight:900;color:#fff;text-align:center;direction:rtl}
"""

# ──────────────────────────────── Mahoo ────────────────────────────────────
MAHOO = r"""
<svg id="mh-svg" viewBox="0 0 120 120" width="300" height="300" style="overflow:visible">
  <defs>
    <radialGradient id="mh-body" cx="38%" cy="34%" r="72%"><stop offset="0" stop-color="#fff7d1"/><stop offset=".5" stop-color="#ffd75e"/><stop offset="1" stop-color="#f29a1f"/></radialGradient>
    <linearGradient id="mh-cap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#9a6bff"/><stop offset="1" stop-color="#5a23c8"/></linearGradient>
    <radialGradient id="mh-glow" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#ffe28a" stop-opacity=".55"/><stop offset="1" stop-color="#ffe28a" stop-opacity="0"/></radialGradient>
  </defs>
  <ellipse cx="60" cy="113" rx="25" ry="4.5" fill="rgba(10,4,40,.35)"/>
  <g id="mh-float">
    <circle cx="60" cy="70" r="50" fill="url(#mh-glow)"/>
    <g id="mh-arml"><path d="M25 80 q-11 1 -13 -9" fill="none" stroke="#f0a428" stroke-width="7" stroke-linecap="round"/></g>
    <g id="mh-armr"><path d="M95 80 q11 1 13 -9" fill="none" stroke="#f0a428" stroke-width="7" stroke-linecap="round"/></g>
    <circle cx="60" cy="70" r="38" fill="url(#mh-body)" stroke="#e48d17" stroke-opacity=".45" stroke-width="1.6"/>
    <circle cx="85" cy="57" r="5.2" fill="#eda733" opacity=".5"/><circle cx="90" cy="76" r="3.2" fill="#eda733" opacity=".45"/><circle cx="35" cy="95" r="3.6" fill="#eda733" opacity=".4"/>
    <ellipse cx="43" cy="51" rx="10" ry="5.5" fill="#fff" opacity=".5" transform="rotate(-32 43 51)"/>
    <g id="mh-eyes">
      <ellipse cx="47" cy="71" rx="8.6" ry="10" fill="#fff"/><ellipse cx="73" cy="71" rx="8.6" ry="10" fill="#fff"/>
      <circle cx="48" cy="73" r="5.3" fill="#2a1b5e"/><circle cx="74" cy="73" r="5.3" fill="#2a1b5e"/>
      <circle cx="50" cy="70.6" r="1.9" fill="#fff"/><circle cx="76" cy="70.6" r="1.9" fill="#fff"/>
    </g>
    <g id="mh-happy" opacity="0" fill="none" stroke="#2a1b5e" stroke-width="3.6" stroke-linecap="round"><path d="M40 73 q7 -9 14 0"/><path d="M66 73 q7 -9 14 0"/></g>
    <g id="mh-wow" opacity="0"><circle cx="47" cy="70" r="10.5" fill="#fff"/><circle cx="73" cy="70" r="10.5" fill="#fff"/><circle cx="47" cy="71" r="3.6" fill="#2a1b5e"/><circle cx="73" cy="71" r="3.6" fill="#2a1b5e"/></g>
    <ellipse cx="36" cy="85" rx="6.2" ry="3.6" fill="#ff8fa3" opacity=".6"/><ellipse cx="84" cy="85" rx="6.2" ry="3.6" fill="#ff8fa3" opacity=".6"/>
    <path id="mh-smile" d="M52 86 q8 8 16 0" fill="none" stroke="#7a2e0e" stroke-width="3.2" stroke-linecap="round"/>
    <g id="mh-talk" opacity="0"><ellipse cx="60" cy="89" rx="6" ry="4.6" fill="#7a2e0e"/><ellipse cx="60" cy="91.6" rx="3.4" ry="1.8" fill="#ff8fa3"/></g>
    <g id="mh-laugh" opacity="0"><path d="M49 84 q11 0 22 0 q-2 12 -11 12 q-9 0 -11 -12z" fill="#7a2e0e"/><path d="M54 92 q6 -4 12 0 q-2 4 -6 4 q-4 0 -6 -4z" fill="#ff8fa3"/></g>
    <g transform="rotate(-9 60 34)">
      <path d="M42 35 L42 44 Q60 52 78 44 L78 35 Z" fill="#4a1a9e"/>
      <polygon points="60,19 95,31.5 60,44 25,31.5" fill="url(#mh-cap)"/>
      <polygon points="60,19 95,31.5 60,25" fill="#fff" opacity=".14"/>
      <g id="mh-tassel">
        <path d="M60 31.5 L86 35 L87.5 49" fill="none" stroke="#ffcf3f" stroke-width="2.2" stroke-linecap="round"/>
        <path d="M87.5 47 l2.6 5.3 5.8 .8 -4.2 4.1 1 5.8 -5.2 -2.7 -5.2 2.7 1 -5.8 -4.2 -4.1 5.8 -.8z" fill="#ffd84d" stroke="#f08c00" stroke-width="1" stroke-linejoin="round"/>
      </g>
      <circle cx="60" cy="31.5" r="2.6" fill="#ffcf3f"/>
    </g>
  </g>
</svg>"""


def scene_file(sid, body, script, style=""):
    return f"""<template>
  <style>
    #root{{position:absolute;inset:0;direction:rtl}}
{style}
  </style>
  <div id="root" data-composition-id="{sid}" data-width="{W}" data-height="{H}">
{body}
  </div>
  <script>
    const tl = gsap.timeline({{ paused: true }});
    const D = {dict(SCENES)[sid]};
{script}
    window.__timelines["{sid}"] = tl;
  </script>
</template>
"""


# helpers emitted into every scene script
JS_HELPERS = r"""
    const P = (sel) => document.querySelector('[data-composition-id="SID"] ' + sel);
    const headIn = (at) => {
      tl.fromTo(P('.chip'), { opacity: 0, filter: 'blur(8px)' }, { opacity: 1, filter: 'blur(0px)', duration: .2, ease: 'power4.out' }, at);
      tl.fromTo(P('.title').querySelectorAll('.w'), { opacity: 0, filter: 'blur(6px)' }, { opacity: 1, filter: 'blur(0px)', duration: .14, stagger: .067, ease: 'power4.out' }, at + .08);
    };
    const headOut = () => tl.to([P('.chip'), P('.title')], { opacity: 0, filter: 'blur(8px)', duration: .16, ease: 'power3.in' }, D - .2);
    const scrIn = (at = 0) => tl.fromTo(P('.scr .layer.a'), { opacity: 0, filter: 'blur(10px)' }, { opacity: 1, filter: 'blur(0px)', duration: .22, ease: 'power4.out' }, at);
    const scrOut = () => tl.to(P('.scr'), { opacity: 0, filter: 'blur(10px)', duration: .16, ease: 'power3.in' }, D - .18);
    // انگشت: از پایین می‌آید، روی نقطه‌ی (x,y) می‌زند
    const fingerTo = (el, x, y, at, dur = .45) => tl.to(el, { x: x - 62, y: y - 8, duration: dur, ease: 'power3.out' }, at);
    const tap = (el, at) => { tl.to(el, { scale: .86, duration: .07, ease: 'power2.in' }, at); tl.to(el, { scale: 1, duration: .16, ease: 'back.out(2)' }, at + .07); };
    const press = (el, at) => { tl.to(el, { scale: .93, duration: .07, ease: 'power2.in' }, at); tl.to(el, { scale: 1, duration: .2, ease: 'back.out(2.2)' }, at + .07); };
    const fingerAway = (el, at) => tl.to(el, { x: 1100, y: 2100, duration: .4, ease: 'power3.in' }, at);
    const pop = (el, at, from = .6) => tl.fromTo(el, { opacity: 0, scale: from }, { opacity: 1, scale: 1, duration: .38, ease: 'back.out(1.8)' }, at);
"""


def helpers(sid):
    return JS_HELPERS.replace("SID", sid)


def head(chip, title):
    return f"""    <div class="head"><div class="chip">{chip}</div><div class="title">{words(title)}</div></div>"""


FINGER = '    <div class="finger" style="left:0;top:0" data-layout-ignore>👆</div>'
FINGER_INIT = "    tl.set(P('.finger'), { x: 1100, y: 2100 }, 0);\n"

TOPBAR = """<div class="topbar"><div class="tt"><b>ریاضی — کسرها</b><small><i class="live"></i>زنده · ۰۰:۱۲</small></div><div class="cnt">👥 ۱۲</div></div>"""

scenes = {}

# ═══════════════════ s01 — قانونِ اول: فیلترشکن خاموش ═══════════════════
sid = "s01"
a = vo(sid)
FLIP = a + 7.55
scenes[sid] = scene_file(sid, f"""
    <div class="h1">
      <div class="chip" style="font-size:44px;padding:10px 34px 12px;background:rgba(220,38,38,.92)">🚨 قانونِ اول</div>
      <div class="title big1">{words('فیلترشکن <em>خاموش!</em>')}</div>
    </div>
    <div class="vpn card">
      <div class="vpn-ic">🛡️</div>
      <div class="vpn-t"><b>فیلترشکن</b><small>VPN</small></div>
      <div class="sw"><div class="sw-bg on"></div><div class="sw-bg off"></div><div class="knob"></div></div>
      <div class="sw-l on">روشن</div><div class="sw-l off">خاموش</div>
    </div>
    <div class="stamp">✅ خاموش شد!</div>
    <div class="warn">با فیلترشکن، صدا و تصویرِ کلاس قطع می‌شود</div>
""", helpers(sid) + f"""
    const A = {a};
    tl.fromTo(P('.title').querySelectorAll('.w'), {{ opacity: .35 }}, {{ opacity: 1, duration: .25, stagger: .08, ease: 'power2.out' }}, 0);
    tl.fromTo(P('.vpn'), {{ y: 0 }}, {{ y: 0, duration: .01 }}, 0);
    // «یک قانونِ خیلی مهم» → زنگِ هشدار
    tl.fromTo(P('.h1 .chip'), {{ rotation: 0 }}, {{ rotation: 4, duration: .07, yoyo: true, repeat: 5, ease: 'sine.inOut' }}, A + 5.7);
    tl.fromTo(P('.warn'), {{ opacity: 0, y: 30 }}, {{ opacity: 1, y: 0, duration: .45, ease: 'expo.out' }}, A + 3.6);
    // «خاموش» → کلید خاموش می‌شود
    tl.to(P('.knob'), {{ x: 150, duration: .28, ease: 'back.out(1.6)' }}, {FLIP});
    tl.to(P('.sw-bg.off'), {{ opacity: 1, duration: .2 }}, {FLIP});
    tl.to(P('.sw-l.on'), {{ opacity: 0, duration: .12 }}, {FLIP});
    tl.fromTo(P('.sw-l.off'), {{ opacity: 0, y: 12 }}, {{ opacity: 1, y: 0, duration: .25, ease: 'expo.out' }}, {FLIP + 0.05});
    tl.fromTo(P('.stamp'), {{ opacity: 0, scale: 2.2, rotation: -4 }}, {{ opacity: 1, scale: 1, rotation: -10, duration: .3, ease: 'power4.out' }}, {FLIP + 0.3});
    tl.to(P('.vpn'), {{ boxShadow: '0 0 0 8px #22c55e, 0 14px 0 #15803d', duration: .25 }}, {FLIP + 0.05});
    tl.to([P('.h1'), P('.vpn'), P('.stamp'), P('.warn')], {{ opacity: 0, filter: 'blur(10px)', duration: .18, ease: 'power3.in' }}, D - .2);
""", r"""
    .h1{position:absolute;left:60px;right:60px;top:170px;text-align:center;color:#fff}
    .big1{font-size:132px;line-height:1.15;margin-top:26px}
    .vpn{position:absolute;left:120px;width:840px;top:1150px;height:330px;display:flex;align-items:center;padding:0 54px;gap:30px}
    .vpn-ic{font-size:130px;line-height:1}
    .vpn-t b{display:block;font-weight:900;font-size:66px}
    .vpn-t small{display:block;font-weight:800;font-size:34px;color:var(--mut);direction:ltr;text-align:right}
    .sw{position:absolute;left:58px;top:92px;width:270px;height:120px}
    .sw-bg{position:absolute;inset:0;border-radius:99px}
    .sw-bg.on{background:#ef4444}
    .sw-bg.off{background:#22c55e;opacity:0}
    .knob{position:absolute;left:12px;top:12px;width:96px;height:96px;border-radius:50%;background:#fff;box-shadow:0 6px 10px rgba(0,0,0,.25)}
    .sw-l{position:absolute;left:58px;width:270px;top:226px;text-align:center;font-weight:900;font-size:38px}
    .sw-l.on{color:#dc2626}
    .sw-l.off{color:#15803d;opacity:0}
    .stamp{position:absolute;left:250px;top:1520px;width:580px;text-align:center;font-weight:900;font-size:70px;color:#fff;background:#16a34a;border-radius:36px;padding:14px 0 22px;box-shadow:0 0 0 8px rgba(255,255,255,.9),0 18px 30px rgba(0,0,0,.35);opacity:0}
    .warn{position:absolute;left:90px;right:90px;top:1760px;text-align:center;font-weight:800;font-size:40px;color:#ffe9a8;opacity:0}
""")

# ═══════════════════ s02 — چرا؟ ═══════════════════
sid = "s02"
a = vo(sid)
CUT, FIX = a + 2.0, a + 5.75
scenes[sid] = scene_file(sid, f"""
{head('🤔 چرا؟', 'فیلترشکن یعنی <em>قطعی!</em>')}
    <div class="scr"><div class="layer a">
      {TOPBAR}
      <div class="vpnb on">🛡️ فیلترشکن: روشن</div><div class="vpnb off">🛡️ فیلترشکن: خاموش</div>
      <div class="tile t1" style="left:40px;top:300px;width:700px;height:520px;background:linear-gradient(140deg,#c4b5fd,#7c3aed)">👩‍🏫<span class="nm">خانم احمدی</span><div class="frz">⏸️</div></div>
      <div class="toast bad">🔇 صدا و تصویر قطع شد!</div>
      <div class="toast ok">🟢 وصل شد؛ صدا می‌آید</div>
      <div class="bar"><div class="cb"><span>✋</span>دست بالا</div><div class="cb"><span>😀</span>واکنش</div><div class="cb"><span>🔊</span>صدا</div><div class="cb"><span>💬</span>گفت‌وگو</div></div>
    </div></div>
""", helpers(sid) + f"""
    // گوشی با پوسته بالا می‌آید
    tl.fromTo(P('.scr'), {{ y: 1400 }}, {{ y: 0, duration: .6, ease: 'expo.out' }}, 0);
    headIn(.15);
    tl.fromTo(P('.toast.bad'), {{ opacity: 0, y: -40 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, {CUT});
    tl.to(P('.t1'), {{ filter: 'grayscale(1) blur(3px)', duration: .25 }}, {CUT});
    tl.fromTo(P('.frz'), {{ opacity: 0 }}, {{ opacity: 1, duration: .2 }}, {CUT});
    tl.fromTo(P('.t1'), {{ x: 0 }}, {{ x: 10, duration: .05, yoyo: true, repeat: 5, ease: 'none' }}, {CUT});
    // «اول خاموشش کن»
    tl.to(P('.vpnb.on'), {{ opacity: 0, duration: .15 }}, {FIX});
    tl.fromTo(P('.vpnb.off'), {{ opacity: 0, scale: .7 }}, {{ opacity: 1, scale: 1, duration: .3, ease: 'back.out(2)' }}, {FIX});
    tl.to(P('.toast.bad'), {{ opacity: 0, y: -30, duration: .2 }}, {FIX + 0.1});
    tl.fromTo(P('.toast.ok'), {{ opacity: 0, y: -40 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, {FIX + 0.25});
    tl.to(P('.t1'), {{ filter: 'grayscale(0) blur(0px)', duration: .3 }}, {FIX + 0.25});
    tl.to(P('.frz'), {{ opacity: 0, duration: .2 }}, {FIX + 0.25});
    headOut(); scrOut();
""", r"""
    .vpnb{position:absolute;left:40px;right:40px;top:200px;height:80px;border-radius:24px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:34px}
    .vpnb.on{background:#fee2e2;color:#b91c1c;box-shadow:inset 0 0 0 3px #fca5a5}
    .vpnb.off{background:#dcfce7;color:#166534;box-shadow:inset 0 0 0 3px #86efac;opacity:0}
    .frz{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:150px;background:rgba(255,255,255,.35);opacity:0}
    .toast{top:860px}
    .toast.bad,.toast.ok{opacity:0}
""")

# ═══════════════════ s03 — ورود از «کلاسِ آنلاین» ═══════════════════
sid = "s03"
a = vo(sid)
T1, SW, T2 = a + 2.25, a + 2.75, a + 5.85
scenes[sid] = scene_file(sid, f"""
{head('۱ · ورود', 'برو به <em>کلاسِ آنلاین</em>')}
    <div class="scr">
      <div class="layer a site">
        <div class="hdr"><div class="lg"><img src="assets/img/hero-emblem-560.webp" alt="">ستاره <i>ماه</i></div><div class="bell">🔔</div></div>
        <div class="menu"><div class="mc">🎮 دنیای بازی‌ها</div><div class="mc">🏆 مسابقه‌ی زنده</div><div class="mc hot">⭐ کلاسِ آنلاین</div></div>
        <div class="ph"><div class="phl"></div><div class="phl s"></div><div class="phl"></div><div class="phl s"></div></div>
      </div>
      <div class="layer b" data-layout-allow-occlusion>
        <div class="pg">کلاسِ آنلاین</div>
        <div class="hero">کلاس شروع شده! بدو بیا 🎉<small>معلم منتظرِ توست. هدفون بگذار و وارد شو.</small></div>
        <div class="live card"><div class="dot"></div><div class="li"><b>ریاضی — کسرها</b><small>👩‍🏫 خانم احمدی · ۱۲ نفر در کلاس</small></div><div class="go">ورود 🚀</div></div>
      </div>
    </div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    fingerTo(P('.finger'), 340, 820, {T1 - 0.55});
    tap(P('.finger'), {T1}); press(P('.mc.hot'), {T1});
    tl.to(P('.mc.hot'), {{ backgroundColor: '#e11d48', color: '#fff', duration: .12 }}, {T1 + 0.05});
    // صفحه‌ی «کلاسِ آنلاین» با یک جاروی افقی
    tl.to(P('.layer.a'), {{ x: 300, opacity: 0, filter: 'blur(14px)', duration: .28, ease: 'power3.in' }}, {SW});
    tl.fromTo(P('.layer.b'), {{ x: -300, opacity: 0, filter: 'blur(14px)' }}, {{ x: 0, opacity: 1, filter: 'blur(0px)', duration: .4, ease: 'expo.out' }}, {SW + 0.2});
    fingerTo(P('.finger'), 330, 1290, {T2 - 0.6});
    tap(P('.finger'), {T2}); press(P('.go'), {T2});
    fingerAway(P('.finger'), {T2 + 0.45});
    headOut(); scrOut();
""", r"""
    .site{background:#3b0f17}
    .hdr{position:absolute;left:24px;right:24px;top:70px;height:120px;background:#fff;border-radius:34px;display:flex;align-items:center;justify-content:space-between;padding:0 30px}
    .lg{display:flex;align-items:center;gap:14px;font-weight:900;font-size:44px}
    .lg i{font-style:normal;color:#d4a017}
    .lg img{width:72px;height:72px;border-radius:50%}
    .bell{font-size:56px}
    .menu{position:absolute;right:24px;left:24px;top:230px;display:flex;gap:14px;direction:rtl}
    .mc{flex:none;background:#f3f1f6;border-radius:28px;padding:22px 22px;font-weight:900;font-size:30px;color:#3b3355;box-shadow:0 6px 0 #d6d0e2}
    .mc.hot{background:#fde7ea;color:#be123c}
    .ph{position:absolute;left:24px;right:24px;top:400px;display:flex;flex-direction:column;gap:26px}
    .phl{height:150px;border-radius:30px;background:rgba(255,255,255,.08)}
    .phl.s{height:90px}
    .layer.b{background:#3b0f17;opacity:0}
    .pg{position:absolute;right:40px;top:80px;color:#fff;font-weight:900;font-size:52px}
    .hero{position:absolute;left:30px;right:30px;top:190px;border-radius:44px;padding:44px 40px;background:linear-gradient(135deg,#3b2a9e,#7c3aed);color:#fff;font-weight:900;font-size:50px;line-height:1.35}
    .hero small{display:block;font-weight:700;font-size:30px;color:#e4dcff;margin-top:14px}
    .live{position:absolute;left:30px;right:30px;top:560px;height:250px;display:flex;align-items:center;gap:22px;padding:0 30px;box-shadow:0 0 0 6px #f43f5e,0 12px 0 #fda4af}
    .dot{width:26px;height:26px;border-radius:50%;background:#f43f5e;box-shadow:0 0 0 10px rgba(244,63,94,.25)}
    .li{flex:1}
    .li b{display:block;font-weight:900;font-size:42px}
    .li small{display:block;font-weight:700;font-size:28px;color:var(--mut);margin-top:6px}
    .go{flex:none;background:linear-gradient(135deg,#fb7185,#e11d48);color:#fff;font-weight:900;font-size:38px;border-radius:30px;padding:28px 30px;box-shadow:0 8px 0 #9f1239}
""")

# ═══════════════════ s04 — لابی: هدفون، ورود به کلاس ═══════════════════
sid = "s04"
a = vo(sid)
TAP = a + 1.85
scenes[sid] = scene_file(sid, f"""
{head('۲ · هدفون', '🎧 هدفون، بعد <em>ورود!</em>')}
    <div class="scr">
      <div class="layer a lobby">
        <div class="lc card">
          <div class="moon">🌙</div>
          <b>ریاضی — کسرها</b><small>👩‍🏫 خانم احمدی · چهارم الف</small>
          <div class="tip t-head">🎧 هدفون بگذار تا صدا بهتر باشد.</div>
          <div class="tip">✋ برای صحبت دست بالا کن؛ معلم میکروفون را باز می‌کند.</div>
          <div class="tip">⭐ پاسخِ درست به سؤال‌ها و ستاره‌ی معلم امتیاز دارد!</div>
          <div class="btn enter">🚀 ورود به کلاس</div>
        </div>
      </div>
      <div class="zoom"></div>
      <div class="layer c">
        {TOPBAR}
        <div class="board card"><div class="ln"></div><div class="ln"></div><div class="ln"></div><div class="ln"></div><div class="ln"></div></div>
        <div class="toast ok wel">🌟 آفرین! تو در کلاس هستی</div>
        <div class="bar"><div class="cb"><span>✋</span>دست بالا</div><div class="cb"><span>😀</span>واکنش</div><div class="cb"><span>🔊</span>صدا</div><div class="cb"><span>💬</span>گفت‌وگو</div></div>
      </div>
    </div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.fromTo(P('.t-head'), {{ backgroundColor: '#f5f2fd' }}, {{ backgroundColor: '#fff3c4', duration: .3 }}, .6);
    tl.fromTo(P('.t-head'), {{ scale: 1 }}, {{ scale: 1.04, duration: .18, yoyo: true, repeat: 1, ease: 'sine.inOut' }}, .6);
    fingerTo(P('.finger'), 540, 1530, {TAP - 0.55});
    tap(P('.finger'), {TAP}); press(P('.enter'), {TAP});
    fingerAway(P('.finger'), {TAP + 0.3});
    // زوم از داخلِ دکمه به کلاس
    tl.fromTo(P('.zoom'), {{ opacity: 0, scale: .1 }}, {{ opacity: 1, scale: 4, duration: .4, ease: 'power3.in' }}, {TAP + 0.2});
    tl.set(P('.layer.a'), {{ opacity: 0 }}, {TAP + 0.6});
    tl.fromTo(P('.layer.c'), {{ opacity: 0, scale: 1.08 }}, {{ opacity: 1, scale: 1, duration: .35, ease: 'expo.out' }}, {TAP + 0.6});
    tl.to(P('.zoom'), {{ opacity: 0, duration: .25 }}, {TAP + 0.65});
    tl.fromTo(P('.wel'), {{ opacity: 0, y: -40 }}, {{ opacity: 1, y: 0, duration: .4, ease: 'expo.out' }}, {a + 2.7});
    headOut(); scrOut();
""", r"""
    .lobby{background:radial-gradient(120% 80% at 50% 0%,#5b2bb8,#22105a)}
    .lc{position:absolute;left:36px;right:36px;top:110px;padding:40px 40px 50px;text-align:center}
    .moon{font-size:110px;line-height:1.1}
    .lc b{display:block;font-weight:900;font-size:50px;margin-top:6px}
    .lc small{display:block;font-weight:700;font-size:28px;color:var(--mut);margin:8px 0 22px}
    .tip{background:#f5f2fd;border-radius:24px;padding:18px 22px;font-weight:700;font-size:28px;text-align:right;margin-bottom:14px;line-height:1.6}
    .enter{margin-top:24px}
    .zoom{position:absolute;left:290px;top:1080px;width:200px;height:200px;border-radius:50%;background:#6d28d9;opacity:0}
    .layer.c{opacity:0}
    .board{position:absolute;left:30px;right:30px;top:200px;height:820px;padding:120px 40px 0}
    .ln{height:2px;background:#bcd3f5;margin-bottom:120px}
    .wel{top:470px;opacity:0}
""")

# ═══════════════════ s05 — دست بالا و میکروفون ═══════════════════
sid = "s05"
a = vo(sid)
HAND, OK, MIC = a + 1.6, a + 3.2, a + 4.55
scenes[sid] = scene_file(sid, f"""
{head('۳ · صحبت', '✋ دست بالا، بعد <em>صحبت</em>')}
    <div class="scr"><div class="layer a">
      {TOPBAR}
      <div class="board card"><div class="eq">۳ + ۴ = ؟</div></div>
      <div class="tile tp" style="left:56px;top:230px;width:220px;height:170px;background:linear-gradient(140deg,#c4b5fd,#7c3aed);font-size:90px">👩‍🏫</div>
      <div class="handup">✋ دستت بالاست</div>
      <div class="toast ok perm">✅ معلم اجازه‌ی صحبت داد</div>
      <div class="spk">🎙️ داری صحبت می‌کنی</div>
      <div class="bar">
        <div class="cb mic"><span>🔇</span>میکروفون<div class="cbring"></div></div>
        <div class="cb hand"><span>✋</span><em class="hl">دست بالا</em><div class="cbring"></div></div>
        <div class="cb"><span>😀</span>واکنش</div><div class="cb"><span>🔊</span>صدا</div>
      </div>
    </div></div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.set(P('.cb.mic'), {{ opacity: 0, scale: .5 }}, 0);
    fingerTo(P('.finger'), 440, 1745, {HAND - 0.55});
    tap(P('.finger'), {HAND}); press(P('.cb.hand'), {HAND});
    tl.to(P('.cb.hand'), {{ backgroundColor: '#fff3c4', duration: .12 }}, {HAND + 0.05});
    pop(P('.handup'), {HAND + 0.15});
    tl.to(P('.finger'), {{ x: 900, y: 1500, duration: .4, ease: 'power3.inOut' }}, {HAND + 0.4});
    // معلم اجازه داد → میکروفون پیدا می‌شود
    tl.fromTo(P('.perm'), {{ opacity: 0, y: -40 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, {OK});
    tl.to(P('.handup'), {{ opacity: 0, duration: .2 }}, {OK});
    tl.to(P('.cb.hand'), {{ backgroundColor: '#f1edfb', duration: .2 }}, {OK});
    pop(P('.cb.mic'), {OK + 0.35}, .5);
    fingerTo(P('.finger'), 595, 1745, {MIC - 0.5});
    tap(P('.finger'), {MIC}); press(P('.cb.mic'), {MIC});
    tl.to(P('.cb.mic'), {{ backgroundColor: '#dcfce7', duration: .12 }}, {MIC + 0.05});
    tl.to(P('.cb.mic .cbring'), {{ opacity: 1, duration: .12 }}, {MIC + 0.05});
    tl.set(P('.cb.mic span'), {{ textContent: '🎤' }}, {MIC + 0.05});
    pop(P('.spk'), {MIC + 0.25});
    fingerAway(P('.finger'), {MIC + 0.45});
    headOut(); scrOut();
""", r"""
    .board{position:absolute;left:30px;right:30px;top:200px;height:860px;background:#fff}
    .eq{position:absolute;left:0;right:0;top:420px;text-align:center;font-weight:900;font-size:96px;color:#1f2a44}
    .handup{position:absolute;left:180px;right:180px;top:980px;text-align:center;background:#fff3c4;border-radius:28px;padding:16px;font-weight:900;font-size:36px;color:#7a4a00;box-shadow:0 0 0 3px #fcd34d;opacity:0}
    .perm{top:460px;opacity:0}
    .spk{position:absolute;left:150px;right:150px;top:980px;text-align:center;background:#dcfce7;border-radius:28px;padding:16px;font-weight:900;font-size:36px;color:#14532d;box-shadow:0 0 0 3px #22c55e;opacity:0}
    .hl{font-style:normal}
""")

# ═══════════════════ s06 — قابِ سبزِ گوینده + دوربین ═══════════════════
sid = "s06"
a = vo(sid)
GRN, CAM = a + 1.9, a + 5.65
scenes[sid] = scene_file(sid, f"""
{head('۴ · دوربین', 'هر کس حرف بزند، <em>سبز</em> می‌شود')}
    <div class="scr"><div class="layer a">
      {TOPBAR}
      <div class="tile" style="left:30px;top:200px;width:720px;height:360px;background:linear-gradient(140deg,#c4b5fd,#7c3aed)">👩‍🏫<span class="nm">خانم احمدی</span></div>
      <div class="tile g1" style="left:30px;top:584px;width:348px;height:250px;background:linear-gradient(140deg,#bae6fd,#0284c7)">👦<span class="nm">آرمین</span></div>
      <div class="tile g2" style="left:402px;top:584px;width:348px;height:250px;background:linear-gradient(140deg,#fbcfe8,#db2777)">👧<span class="nm sn">سارا</span><div class="ring"></div></div>
      <div class="tile g3" style="left:30px;top:858px;width:348px;height:250px;background:linear-gradient(140deg,#fde68a,#d97706)">🧒<span class="nm">کیان</span></div>
      <div class="tile g4" style="left:402px;top:858px;width:348px;height:250px;background:#ede9fe;border:4px dashed #c4b5fd;font-size:42px;color:#7c3aed;font-weight:900">📷 تو</div>
      <div class="tile me" style="left:402px;top:858px;width:348px;height:250px;background:linear-gradient(140deg,#bbf7d0,#16a34a)">😀<span class="nm">من</span></div>
      <div class="bar">
        <div class="cb on"><span>🎤</span>میکروفون</div>
        <div class="cb cam"><span>📷</span>دوربین<div class="cbring"></div></div>
        <div class="cb"><span>✋</span>دست بالا</div><div class="cb"><span>😀</span>واکنش</div>
      </div>
    </div></div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.set(P('.me'), {{ opacity: 0 }}, 0);
    // سارا حرف می‌زند → قابِ سبز
    tl.to(P('.g2 .ring'), {{ opacity: 1, duration: .15 }}, {GRN});
    tl.fromTo(P('.g2 .ring'), {{ boxShadow: 'inset 0 0 0 6px #22c55e, 0 0 0 0 rgba(34,197,94,.0)' }},
      {{ boxShadow: 'inset 0 0 0 12px #22c55e, 0 0 30px 6px rgba(34,197,94,.55)', duration: .35, yoyo: true, repeat: 7, ease: 'sine.inOut' }}, {GRN});
    tl.to(P('.sn'), {{ backgroundColor: '#16a34a', duration: .15 }}, {GRN});
    tl.set(P('.sn'), {{ textContent: '🎙️ سارا' }}, {GRN});
    tl.to(P('.g2 .ring'), {{ opacity: 0, duration: .2 }}, {CAM - 0.4});
    tl.set(P('.sn'), {{ textContent: 'سارا' }}, {CAM - 0.4});
    tl.to(P('.sn'), {{ backgroundColor: 'rgba(0,0,0,.5)', duration: .15 }}, {CAM - 0.4});
    // دوربینِ خودم
    fingerTo(P('.finger'), 440, 1745, {CAM - 0.55});
    tap(P('.finger'), {CAM}); press(P('.cb.cam'), {CAM});
    tl.to(P('.cb.cam'), {{ backgroundColor: '#dcfce7', duration: .12 }}, {CAM + 0.05});
    tl.to(P('.cb.cam .cbring'), {{ opacity: 1, duration: .12 }}, {CAM + 0.05});
    tl.set(P('.cb.cam span'), {{ textContent: '🎥' }}, {CAM + 0.05});
    tl.to(P('.g4'), {{ opacity: 0, duration: .15 }}, {CAM + 0.2});
    pop(P('.me'), {CAM + 0.2}, .7);
    fingerAway(P('.finger'), {CAM + 0.45});
    headOut(); scrOut();
""", "")

# ═══════════════════ s07 — تخته و بزرگ‌نمایی ═══════════════════
sid = "s07"
a = vo(sid)
DRAW0, DRAW1, DT = a + 2.15, a + 3.5, a + 6.35
# دایره‌ای دورِ «۳ + ۴» (مختصاتِ صفحه‌ی کامل)
CX, CY, RX, RY = 540, 1235, 210, 95
pts = [(CX + RX * c, CY + RY * s) for c, s in [(1, 0), (0.7, 0.7), (0, 1), (-0.7, 0.7), (-1, 0), (-0.7, -0.7), (0, -1), (0.7, -0.7), (1, 0)]]
fpath = "\n".join(
    f"    tl.to(P('.finger'), {{ x: {x - 62:.0f}, y: {y - 8:.0f}, duration: {(DRAW1 - DRAW0) / 8:.3f}, ease: 'none' }}, {DRAW0 + i * (DRAW1 - DRAW0) / 8:.3f});"
    for i, (x, y) in enumerate(pts[1:]))
scenes[sid] = scene_file(sid, f"""
{head('۵ · تخته', 'با انگشت <em>بنویس</em>')}
    <div class="scr"><div class="layer a">
      {TOPBAR}
      <div class="tools"><span>✏️</span><span>🖍️</span><span>🧽</span><i style="background:#1f2a44"></i><i style="background:#e53935"></i><i style="background:#43a047"></i><i style="background:#1e88e5"></i></div>
      <div class="can">✏️ می‌توانی بنویسی</div>
      <div class="bd card"><div class="zw">
        <div class="pgh">ریاضی چهارم — کسرها</div>
        <svg class="pizza" viewBox="-60 -60 120 120" width="300" height="300">
          <circle r="54" fill="#fde68a" stroke="#d97706" stroke-width="4"/>
          <path d="M0 0 L54 0 A54 54 0 0 1 38.2 38.2 Z" fill="#ef4444"/>
          <path d="M0 0 L38.2 38.2 A54 54 0 0 1 0 54 Z" fill="#ef4444"/>
          <path d="M0 0 L0 54 A54 54 0 0 1 -38.2 38.2 Z" fill="#ef4444"/>
          <g stroke="#b45309" stroke-width="2.5"><line x1="-54" y1="0" x2="54" y2="0"/><line x1="0" y1="-54" x2="0" y2="54"/><line x1="-38.2" y1="-38.2" x2="38.2" y2="38.2"/><line x1="-38.2" y1="38.2" x2="38.2" y2="-38.2"/></g>
        </svg>
        <div class="q">۳ + ۴ = ؟</div>
        <div class="q2">چند تکه‌ی پیتزا قرمز است؟</div>
      </div></div>
      <svg class="ink" viewBox="0 0 {W} {H}" width="{W}" height="{H}"><ellipse cx="{CX}" cy="{CY}" rx="{RX}" ry="{RY}" fill="none" stroke="#e53935" stroke-width="10" stroke-linecap="round" pathLength="1" stroke-dasharray="1 1.2" stroke-dashoffset="1.1"/></svg>
      <div class="zc">🔍 ۱٫۸× <b>⟲ کامل</b></div>
    </div></div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.fromTo(P('.can'), {{ opacity: 0, y: -16 }}, {{ opacity: 1, y: 0, duration: .3, ease: 'expo.out' }}, .5);
    fingerTo(P('.finger'), {pts[0][0]:.0f}, {pts[0][1]:.0f}, {DRAW0 - 0.5});
{fpath}
    tl.fromTo(P('.ink ellipse'), {{ strokeDashoffset: 1.1 }}, {{ strokeDashoffset: 0, duration: {DRAW1 - DRAW0:.2f}, ease: 'none' }}, {DRAW0});
    // دو بار زدن → بزرگ‌نمایی
    fingerTo(P('.finger'), 470, 1080, {DT - 0.5});
    tap(P('.finger'), {DT}); tap(P('.finger'), {DT + 0.24});
    tl.to(P('.ink'), {{ opacity: 0, duration: .2 }}, {DT + 0.3});
    tl.to(P('.zw'), {{ scale: 1.8, x: 0, y: 0, duration: .55, ease: 'expo.out' }}, {DT + 0.38});
    tl.to(P('.can'), {{ opacity: 0, duration: .15 }}, {DT + 0.3});
    pop(P('.zc'), {DT + 0.5});
    fingerAway(P('.finger'), {DT + 0.55});
    headOut(); scrOut();
""", r"""
    .tools{position:absolute;left:30px;right:30px;top:194px;height:100px;background:#fff;border-radius:28px;display:flex;align-items:center;gap:14px;padding:0 22px;box-shadow:0 6px 0 #e3dbfa}
    .tools span{width:74px;height:74px;border-radius:20px;background:#f5f2fd;display:flex;align-items:center;justify-content:center;font-size:40px}
    .tools span:first-child{box-shadow:inset 0 0 0 4px #7c3aed;background:#ede5ff}
    .tools i{width:48px;height:48px;border-radius:50%;display:block}
    .can{position:absolute;left:0;right:0;top:1230px;text-align:center;font-weight:900;font-size:34px;color:#4c1d95;opacity:0}
    .bd{position:absolute;left:30px;right:30px;top:320px;height:880px;overflow:hidden}
    .zw{position:absolute;inset:0;transform-origin:50% 50%}
    .pgh{position:absolute;left:0;right:0;top:0;height:90px;background:#0ea5e9;color:#fff;font-weight:900;font-size:36px;display:flex;align-items:center;justify-content:center}
    .pizza{position:absolute;left:210px;top:140px}
    .q{position:absolute;left:0;right:0;top:470px;text-align:center;font-weight:900;font-size:92px;color:#1f2a44}
    .q2{position:absolute;left:0;right:0;top:640px;text-align:center;font-weight:800;font-size:38px;color:#334155}
    .ink{position:absolute;left:-150px;top:-520px;pointer-events:none}
    .zc{position:absolute;left:50px;top:1214px;background:rgba(30,16,70,.9);color:#fff;border-radius:99px;padding:14px 26px;font-weight:900;font-size:32px;opacity:0}
    .zc b{background:#fff;color:#4c1d95;border-radius:99px;padding:4px 16px;margin-right:12px}
""")

# ═══════════════════ s08 — سؤالِ سریع و ستاره ═══════════════════
sid = "s08"
a = vo(sid)
POLL, ANS, RIGHT = a + 0.1, a + 1.85, a + 2.8
stars = "".join(f'<div class="st st{i}" data-layout-ignore>⭐</div>' for i in range(7))
star_js = "\n".join(
    f"    tl.fromTo(P('.st{i}'), {{ opacity: 0, x: 0, y: 0, scale: .4 }}, {{ opacity: 1, x: {dx}, y: {dy}, scale: 1, duration: .5, ease: 'expo.out' }}, {RIGHT + 0.05 + i * 0.03:.3f});\n"
    f"    tl.to(P('.st{i}'), {{ opacity: 0, y: {dy - 80}, duration: .4, ease: 'power2.in' }}, {RIGHT + 1.3 + i * 0.03:.3f});"
    for i, (dx, dy) in enumerate([(-260, -150), (-150, -280), (0, -330), (150, -280), (260, -150), (-200, 60), (200, 60)]))
scenes[sid] = scene_file(sid, f"""
{head('۶ · سؤالِ سریع', 'جوابِ درست = <em>ستاره ⭐</em>')}
    <div class="scr"><div class="layer a">
      {TOPBAR}
      <div class="dim"></div>
      <div class="poll card">
        <div class="ph">📊 سؤالِ سریع</div>
        <div class="pq">۳ + ۴ = ؟</div>
        <div class="ops"><div class="op">۶</div><div class="op ok">۷<i>✓</i></div><div class="op">۸</div><div class="op">۹</div></div>
        <div class="pf">🎉 آفرین! درست بود</div>
      </div>
      <div class="xp">⭐ +۵ امتیاز</div>
      {stars}
    </div></div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.fromTo(P('.poll'), {{ opacity: 0, scale: .85, y: 40 }}, {{ opacity: 1, scale: 1, y: 0, duration: .45, ease: 'back.out(1.6)' }}, {POLL});
    fingerTo(P('.finger'), 735, 1290, {ANS - 0.5});
    tap(P('.finger'), {ANS}); press(P('.op.ok'), {ANS});
    tl.to(P('.op.ok'), {{ backgroundColor: '#ede5ff', boxShadow: 'inset 0 0 0 5px #7c3aed', duration: .12 }}, {ANS + 0.05});
    tl.to(P('.finger'), {{ x: 950, y: 1700, duration: .4, ease: 'power3.inOut' }}, {ANS + 0.4});
    tl.to(P('.op.ok'), {{ backgroundColor: '#dcfce7', boxShadow: 'inset 0 0 0 6px #22c55e', duration: .2 }}, {RIGHT});
    tl.fromTo(P('.op.ok i'), {{ opacity: 0, scale: .3 }}, {{ opacity: 1, scale: 1, duration: .35, ease: 'back.out(2.5)' }}, {RIGHT});
    tl.fromTo(P('.pf'), {{ opacity: 0, y: 20 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, {RIGHT + 0.1});
    pop(P('.xp'), {RIGHT + 0.2}, .4);
{star_js}
    fingerAway(P('.finger'), {RIGHT + 0.2});
    headOut(); scrOut();
""", r"""
    .dim{position:absolute;inset:170px 0 0;background:#e9e4fb}
    .poll{position:absolute;left:40px;right:40px;top:300px;padding:40px 40px 46px;text-align:center}
    .ph{font-weight:900;font-size:36px;color:#7c3aed}
    .pq{font-weight:900;font-size:110px;color:#1f2a44;margin:20px 0 30px}
    .ops{display:grid;grid-template-columns:1fr 1fr;gap:22px}
    .op{position:relative;height:150px;border-radius:32px;background:#f5f2fd;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:78px;color:#231a45}
    .op i{position:absolute;left:24px;top:22px;font-style:normal;font-size:48px;color:#16a34a;opacity:0}
    .pf{margin-top:26px;font-weight:900;font-size:42px;color:#15803d;opacity:0}
    .xp{position:absolute;left:200px;right:200px;top:1150px;text-align:center;background:linear-gradient(135deg,#fde047,#f59e0b);color:#78350f;border-radius:99px;padding:18px 0;font-weight:900;font-size:52px;box-shadow:0 10px 0 #b45309;opacity:0}
    .st{position:absolute;left:360px;top:1000px;font-size:80px;opacity:0}
""")

# ═══════════════════ s09 — گفت‌وگو و شکلک‌ها ═══════════════════
sid = "s09"
a = vo(sid)
TYPE0 = a + 1.6
typed = "ممنون خانم!"
type_js = "\n".join(
    f"    tl.set(P('.inp'), {{ textContent: {json.dumps(typed[:i + 1], ensure_ascii=False)} }}, {TYPE0 + i * 0.07:.3f});" for i in range(len(typed)))
SEND = TYPE0 + len(typed) * 0.07 + 0.25
reacts = [("👏", 520), ("❤️", 600), ("😂", 470), ("🌟", 640), ("👍", 560)]
react_html = "".join(f'<div class="re re{i}" style="left:{x}px" data-layout-ignore>{e}</div>' for i, (e, x) in enumerate(reacts))
react_js = "\n".join(
    f"    tl.fromTo(P('.re{i}'), {{ opacity: 0, y: 0, scale: .6 }}, {{ opacity: 1, y: -260, scale: 1.1, duration: .9, ease: 'power2.out' }}, {a + 2.0 + i * 0.16:.3f});\n"
    f"    tl.to(P('.re{i}'), {{ opacity: 0, y: -420, duration: .5, ease: 'power2.in' }}, {a + 2.9 + i * 0.16:.3f});"
    for i in range(len(reacts)))
scenes[sid] = scene_file(sid, f"""
{head('۷ · گفت‌وگو', 'پیام بده، <em>مؤدب</em> باش')}
    <div class="scr"><div class="layer a">
      {TOPBAR}
      <div class="tabs"><span class="on">💬 گفت‌وگو</span><span>👥 نفرات</span><span>📊 سؤال</span></div>
      <div class="msg m1 t"><b>👩‍🏫 خانم احمدی</b>سلام بچه‌ها! آماده‌اید؟</div>
      <div class="msg m2"><b>آرمین</b>سلام خانم! 👋</div>
      <div class="msg m3"><b>سارا</b>من آماده‌ام 😊</div>
      <div class="msg m4 me"><b>من</b>ممنون خانم!</div>
      <div class="emo"><span>👋</span><span>👍</span><span>😀</span><span>❤️</span><span>🙏</span><span>❓</span><span>✅</span><span>🌟</span></div>
      <div class="send"><div class="inp"></div><div class="sb">➤</div></div>
      {react_html}
      <div class="polite" data-layout-allow-occlusion>🙂 همیشه مؤدب باش</div>
    </div></div>
""", helpers(sid) + f"""
    headIn(.05); scrIn(0);
    tl.set(P('.inp'), {{ textContent: 'پیام بنویس…' }}, 0);
    [['.m1', {a + 0.1}], ['.m2', {a + 0.6}], ['.m3', {a + 1.1}]].forEach(([s, at]) => tl.fromTo(P(s), {{ opacity: 0, x: 80 }}, {{ opacity: 1, x: 0, duration: .35, ease: 'expo.out' }}, at));
{type_js}
    tl.fromTo(P('.m4'), {{ opacity: 0, y: 60 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, {SEND});
    tl.set(P('.inp'), {{ textContent: 'پیام بنویس…' }}, {SEND});
{react_js}
    pop(P('.polite'), {a + 4.2});
    headOut(); scrOut();
""", r"""
    .tabs{position:absolute;left:30px;right:30px;top:196px;height:96px;background:#efeafc;border-radius:28px;display:flex;padding:8px;gap:8px}
    .tabs span{flex:1;border-radius:22px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:28px;color:var(--mut)}
    .tabs span.on{background:#fff;color:#7c3aed;box-shadow:0 4px 0 #e3dbfa}
    .msg{position:absolute;right:30px;max-width:620px;background:#fff;border-radius:30px;padding:18px 26px 22px;font-weight:700;font-size:34px;box-shadow:0 0 0 2px var(--line);opacity:0}
    .msg b{display:block;font-weight:900;font-size:28px;color:#7c3aed;margin-bottom:4px}
    .msg.t{background:#eff6ff;box-shadow:0 0 0 2px #bfdbfe}
    .msg.t b{color:#1d4ed8}
    .msg.me{right:auto;left:30px;background:#ede5ff}
    .m1{top:330px}.m2{top:500px}.m3{top:650px}.m4{top:800px}
    .emo{position:absolute;left:24px;right:24px;top:1010px;display:flex;justify-content:space-between}
    .emo span{width:78px;height:78px;border-radius:22px;background:#f1edfb;display:flex;align-items:center;justify-content:center;font-size:44px}
    .send{position:absolute;left:24px;right:24px;top:1120px;height:120px;display:flex;gap:16px}
    .inp{flex:1;background:#fff;border-radius:32px;box-shadow:inset 0 0 0 3px var(--line);display:flex;align-items:center;padding:0 30px;font-weight:700;font-size:34px;color:#8a83a8}
    .sb{width:120px;border-radius:32px;background:#7c3aed;color:#fff;display:flex;align-items:center;justify-content:center;font-size:50px;transform:scaleX(-1)}
    .re{position:absolute;top:900px;font-size:90px;opacity:0}
    .polite{position:absolute;left:140px;right:140px;top:470px;text-align:center;background:#fff3c4;color:#7a4a00;border-radius:30px;padding:20px 0;font-weight:900;font-size:42px;box-shadow:0 0 0 4px #fcd34d,0 12px 24px rgba(0,0,0,.18);opacity:0}
""")

# ═══════════════════ s10 — فیلمِ کلاس‌های گذشته ═══════════════════
sid = "s10"
a = vo(sid)
LIST, PLAY = a + 1.55, a + 4.25
recs = [("ریاضی — کسرها", "۲۴ آبان", "۴۵"), ("علوم — آب", "۲۲ آبان", "۴۰"), ("فارسی — املا", "۲۰ آبان", "۳۵")]
rec_html = "".join(
    f'<div class="rc card r{i}" style="top:{330 + i * 250}px"><div class="pl">▶</div><div class="ri"><b>{t}</b><small>👥 چهارم الف · 👩‍🏫 خانم احمدی</small><small>🗓️ {d} · ⏱️ {m} دقیقه</small></div></div>'
    for i, (t, d, m) in enumerate(recs))
scenes[sid] = scene_file(sid, f"""
{head('۸ · فیلم‌ها', 'کلاس را <em>از دست</em> دادی؟')}
    <div class="scr"><div class="layer a">
      <div class="pg">کلاسِ آنلاین</div>
      <div class="sec">🎬 فیلمِ کلاس‌های گذشته</div>
      {rec_html}
      <div class="player" data-layout-allow-occlusion><div class="vid">👩‍🏫 <span>۳ + ۴ = ۷</span></div><div class="prog"><i></i></div></div>
    </div></div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.fromTo(P('.sec'), {{ opacity: 0, y: 20 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, {LIST - 0.2});
    [0, 1, 2].forEach((i) => tl.fromTo(P('.r' + i), {{ opacity: 0, x: -120 }}, {{ opacity: 1, x: 0, duration: .45, ease: 'expo.out' }}, {LIST} + i * .12));
    fingerTo(P('.finger'), 795, 965, {PLAY - 0.5});
    tap(P('.finger'), {PLAY}); press(P('.r0 .pl'), {PLAY});
    fingerAway(P('.finger'), {PLAY + 0.3});
    tl.to([P('.r0'), P('.r1'), P('.r2'), P('.sec')], {{ opacity: 0, duration: .2 }}, {PLAY + 0.2});
    tl.fromTo(P('.player'), {{ opacity: 0, scale: .4, y: -300 }}, {{ opacity: 1, scale: 1, y: 0, duration: .5, ease: 'expo.out' }}, {PLAY + 0.15});
    tl.fromTo(P('.prog i'), {{ scaleX: 0 }}, {{ scaleX: .45, duration: {dict(SCENES)[sid] - PLAY - 0.7:.2f}, ease: 'none' }}, {PLAY + 0.5});
    headOut(); scrOut();
""", r"""
    .layer.a{background:#3b0f17}
    .pg{position:absolute;right:40px;top:90px;color:#fff;font-weight:900;font-size:52px}
    .sec{position:absolute;right:40px;top:210px;color:#ffe9a8;font-weight:900;font-size:40px;opacity:0}
    .rc{position:absolute;left:30px;right:30px;height:220px;display:flex;align-items:center;gap:26px;padding:0 30px;opacity:0}
    .pl{flex:none;width:130px;height:130px;border-radius:36px;background:linear-gradient(135deg,#a78bfa,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:56px;box-shadow:0 8px 0 #5b21b6}
    .ri b{display:block;font-weight:900;font-size:40px}
    .ri small{display:block;font-weight:700;font-size:28px;color:var(--mut);margin-top:6px}
    .player{position:absolute;left:30px;right:30px;top:420px;height:520px;border-radius:40px;background:#120b2b;box-shadow:0 0 0 6px #fff,0 30px 50px rgba(0,0,0,.45);opacity:0;overflow:hidden}
    .vid{position:absolute;inset:0 0 70px;display:flex;align-items:center;justify-content:center;gap:30px;font-size:150px;background:linear-gradient(140deg,#c4b5fd,#7c3aed)}
    .vid span{font-weight:900;font-size:70px;color:#fff;background:rgba(0,0,0,.35);border-radius:24px;padding:4px 24px}
    .prog{position:absolute;left:30px;right:30px;bottom:26px;height:16px;border-radius:99px;background:#3b2a6b}
    .prog i{display:block;height:100%;border-radius:99px;background:#a78bfa;transform-origin:100% 50%}
""")

# ═══════════════════ s11 — آزمایشِ اتصال ═══════════════════
sid = "s11"
a = vo(sid)
VPN, TAP11 = a + 2.55, a + 4.95
checks = ["اینترنت", "صدا", "سرورِ کلاس"]
chk_html = "".join(f'<div class="ck k{i}"><span>{n}</span><i class="sp">⏳</i><i class="ok">✅</i></div>' for i, n in enumerate(checks))
chk_js = "\n".join(
    f"    tl.fromTo(P('.k{i}'), {{ opacity: 0, x: -60 }}, {{ opacity: 1, x: 0, duration: .3, ease: 'expo.out' }}, {TAP11 + 0.45 + i * 0.12:.3f});\n"
    f"    tl.to(P('.k{i} .sp'), {{ opacity: 0, duration: .1 }}, {TAP11 + 0.65 + i * 0.22:.3f});\n"
    f"    tl.fromTo(P('.k{i} .ok'), {{ opacity: 0, scale: .3 }}, {{ opacity: 1, scale: 1, duration: .3, ease: 'back.out(2.5)' }}, {TAP11 + 0.65 + i * 0.22:.3f});"
    for i in range(len(checks)))
scenes[sid] = scene_file(sid, f"""
{head('۹ · آزمایشِ اتصال', 'صدا <em>وصل نشد؟</em>')}
    <div class="scr">
      <div class="layer a">
        {TOPBAR}
        <div class="weak">صدا و تصویر وصل نشد؛ تخته، گفت‌وگو و سؤال‌ها کار می‌کند.<div class="chk">🔍 آزمایشِ اتصال</div></div>
        <div class="v card"><span>🛡️ فیلترشکن خاموش است؟</span><div class="mini"><div class="kn"></div></div><b>✅</b></div>
      </div>
      <div class="layer b">
        <div class="cp">🔍 آزمایشِ اتصال</div>
        <div class="ck vok"><span>فیلترشکن خاموش</span><i class="ok" style="opacity:1">✅</i></div>
        {chk_html}
        <div class="done">🟢 همه‌چیز درست است!</div>
      </div>
    </div>
{FINGER}
""", helpers(sid) + FINGER_INIT + f"""
    headIn(.05); scrIn(0);
    tl.fromTo(P('.weak'), {{ opacity: 0, y: -30 }}, {{ opacity: 1, y: 0, duration: .35, ease: 'expo.out' }}, .25);
    // «اول ببین فیلترشکن خاموش باشد»
    pop(P('.v'), {VPN - 0.35}, .8);
    tl.to(P('.mini'), {{ backgroundColor: '#22c55e', duration: .2 }}, {VPN});
    tl.to(P('.kn'), {{ x: 70, duration: .25, ease: 'back.out(1.6)' }}, {VPN});
    tl.fromTo(P('.v b'), {{ opacity: 0, scale: .3 }}, {{ opacity: 1, scale: 1, duration: .3, ease: 'back.out(2.5)' }}, {VPN + 0.15});
    fingerTo(P('.finger'), 540, 870, {TAP11 - 0.5});
    tap(P('.finger'), {TAP11}); press(P('.chk'), {TAP11});
    fingerAway(P('.finger'), {TAP11 + 0.3});
    tl.to(P('.layer.a'), {{ opacity: 0, filter: 'blur(10px)', duration: .2 }}, {TAP11 + 0.2});
    tl.fromTo(P('.layer.b'), {{ opacity: 0, filter: 'blur(10px)' }}, {{ opacity: 1, filter: 'blur(0px)', duration: .22, ease: 'power4.out' }}, {TAP11 + 0.3});
{chk_js}
    tl.fromTo(P('.done'), {{ opacity: 0, y: 20 }}, {{ opacity: 1, y: 0, duration: .3, ease: 'expo.out' }}, {TAP11 + 1.2});
    headOut(); scrOut();
""", r"""
    .weak{position:absolute;left:30px;right:30px;top:200px;background:#fff7d6;border-radius:30px;padding:26px 28px;font-weight:800;font-size:31px;line-height:1.6;color:#713f12;box-shadow:0 0 0 3px #fcd34d;opacity:0}
    .chk{margin-top:16px;display:inline-block;background:#7c3aed;color:#fff;border-radius:24px;padding:12px 26px;font-weight:900;font-size:32px;box-shadow:0 6px 0 #5b21b6}
    .v{position:absolute;left:30px;right:30px;top:640px;height:170px;display:flex;align-items:center;gap:20px;padding:0 30px;opacity:0}
    .v span{flex:1;font-weight:900;font-size:36px}
    .v b{font-size:56px;opacity:0}
    .mini{position:relative;width:150px;height:80px;border-radius:99px;background:#ef4444}
    .kn{position:absolute;left:8px;top:8px;width:64px;height:64px;border-radius:50%;background:#fff}
    .layer.b{background:#f4f1ff;opacity:0}
    .cp{position:absolute;right:40px;top:90px;font-weight:900;font-size:50px;color:#4c1d95}
    .ck{position:absolute;left:30px;right:30px;height:130px;background:#fff;border-radius:30px;box-shadow:0 0 0 2px var(--line);display:flex;align-items:center;padding:0 34px;font-weight:900;font-size:40px}
    .ck span{flex:1}
    .ck i{font-style:normal;font-size:52px;position:absolute;left:34px}
    .ck .ok{opacity:0}
    .vok{top:220px;background:#f0fdf4;box-shadow:0 0 0 3px #86efac}
    .k0{top:380px}.k1{top:540px}.k2{top:700px}
    .k0,.k1,.k2{opacity:0}
    .done{position:absolute;left:30px;right:30px;top:900px;text-align:center;font-weight:900;font-size:46px;color:#15803d;opacity:0}
""")

# ═══════════════════ s12 — پایان ═══════════════════
sid = "s12"
a = vo(sid)
C1, C2, TT, LOGO = a + 0.0, a + 1.15, a + 3.45, a + 4.75
scenes[sid] = scene_file(sid, f"""
    <div class="rc1">⛔ فیلترشکن خاموش</div>
    <div class="rc2">🎧 هدفون روی گوش</div>
    <div class="big go">{words('بزن بریم <em>کلاس!</em> 🚀')}</div>
    <div class="brand"><img src="assets/img/hero-emblem-560.webp" alt="ستاره ماه"><b>ستاره <i>ماه</i></b><small>کلاسِ آنلاین · منتظرِ توست!</small></div>
""", helpers(sid) + f"""
    pop(P('.rc1'), {C1}, .7);
    pop(P('.rc2'), {C2}, .7);
    tl.fromTo(P('.go').querySelectorAll('.w'), {{ opacity: 0, filter: 'blur(8px)', y: 30 }}, {{ opacity: 1, filter: 'blur(0px)', y: 0, duration: .3, stagger: .1, ease: 'expo.out' }}, {TT});
    tl.fromTo(P('.brand'), {{ opacity: 0, y: 60 }}, {{ opacity: 1, y: 0, duration: .55, ease: 'expo.out' }}, {LOGO});
    tl.fromTo(P('.brand img'), {{ rotation: -20, scale: .6 }}, {{ rotation: 0, scale: 1, duration: .7, ease: 'back.out(1.7)' }}, {LOGO});
""", r"""
    .rc1,.rc2{position:absolute;left:150px;right:150px;text-align:center;border-radius:99px;padding:18px 0 22px;font-weight:900;font-size:50px;color:#fff;opacity:0}
    .rc1{top:220px;background:#dc2626;box-shadow:0 10px 0 #7f1d1d}
    .rc2{top:370px;background:#7c3aed;box-shadow:0 10px 0 #4c1d95}
    .go{position:absolute;left:40px;right:40px;top:1130px;font-size:120px;line-height:1.2}
    .brand{position:absolute;left:0;right:0;top:1440px;text-align:center;color:#fff;opacity:0}
    .brand img{width:200px;height:200px;border-radius:50%;box-shadow:0 0 0 8px rgba(255,255,255,.25)}
    .brand b{display:block;font-weight:900;font-size:76px;margin-top:6px}
    .brand b i{font-style:normal;color:#ffd84d}
    .brand small{display:block;font-weight:800;font-size:38px;color:#e4dcff;margin-top:4px}
""")

# ──────────────────────────── phone (persistent) ─────────────────────────────
PH_DUR = round(PHONE_OUT - PHONE_IN + 0.5, 3)
phone = f"""<template>
  <style>
    #root{{position:absolute;inset:0}}
    .body{{position:absolute;left:130px;top:500px;width:820px;height:1340px;border-radius:78px;background:#120b2b;box-shadow:0 0 0 4px #3a2a7a,0 40px 80px rgba(0,0,0,.55)}}
    .screen{{position:absolute;left:20px;top:20px;width:780px;height:1300px;border-radius:58px;background:#f4f1ff}}
    .notch{{position:absolute;left:310px;top:40px;width:200px;height:52px;border-radius:99px;background:#120b2b}}
  </style>
  <div id="root" data-composition-id="phone" data-width="{W}" data-height="{H}">
    <div class="body" id="phone-body"><div class="screen"></div></div>
  </div>
  <script>
    const tl = gsap.timeline({{ paused: true }});
    tl.fromTo('#phone-body', {{ y: 1400 }}, {{ y: 0, duration: .6, ease: 'expo.out' }}, 0);
    tl.to('#phone-body', {{ y: 1500, duration: .45, ease: 'power3.in' }}, {PH_DUR - 0.5});
    window.__timelines["phone"] = tl;
  </script>
</template>
"""
notch = f"""<template>
  <style>#root{{position:absolute;inset:0}} .n{{position:absolute;left:440px;top:540px;width:200px;height:50px;border-radius:99px;background:#120b2b}}</style>
  <div id="root" data-composition-id="notch" data-width="{W}" data-height="{H}"><div class="n" id="notch-pill"></div></div>
  <script>
    const tl = gsap.timeline({{ paused: true }});
    tl.fromTo('#notch-pill', {{ y: 1400 }}, {{ y: 0, duration: .6, ease: 'expo.out' }}, 0);
    tl.to('#notch-pill', {{ y: 1500, duration: .45, ease: 'power3.in' }}, {PH_DUR - 0.5});
    window.__timelines["notch"] = tl;
  </script>
</template>
"""

# ───────────────────────────── Mahoo (persistent) ─────────────────────────────
# جای ماهو در هر صحنه: (x, y, scale)  — x,y گوشه‌ی بالا-چپِ نقاشیِ ۳۰۰×۳۰۰
POS = {"hero": (390, 640, 1.2), "corner": (36, 120, 0.82), "outro": (390, 600, 1.45)}
mjs = []
mjs.append(f"    tl.set('#mh-wrap', {{ x: {POS['hero'][0]}, y: -420, scale: {POS['hero'][2]} }}, 0);")
mjs.append(f"    tl.to('#mh-wrap', {{ y: {POS['hero'][1]}, duration: .7, ease: 'back.out(1.5)' }}, .25);")
# s01: سلام → دست تکان؛ «قانونِ مهم» → تعجب؛ کلید را می‌زند
s01 = START["s01"]
mjs.append(f"    tl.to('#mh-armr', {{ rotation: -28, duration: .22, yoyo: true, repeat: 5, ease: 'sine.inOut', svgOrigin: '95 80' }}, {s01 + 0.4});")
mjs.append(f"    tl.to('#mh-eyes', {{ opacity: 0, duration: .05 }}, {s01 + vo('s01') + 5.6}); tl.to('#mh-wow', {{ opacity: 1, duration: .05 }}, {s01 + vo('s01') + 5.6});")
mjs.append(f"    tl.to('#mh-wow', {{ opacity: 0, duration: .05 }}, {s01 + vo('s01') + 7.0}); tl.to('#mh-eyes', {{ opacity: 1, duration: .05 }}, {s01 + vo('s01') + 7.0});")
mjs.append(f"    tl.to('#mh-wrap', {{ x: 120, y: 1000, scale: .9, duration: .4, ease: 'power3.inOut' }}, {s01 + FLIP - 0.4});")
mjs.append(f"    tl.to('#mh-wrap', {{ x: {POS['hero'][0]}, y: {POS['hero'][1]}, scale: {POS['hero'][2]}, duration: .5, ease: 'expo.out' }}, {s01 + FLIP + 0.25});")
# به گوشه برای بقیه‌ی فیلم
mjs.append(f"    tl.to('#mh-wrap', {{ x: {POS['corner'][0]}, y: {POS['corner'][1]}, scale: {POS['corner'][2]}, duration: .6, ease: 'power3.inOut' }}, {START['s02'] - 0.15});")
# شادی‌ها
for at, dur in [(START["s04"] + vo("s04") + 2.6, 1.0), (START["s08"] + vo("s08") + 2.8, 1.6), (START["s11"] + vo("s11") + 6.0, 0.9)]:
    mjs.append(f"    tl.to('#mh-eyes', {{ opacity: 0, duration: .05 }}, {at:.3f}); tl.to('#mh-happy', {{ opacity: 1, duration: .05 }}, {at:.3f});")
    mjs.append(f"    tl.to('#mh-float', {{ y: -26, duration: .18, yoyo: true, repeat: 3, ease: 'power2.out' }}, {at:.3f});")
    mjs.append(f"    tl.to('#mh-happy', {{ opacity: 0, duration: .05 }}, {at + dur:.3f}); tl.to('#mh-eyes', {{ opacity: 1, duration: .05 }}, {at + dur:.3f});")
# پایان: وسط، هورا
mjs.append(f"    tl.to('#mh-wrap', {{ x: {POS['outro'][0]}, y: {POS['outro'][1]}, scale: {POS['outro'][2]}, duration: .7, ease: 'expo.out' }}, {START['s12'] + 0.1});")
o = START["s12"] + vo("s12") + 3.45
mjs.append(f"    tl.to('#mh-eyes', {{ opacity: 0, duration: .05 }}, {o:.3f}); tl.to('#mh-happy', {{ opacity: 1, duration: .05 }}, {o:.3f});")
mjs.append(f"    tl.to('#mh-armr', {{ rotation: -30, duration: .2, yoyo: true, repeat: 9, ease: 'sine.inOut', svgOrigin: '95 80' }}, {o:.3f});")
mjs.append(f"    tl.to('#mh-arml', {{ rotation: 30, duration: .2, yoyo: true, repeat: 9, ease: 'sine.inOut', svgOrigin: '25 80' }}, {o + 0.1:.3f});")
mjs.append(f"    tl.to('#mh-float', {{ y: -30, duration: .25, yoyo: true, repeat: 7, ease: 'power2.out' }}, {o:.3f});")
# دهان: هنگامِ صحبت باز و بسته می‌شود (در مکث‌ها بسته)
mouth = []
for sid_, _ in SCENES:
    v0 = START[sid_] + vo(sid_)
    t0, end = v0, v0 + VOICE_LEN[sid_]
    segs, cur = [], 0.0
    for p0, p1 in PAUSES[sid_]:
        segs.append((cur, p0)); cur = p1
    segs.append((cur, VOICE_LEN[sid_]))
    for s0, s1 in segs:
        k = 0
        tt = s0
        while tt < s1 - 0.05:
            mouth.append((round(v0 + tt, 3), 1 if k % 2 == 0 else 0))
            tt += 0.13 + (0.03 if k % 3 == 0 else 0)
            k += 1
        mouth.append((round(v0 + s1, 3), 0))
mjs.append("    const MOUTH = " + json.dumps(mouth) + ";")
mjs.append("    MOUTH.forEach(([at, on]) => { tl.set('#mh-talk', { opacity: on }, at); tl.set('#mh-smile', { opacity: 1 - on }, at); });")
# پلک
bt, blinks = 2.3, []
while bt < TOTAL - 0.5:
    blinks.append(round(bt, 2))
    bt += 3.1 if int(bt) % 2 else 3.7
mjs.append("    const BLINK = " + json.dumps(blinks) + ";")
mjs.append("    BLINK.forEach((b) => { tl.to('#mh-eyes', { scaleY: .1, duration: .06, svgOrigin: '60 71' }, b); tl.to('#mh-eyes', { scaleY: 1, duration: .08, svgOrigin: '60 71' }, b + .08); });")
# شناوری و منگوله
n = int(TOTAL / 1.6)
mjs.append(f"    tl.fromTo('#mh-bob', {{ y: 0 }}, {{ y: -10, duration: .8, yoyo: true, repeat: {n * 2 - 1}, ease: 'sine.inOut' }}, 0);")
mjs.append(f"    tl.fromTo('#mh-tassel', {{ rotation: -4 }}, {{ rotation: 6, duration: 1.1, yoyo: true, repeat: {int(TOTAL / 1.1) - 1}, ease: 'sine.inOut', svgOrigin: '60 31.5' }}, 0);")

mahoo = f"""<template>
  <style>#root{{position:absolute;inset:0}} #mh-wrap{{position:absolute;left:0;top:0;width:300px;height:300px;transform-origin:0 0}}</style>
  <div id="root" data-composition-id="mahoo" data-width="{W}" data-height="{H}">
    <div id="mh-wrap"><div id="mh-bob">{MAHOO}</div></div>
  </div>
  <script>
    const tl = gsap.timeline({{ paused: true }});
{chr(10).join(mjs)}
    window.__timelines["mahoo"] = tl;
  </script>
</template>
"""

# ────────────────────────────── index.html ────────────────────────────────
hosts = []
for i, (sid_, d) in enumerate(SCENES):
    hosts.append(f'      <div id="{sid_}" data-composition-id="{sid_}" data-composition-src="compositions/{sid_}.html" data-start="{START[sid_]}" data-duration="{d}" data-track-index="{3}" data-track-kind="graphics" data-width="{W}" data-height="{H}" style="z-index:3"></div>')
audio = []
for i, (sid_, d) in enumerate(SCENES):
    audio.append(f'      <audio id="vo-{sid_}" data-start="{round(START[sid_] + vo(sid_), 3)}" data-duration="{VOICE_LEN[sid_]}" data-track-index="20" data-volume="1" src="assets/voice/{sid_}.wav"></audio>')
BED_SPLIT = round(START["s12"] + vo("s12") + VOICE_LEN["s12"] - 0.2, 3)
audio.append(f'      <audio id="music-a" data-start="0" data-duration="{BED_SPLIT + 0.6}" data-track-index="21" data-volume="0.12" data-fade-in="0.8" data-fade-out="0.6" src="assets/music/bed.mp3"></audio>')
audio.append(f'      <audio id="music-b" data-start="{BED_SPLIT}" data-duration="{round(TOTAL - BED_SPLIT, 3)}" data-media-start="{BED_SPLIT}" data-track-index="22" data-volume="0.3" data-fade-in="0.6" data-fade-out="1.4" src="assets/music/bed.mp3"></audio>')
SFX = [
    ("s01", FLIP, "switch_007", .7), ("s01", FLIP + 0.3, "impactSoft_medium_001", .7),
    ("s02", 0.0, "card-slide-1", .5), ("s02", CUT, "error_005", .45), ("s02", FIX, "switch_007", .6), ("s02", FIX + 0.25, "drop_002", .5),
    ("s03", T1, "click2", .65), ("s03", SW, "card-slide-1", .45), ("s03", T2, "click_003", .7),
    ("s04", TAP, "click2", .65), ("s04", TAP + 0.25, "impactSoft_medium_004", .55), ("s04", vo("s04") + 2.7, "impactGlass_light_001", .45),
    ("s05", HAND, "click2", .65), ("s05", OK, "drop_001", .5), ("s05", MIC, "click_003", .7),
    ("s06", GRN, "drop_002", .45), ("s06", CAM, "click2", .65), ("s06", CAM + 0.2, "drop_001", .5),
    ("s07", DT, "click2", .6), ("s07", DT + 0.24, "click_003", .6),
    ("s08", POLL, "drop_002", .5), ("s08", ANS, "click2", .65), ("s08", RIGHT, "impactGlass_light_001", .55),
    ("s09", vo("s09") + 0.1, "card-slide-1", .4), ("s09", SEND, "click_003", .6),
    ("s10", LIST, "card-slide-1", .45), ("s10", PLAY, "click2", .65),
    ("s11", VPN, "switch_007", .55), ("s11", TAP11, "click2", .65), ("s11", TAP11 + 0.75, "drop_001", .45), ("s11", TAP11 + 1.2, "impactGlass_light_001", .45),
    ("s12", 0.05, "card-slide-1", .45), ("s12", C1, "drop_001", .5), ("s12", C2, "drop_002", .5), ("s12", TT, "impactSoft_medium_004", .6), ("s12", LOGO, "impactBell_heavy_000", .55),
]
import subprocess
def flen(f):
    out = subprocess.run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', os.path.join(ROOT, 'assets', 'sfx', f + '.ogg')], capture_output=True, text=True).stdout
    return round(float(out.strip()) - 0.005, 3)
for i, (sid_, local, f, vol) in enumerate(SFX):
    audio.append(f'      <audio id="fx-{i:02d}" data-start="{round(START[sid_] + local, 3)}" data-duration="{flen(f)}" data-track-index="{30 + i % 3}" data-volume="{vol}" src="assets/sfx/{f}.ogg"></audio>')

index = f"""<!doctype html>
<html lang="fa" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width={W}, height={H}" />
    <script src="assets/vendor/gsap.min.js"></script>
    <style>{CSS}</style>
  </head>
  <body>
    <div id="root" data-composition-id="main" data-start="0" data-duration="{TOTAL}" data-width="{W}" data-height="{H}">
      <div class="ground"><div class="stars" id="stars"></div></div>
      <div id="phone" data-composition-id="phone" data-composition-src="compositions/phone.html" data-start="{PHONE_IN}" data-duration="{PH_DUR}" data-track-index="2" data-track-kind="graphics" data-width="{W}" data-height="{H}" style="z-index:2"></div>
{chr(10).join(hosts)}
      <div id="notch" data-composition-id="notch" data-composition-src="compositions/notch.html" data-start="{PHONE_IN}" data-duration="{PH_DUR}" data-track-index="4" data-track-kind="graphics" data-width="{W}" data-height="{H}" style="z-index:4"></div>
      <div id="mahoo" data-composition-id="mahoo" data-composition-src="compositions/mahoo.html" data-start="0" data-duration="{TOTAL}" data-track-index="5" data-track-kind="graphics" data-width="{W}" data-height="{H}" style="z-index:5"></div>
{chr(10).join(audio)}
    </div>
    <script>
      const tl = gsap.timeline({{ paused: true }});
      tl.fromTo('#stars', {{ y: 0 }}, {{ y: -160, duration: {TOTAL}, ease: 'none' }}, 0);
      window.__timelines["main"] = tl;
    </script>
  </body>
</html>
"""

os.makedirs(os.path.join(ROOT, "compositions"), exist_ok=True)
with open(os.path.join(ROOT, "index.html"), "w", encoding="utf-8") as f:
    f.write(index)
for name, html in list(scenes.items()) + [("phone", phone), ("notch", notch), ("mahoo", mahoo)]:
    with open(os.path.join(ROOT, "compositions", f"{name}.html"), "w", encoding="utf-8") as f:
        f.write(html)
print("total", TOTAL, "scenes", START)
