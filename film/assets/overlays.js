// Professionally typeset Persian overlays (DOM, crisp at output resolution) + their GSAP choreography.
// All copy lives here so the 16:9 and 9:16 cuts stay word-for-word identical.
const fa = (n) => String(n).replace(/\d/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[d]);

export const SUBS = [
  [0.5, 5.9, "تا حالا فکر کردی یه سؤال ساده، می‌تونه درِ یه دنیای شگفت‌انگیز رو باز کنه؟"],
  [8.2, 12.9, "خوش اومدی به ستاره ماه… جایی که یادگیری، یه ماجراجویی تازه‌ست!"],
  [13.6, 20.8, "از کشف رازهای علوم و دنیای کلمات، تا الگوهای ریاضی و بازی‌های هیجان‌انگیز!"],
  [21.8, 28.8, "اینجا هر بازی، فرصتی برای فکر کردن، تجربه کردن و کشف کردنه!"],
  [29.6, 36.8, "مسابقه‌های زنده، چالش‌های جذاب و کلی هیجان… تا یاد گرفتن، شیرین‌تر بشه!"],
  [37.4, 43.9, "چون برای ما، فقط جواب درست مهم نیست… تلاش کردن، پیشرفت و لذت یاد گرفتنه که ارزش داره."],
  [48.6, 52.8, "ستاره ماه… جایی که هر کودک می‌تونه ستاره‌ی یادگیری خودش باشه!"],
];

const LAYOUTS = {
  land: { mixdx: 250, mixdy: -250, puzdx: 260, puzdy: -200, sbdx: -200, rowup: -150, rowdn: 150, dpdx1: 200, dpdx2: -200, logoup: -210, logoscale: 0.78, shootfrom: 700, shootto: -760 },
  vert: { mixdx: 110, mixdy: -110, puzdx: 120, puzdy: -90, sbdx: 80, rowup: -62, rowdn: 62, dpdx1: 80, dpdx2: -80, logoup: -150, logoscale: 0.8, shootfrom: 420, shootto: -440 },
};

export function buildOverlays(root, tl, { layout = "land", subtitles = false, subTimes = null } = {}) {
  const L = LAYOUTS[layout];
  const el = document.createElement("div");
  el.id = "ov";
  el.className = `ov ${layout}`;
  el.innerHTML = `
  <!-- SCENE 4: games inside the portal -->
  <div id="g-wrap">
    <div class="g-chip">بازی و یادگیری</div>
    <div class="g-card" id="g1">
      <div class="g-title">الگوی عددی</div>
      <div class="g-seq"><span>۲</span><i>،</i><span>۴</span><i>،</i><span>۶</span><i>،</i><span class="q"><em id="qm">؟</em><em id="q8">۸</em></span></div>
      <div class="g-opts"><b id="g1a">۷</b><b id="g1b">۸</b><b id="g1c">۹</b></div>
      <div class="g-hint" id="g1hint">دوباره فکر کن!</div>
      <div class="g-ok" id="g1ok">آفرین!</div>
    </div>
    <div class="g-card" id="g2">
      <div class="g-title">کلمه‌ی درست</div>
      <div class="g-q">کدام درست نوشته شده؟</div>
      <div class="g-opts words"><b id="g2a">خورشید</b><b id="g2b">خرشید</b></div>
      <div class="g-ok" id="g2ok">درسته!</div>
    </div>
    <div class="g-card" id="g3">
      <div class="g-title">آزمایش رنگ‌ها</div>
      <div class="g-mix"><span class="dot blue" id="g3b"></span><em id="g3plus">+</em><span class="dot yellow" id="g3y"></span><em id="g3eq">=</em><span class="dot green" id="g3g"></span></div>
      <div class="g-mixlbl"><span>آبی</span><span>زرد</span><span id="g3lbl">سبز!</span></div>
    </div>
    <div class="g-card" id="g4">
      <div class="g-title">پازل</div>
      <div class="g-puz"><i class="p1"></i><i class="p2"></i><i class="p3"></i><i class="p4" id="g4p"></i><div class="puz-star" id="g4s"></div></div>
      <div class="g-ok" id="g4ok">پازل کامل شد!</div>
    </div>
    <div class="g-prog"><i id="pr1"></i><i id="pr2"></i><i id="pr3"></i><i id="pr4"></i></div>
  </div>

  <!-- SCENE 5: live competition -->
  <div id="q-wrap">
    <div class="q-top"><span class="live"><i></i>زنده</span><span class="cat">ریاضی</span><span class="cat on">علوم</span><span class="cat">فارسی</span></div>
    <div class="q-card" id="qcard">
      <div class="q-n">سؤال ۳ از ۵</div>
      <div class="q-text">آب در دمای چند درجه‌ی سانتی‌گراد می‌جوشد؟</div>
      <div class="q-opts"><b id="qa">۵۰ درجه</b><b id="qb">۱۰۰ درجه</b><b id="qc">۱۵۰ درجه</b></div>
    </div>
    <div class="cd" id="cd"><svg viewBox="0 0 200 200"><circle class="bg" cx="100" cy="100" r="86"/><circle class="fg" id="cdring" cx="100" cy="100" r="86"/></svg><div id="cdn"><b id="cd5">۵</b><b id="cd4">۴</b><b id="cd3">۳</b><b id="cd2">۲</b><b id="cd1">۱</b><b id="cdok">✓</b></div></div>
    <div class="sb" id="sb">
      <div class="sb-h">جدول امتیاز</div>
      <div class="sb-row" id="r1"><span>تیم صاعقه آبی</span><b>۹۵۰</b></div>
      <div class="sb-row me" id="r2"><span>تیم ستاره‌ها</span><b id="meScore">۹۲۰</b></div>
      <div class="sb-row" id="r3"><span>تیم شلیک آتشین</span><b>۸۹۰</b></div>
      <div class="sb-row" id="r4"><span>جنگجویان کریپر</span><b>۸۷۰</b></div>
    </div>
  </div>

  <!-- SCENE 6: progress dashboard -->
  <div id="d-wrap">
    <div class="d-panel" id="dp1">
      <div class="d-h">پیشرفت من</div>
      <div class="d-bar"><span>ریاضی</span><div><i id="b1"></i></div><b id="b1n">۰٪</b></div>
      <div class="d-bar"><span>علوم</span><div><i id="b2"></i></div><b id="b2n">۰٪</b></div>
      <div class="d-bar"><span>فارسی</span><div><i id="b3"></i></div><b id="b3n">۰٪</b></div>
      <div class="d-stars"><span class="st"></span><b id="dstars">۱۱۸۰</b><span>ستاره</span></div>
    </div>
    <div class="d-panel" id="dp2">
      <div class="d-h">مسیر رشد</div>
      <div class="d-path"><div class="d-line"><i id="dline"></i></div>
        <div class="d-step" id="ds1"><i></i><span>کنجکاوی</span></div>
        <div class="d-step" id="ds2"><i></i><span>تلاش</span></div>
        <div class="d-step" id="ds3"><i></i><span>پیشرفت</span></div>
        <div class="d-step" id="ds4"><i></i><span>اعتمادبه‌نفس</span></div>
      </div>
    </div>
  </div>

  <!-- SCENES 7–8: logo + brand moment -->
  <div id="f-wrap">
    <div id="logo-frame"><div id="logo-halo"></div><img id="logo" src="assets/img/hero-emblem-880.webp" alt="" /></div>
    <div id="f-title">ستاره ماه</div>
    <div id="f-tag">یادگیری، یک ماجراجویی شیرین!</div>
    <div id="f-url-wrap"><div id="f-url">STARMAH.IR</div><div id="shoot"><i class="tail"></i><i class="head"></i></div></div>
  </div>

  <div id="subs"></div>`;
  root.appendChild(el);
  const $ = (s) => el.querySelector(s);
  const all = (s) => el.querySelectorAll(s);

  /* ---------------- scene 4: games */
  tl.set("#g-wrap", { autoAlpha: 0 }, 0);
  tl.to("#g-wrap", { autoAlpha: 1, duration: 0.4 }, 21.85);
  tl.fromTo(".g-chip", { y: 30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.4, ease: "power3.out" }, 21.95);
  tl.fromTo(".g-prog i", { scale: 0.5, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.3, stagger: 0.06, ease: "back.out(2)" }, 22.1);
  const cardIn = (id, t) => tl.fromTo(id, { opacity: 0, scale: 0.86, rotationY: -28, y: 40 }, { opacity: 1, scale: 1, rotationY: 0, y: 0, duration: 0.45, ease: "back.out(1.6)" }, t);
  const cardOut = (id, t) => tl.to(id, { opacity: 0, scale: 0.9, rotationY: 24, y: -30, duration: 0.3, ease: "power2.in" }, t);
  all(".g-card").forEach((c) => tl.set(c, { opacity: 0 }, 0));
  cardIn("#g1", 21.95); cardOut("#g1", 24.2);
  cardIn("#g2", 24.25); cardOut("#g2", 25.45);
  cardIn("#g3", 25.5); cardOut("#g3", 26.7);
  cardIn("#g4", 26.75); cardOut("#g4", 28.6);
  tl.to("#g-wrap", { autoAlpha: 0, duration: 0.4 }, 28.7);
  // wrong attempt → friendly hint → think again → correct
  const tap = (id, t) => { tl.to(id, { scale: 0.88, duration: 0.08, ease: "power2.out" }, t - 0.06); tl.to(id, { scale: 1, duration: 0.3, ease: "back.out(3)" }, t + 0.02); };
  tap("#g1a", 22.6);
  tl.to("#g1a", { x: -14, duration: 0.06, yoyo: true, repeat: 5, ease: "sine.inOut" }, 22.62);
  tl.to("#g1a", { backgroundColor: "rgba(255,170,90,0.35)", borderColor: "rgba(255,190,110,0.9)", duration: 0.2 }, 22.6);
  tl.to("#g1a", { backgroundColor: "rgba(255,255,255,0.10)", borderColor: "rgba(255,255,255,0.28)", duration: 0.3 }, 23.15);
  tl.fromTo("#g1hint", { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.3, ease: "back.out(2)" }, 22.68);
  tl.to("#g1hint", { opacity: 0, duration: 0.25 }, 23.45);
  tap("#g1b", 23.6);
  tl.to("#g1b", { backgroundColor: "#22c58b", borderColor: "#7dffcf", duration: 0.2 }, 23.6);
  tl.set("#q8", { opacity: 0 }, 0);
  tl.to("#qm", { opacity: 0, duration: 0.12 }, 23.6);
  tl.fromTo("#q8", { opacity: 0, scale: 0.5 }, { opacity: 1, scale: 1, duration: 0.3, ease: "back.out(2.5)", immediateRender: false }, 23.62);
  tl.fromTo("#g1ok", { opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1, duration: 0.35, ease: "back.out(2.5)" }, 23.68);
  tap("#g2a", 24.8);
  tl.to("#g2a", { backgroundColor: "#22c58b", borderColor: "#7dffcf", duration: 0.2 }, 24.8);
  tl.to("#g2b", { opacity: 0.4, duration: 0.3 }, 24.85);
  tl.fromTo("#g2ok", { opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1, duration: 0.35, ease: "back.out(2.5)" }, 24.88);
  // colours mix
  tl.fromTo("#g3b", { x: 0 }, { x: L.mixdx, duration: 0.5, ease: "power2.inOut" }, 25.75);
  tl.fromTo("#g3y", { x: 0 }, { x: L.mixdy, duration: 0.5, ease: "power2.inOut" }, 25.75);
  tl.to(["#g3b", "#g3y", "#g3plus"], { opacity: 0, duration: 0.2 }, 26.15);
  tl.fromTo("#g3g", { scale: 0.2, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.45, ease: "back.out(2.2)" }, 26.0);
  tl.fromTo("#g3lbl", { opacity: 0 }, { opacity: 1, duration: 0.3 }, 26.1);
  // puzzle piece snaps in
  tl.fromTo("#g4p", { x: L.puzdx, y: L.puzdy, rotation: 20 }, { x: 0, y: 0, rotation: 0, duration: 0.55, ease: "back.out(1.4)" }, 26.5);
  tl.fromTo("#g4s", { opacity: 0, scale: 0.5 }, { opacity: 1, scale: 1, duration: 0.4, ease: "back.out(2)" }, 27.02);
  tl.fromTo("#g4ok", { opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1, duration: 0.35, ease: "back.out(2.5)" }, 27.08);
  [["#pr1", 23.8], ["#pr2", 25.0], ["#pr3", 26.2], ["#pr4", 27.2]].forEach(([id, t]) => {
    tl.to(id, { backgroundColor: "#ffd25a", boxShadow: "0 0 40px rgba(255,200,80,0.9)", scale: 1.25, duration: 0.25, ease: "back.out(3)" }, t);
    tl.to(id, { scale: 1, duration: 0.3 }, t + 0.25);
  });

  /* ---------------- scene 5: live competition */
  tl.set("#q-wrap", { autoAlpha: 0 }, 0);
  tl.to("#q-wrap", { autoAlpha: 1, duration: 0.3 }, 30.55);
  tl.fromTo(".q-top > *", { y: -30, opacity: 0 }, { y: 0, opacity: 1, duration: 0.35, stagger: 0.08, ease: "back.out(2)" }, 30.6);
  tl.fromTo("#qcard", { opacity: 0, y: -60, scale: 0.9 }, { opacity: 1, y: 0, scale: 1, duration: 0.5, ease: "back.out(1.6)" }, 30.85);
  tl.fromTo(".q-opts b", { opacity: 0, y: 30 }, { opacity: 1, y: 0, duration: 0.35, stagger: 0.1, ease: "back.out(2)" }, 31.25);
  tl.fromTo("#cd", { opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1, duration: 0.4, ease: "back.out(2)" }, 30.7);
  tl.fromTo("#sb", { opacity: 0, x: L.sbdx }, { opacity: 1, x: 0, duration: 0.5, ease: "power3.out" }, 31.0);
  tl.fromTo(".sb-row", { opacity: 0, y: 20 }, { opacity: 1, y: 0, duration: 0.3, stagger: 0.08 }, 31.2);
  const circ = 2 * Math.PI * 86;
  tl.fromTo("#cdring", { strokeDashoffset: 0 }, { strokeDashoffset: circ, duration: 4.4, ease: "none" }, 30.6);
  const cdIds = ["#cd5", "#cd4", "#cd3", "#cd2", "#cd1", "#cdok"];
  cdIds.forEach((id, i) => tl.set(id, { opacity: i === 0 ? 1 : 0 }, 0));
  cdIds.forEach((id, i) => {
    if (i === 0) return;
    const t = 30 + i;
    tl.set(cdIds[i - 1], { opacity: 0 }, t);
    tl.fromTo(id, { opacity: 1, scale: 1.45 }, { opacity: 1, scale: 1, duration: 0.35, ease: "back.out(2)", immediateRender: false }, t);
  });
  tl.to("#cd", { boxShadow: "0 0 90px rgba(255,200,80,0.95)", duration: 0.2 }, 35.0);
  tap("#qb", 34.4);
  tl.to("#qb", { backgroundColor: "rgba(255,210,90,0.35)", borderColor: "#ffd25a", duration: 0.15 }, 34.4);
  tl.to("#qb", { backgroundColor: "#22c58b", borderColor: "#7dffcf", duration: 0.2 }, 35.0);
  tl.to(["#qa", "#qc"], { opacity: 0.35, duration: 0.3 }, 35.05);
  tl.to("#qcard", { boxShadow: "0 0 120px rgba(80,255,190,0.55)", duration: 0.3 }, 35.0);
  // our team takes the lead
  const sc = { v: 920 };
  tl.to(sc, { v: 1020, duration: 0.8, ease: "power2.out", onUpdate: () => { $("#meScore").textContent = fa(Math.round(sc.v)); } }, 35.05);
  tl.to("#r2", { y: L.rowup, duration: 0.5, ease: "back.out(1.4)" }, 35.25);
  tl.to("#r1", { y: L.rowdn, duration: 0.5, ease: "back.out(1.4)" }, 35.25);
  tl.to("#r2", { boxShadow: "0 0 70px rgba(255,200,80,0.9)", duration: 0.3 }, 35.05);
  tl.to("#q-wrap", { autoAlpha: 0, duration: 0.35 }, 36.6);

  /* ---------------- scene 6: progress */
  tl.set("#d-wrap", { autoAlpha: 0 }, 0);
  tl.to("#d-wrap", { autoAlpha: 1, duration: 0.3 }, 38.55);
  tl.fromTo("#dp1", { opacity: 0, x: L.dpdx1 }, { opacity: 1, x: 0, duration: 0.55, ease: "power3.out" }, 38.6);
  tl.fromTo("#dp2", { opacity: 0, x: L.dpdx2 }, { opacity: 1, x: 0, duration: 0.55, ease: "power3.out" }, 38.75);
  [["#b1", 85], ["#b2", 72], ["#b3", 90]].forEach(([id, v], i) => {
    const t = 38.9 + i * 0.3;
    tl.fromTo(id, { scaleX: 0 }, { scaleX: v / 100, duration: 1.0, ease: "power2.out" }, t);
    const o = { v: 0 };
    tl.to(o, { v, duration: 1.0, ease: "power2.out", onUpdate: () => { $(id + "n").textContent = fa(Math.round(o.v)) + "٪"; } }, t);
  });
  const st = { v: 1180 };
  tl.to(st, { v: 1240, duration: 1.2, ease: "power2.out", onUpdate: () => { $("#dstars").textContent = fa(Math.round(st.v)); } }, 41.5);
  [["#ds1", 39.6], ["#ds2", 40.2], ["#ds3", 40.8], ["#ds4", 41.5]].forEach(([id, t], i) => {
    tl.to(id + " i", { backgroundColor: "#ffd25a", boxShadow: "0 0 40px rgba(255,200,80,0.95)", scale: 1.2, duration: 0.25, ease: "back.out(3)" }, t);
    tl.to(id + " span", { color: "#ffe9a8", duration: 0.25 }, t);
    tl.to("#dline", { scaleY: (i + 1) / 4, duration: 0.5, ease: "power2.out" }, t - 0.4);
  });
  tl.to("#d-wrap", { autoAlpha: 0, duration: 0.45 }, 44.1);

  /* ---------------- scenes 7–8: logo, brand */
  tl.set("#f-wrap", { autoAlpha: 0 }, 0);
  tl.set("#f-wrap", { autoAlpha: 1 }, 47.98);
  tl.fromTo("#logo-frame", { scale: 0.55, opacity: 0, filter: "blur(30px)" }, { scale: 1, opacity: 1, filter: "blur(0px)", duration: 0.9, ease: "expo.out" }, 48.0);
  tl.fromTo("#logo-halo", { opacity: 0, scale: 0.6 }, { opacity: 1, scale: 1, duration: 1.4, ease: "power2.out" }, 48.0);
  tl.fromTo("#logo", { boxShadow: "0 0 0 4px rgba(255,255,255,0.35), 0 0 60px rgba(60,140,255,0.5)" }, { boxShadow: "0 0 0 6px rgba(255,226,150,0.75), 0 0 160px rgba(90,170,255,0.85)", duration: 1.6, yoyo: true, repeat: 3, ease: "sine.inOut" }, 48.2);
  tl.fromTo("#logo-frame", { y: 0 }, { y: L.logoup, duration: 1.2, ease: "power3.inOut" }, 50.0);
  tl.fromTo("#logo-frame", { scale: 1 }, { scale: L.logoscale, duration: 1.2, ease: "power3.inOut", immediateRender: false }, 50.0);
  tl.fromTo("#f-title", { opacity: 0, y: 60, filter: "blur(16px)" }, { opacity: 1, y: 0, filter: "blur(0px)", duration: 0.7, ease: "power3.out" }, 50.75);
  tl.fromTo("#f-tag", { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.6, ease: "power3.out" }, 51.15);
  // a shooting star reveals the website address
  tl.fromTo("#shoot", { x: L.shootfrom, opacity: 0 }, { x: L.shootto, opacity: 1, duration: 1.2, ease: "power1.inOut" }, 52.2);
  tl.to("#shoot", { opacity: 0, duration: 0.25 }, 53.3);
  tl.fromTo("#f-url", { clipPath: "inset(0% 0% 0% 100%)" }, { clipPath: "inset(0% 0% 0% 0%)", duration: 1.05, ease: "power1.inOut" }, 52.3);
  tl.fromTo("#f-url", { textShadow: "0 0 0px rgba(255,210,110,0)" }, { textShadow: "0 0 50px rgba(255,210,110,0.9)", duration: 0.5, yoyo: true, repeat: 1 }, 53.3);

  /* ---------------- subtitles (vertical cut) */
  if (subtitles) {
    const box = $("#subs");
    (subTimes || SUBS).forEach(([a, b, text], i) => {
      const d = document.createElement("div");
      d.className = "sub";
      d.id = "sub" + i;
      d.textContent = text;
      box.appendChild(d);
      tl.set(d, { opacity: 0 }, 0);
      tl.fromTo(d, { opacity: 0, y: 24 }, { opacity: 1, y: 0, duration: 0.3, ease: "power2.out" }, a);
      tl.to(d, { opacity: 0, duration: 0.25 }, b);
    });
  }
  return el;
}
