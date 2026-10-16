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
</style>
</head>
<body>
<div id="root" data-composition-id="__ID__" data-start="0" data-width="__W__" data-height="__H__" data-duration="__DUR__" data-fps="60">
  <canvas id="film"></canvas>
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
  window.addEventListener("hf-seek", (e) => film.renderAt(e.detail.time));
  film.renderAt(window.__hfThreeTime || 0);
})();
__OVERLAYS__
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

AUDIO = '  <audio id="score" src="assets/audio/mix.wav" data-start="0" data-duration="55" data-track-index="10" data-volume="1"></audio>'
iw, ih = (int(sys.argv[1]), int(sys.argv[2])) if len(sys.argv) > 2 else (1920, 1080)
make("cuts/landscape/index.html", W=3840, H=2160, IW=iw, IH=ih, ID="starmah-film", TITLE="فیلم معرفی", DUR=55, LAYOUT="land", AUDIO=AUDIO,
     OVERLAYS='buildOverlays(document.getElementById("root"), tl, { layout: "land" });')
make("cuts/vertical/index.html", W=1080, H=1920, IW=ih, IH=iw, ID="starmah-reels", TITLE="ریلز", DUR=55, LAYOUT="vert", AUDIO=AUDIO,
     OVERLAYS='buildOverlays(document.getElementById("root"), tl, { layout: "vert", subtitles: true });')
make("cuts/loop/index.html", W=1920, H=1080, IW=1920, IH=1080, ID="starmah-loop", TITLE="لوپ", DUR=10, LAYOUT="loop", AUDIO="",
     OVERLAYS='')
print("ok", iw, ih)
