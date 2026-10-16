# Generates the three entry compositions from one template so they never drift apart.
import sys
TPL = r'''<!doctype html>
<html lang="fa">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=__W__, height=__H__" />
<title>ستاره ماه — __TITLE__</title>
<script src="assets/vendor/gsap.min.js"></script>
<script type="importmap">{"imports":{"three":"./assets/three/three.module.js","three/addons/":"./assets/three/addons/"}}</script>
<link rel="stylesheet" href="assets/overlays.css" />
<style>
@font-face { font-family: "Vazirmatn"; src: url("assets/fonts/Vazirmatn-Medium.woff2") format("woff2"); font-weight: 500; }
@font-face { font-family: "Vazirmatn"; src: url("assets/fonts/Vazirmatn-SemiBold.woff2") format("woff2"); font-weight: 600; }
@font-face { font-family: "Vazirmatn"; src: url("assets/fonts/Vazirmatn-Bold.woff2") format("woff2"); font-weight: 700; }
@font-face { font-family: "Vazirmatn"; src: url("assets/fonts/Vazirmatn-ExtraBold.woff2") format("woff2"); font-weight: 800; }
@font-face { font-family: "Vazirmatn"; src: url("assets/fonts/Vazirmatn-Black.woff2") format("woff2"); font-weight: 900; }
@font-face { font-family: "Poppins"; src: url("assets/fonts/Poppins-800.woff2") format("woff2"); font-weight: 800; }
body { margin: 0; background: #050a1c; }
#root { position: relative; width: 100%; height: 100%; overflow: hidden; background: #050a1c; }
#film { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
#vig { position: absolute; inset: 0; background: radial-gradient(ellipse 80% 75% at 50% 50%, rgba(0,0,0,0) 55%, rgba(2,6,22,0.55) 100%); pointer-events: none; }
#flash { position: absolute; inset: 0; opacity: 0; background: radial-gradient(circle at 50% 50%, #fff6dc 0%, #ffe3a0 55%, #ffcf73 100%); pointer-events: none; }
</style>
</head>
<body>
<div id="root" data-composition-id="__ID__" data-start="0" data-width="__W__" data-height="__H__" data-duration="__DUR__" data-fps="60">
  <canvas id="film"></canvas>
  <div id="vig"></div>
__AUDIO__
</div>
<script type="module">
import { createFilm } from "./assets/film.js";
import { buildOverlays } from "./assets/overlays.js";
window.__hf = window.__hf || {};
window.__hf.buildReady = window.__hf.buildReady || {};
const tl = gsap.timeline({ paused: true });
window.__hf.buildReady.film = (async () => {
  const film = await createFilm({ canvas: document.getElementById("film"), width: __IW__, height: __IH__, layout: "__LAYOUT__" });
  const flash = document.getElementById("flash");
  const draw = (t) => { const st = film.renderAt(t); flash.style.opacity = st.flash.toFixed(3); };
  window.addEventListener("hf-seek", (e) => draw(e.detail.time));
  draw(window.__hfThreeTime || 0);
})();
__OVERLAYS__
const fl = document.createElement("div"); fl.id = "flash"; const subs = document.getElementById("subs"); document.getElementById("root").insertBefore(fl, document.getElementById("ov") ? document.getElementById("ov").nextSibling : null);
window.__timelines["__ID__"] = tl;
</script>
</body>
</html>
'''
def make(path, **kw):
    s = TPL
    for k, v in kw.items():
        s = s.replace(f"__{k}__", str(v))
    open(path, "w").write(s)

AUDIO = '  <audio id="score" src="assets/audio/mix.m4a" data-start="0" data-duration="55" data-track-index="10" data-volume="1"></audio>'
iw, ih = (int(sys.argv[1]), int(sys.argv[2])) if len(sys.argv) > 2 else (1920, 1080)
make("cuts/landscape/index.html", W=3840, H=2160, IW=iw, IH=ih, ID="starmah-film", TITLE="فیلم معرفی", DUR=55, LAYOUT="land", AUDIO=AUDIO,
     OVERLAYS='buildOverlays(document.getElementById("root"), tl, { layout: "land" });')
make("cuts/vertical/index.html", W=1080, H=1920, IW=ih, IH=iw, ID="starmah-reels", TITLE="ریلز", DUR=55, LAYOUT="vert", AUDIO=AUDIO,
     OVERLAYS='buildOverlays(document.getElementById("root"), tl, { layout: "vert", subtitles: true });')
make("cuts/loop/index.html", W=1920, H=1080, IW=1920, IH=1080, ID="starmah-loop", TITLE="لوپ", DUR=10, LAYOUT="loop", AUDIO="",
     OVERLAYS='')
print("ok", iw, ih)
