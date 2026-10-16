// STARMAH — "Where Learning Becomes Magic" · 55 s Three.js film, driven purely by time.
import * as THREE from "three";
import { RoomEnvironment } from "three/addons/environments/RoomEnvironment.js";
import { EffectComposer } from "three/addons/postprocessing/EffectComposer.js";
import { RenderPass } from "three/addons/postprocessing/RenderPass.js";
import { UnrealBloomPass } from "three/addons/postprocessing/UnrealBloomPass.js";
import { OutputPass } from "three/addons/postprocessing/OutputPass.js";
import { ShaderPass } from "three/addons/postprocessing/ShaderPass.js";
import * as K from "./film-kit.js";

const { V, clamp, lerp, win, smooth, smoother, easeInOut, easeOut, easeIn, easeOutBack, pulse, prand, trackV, M } = K;

export const CUE = { S2: 6, S3: 13, S4: 21, S5: 29, S6: 37, S7: 44, LOGO: 48, S8: 51, END: 55 };

// world anchors
const BOOK = V(0, 0, 0);
const ISL = { math: V(-7.2, 4.6, -18), sci: V(7.6, 6.6, -33), fa: V(-7.6, 8.6, -47), art: V(7.6, 10.6, -61) };
const ARENA = V(0, 9.6, -118);
const DASH = V(0, 12.2, -160);
const FINAL = V(0, 40, -262);
const GPATH = [
  V(-3.4, 3.4, 2.6), V(-1.2, 4.6, -4), V(-2.2, 5.6, -12), V(-2.0, 6.2, -20), V(1.6, 7.2, -27), V(2.4, 8.2, -34),
  V(-1.4, 9.4, -41), V(-2.4, 10.2, -48), V(1.6, 11.4, -55), V(2.4, 12.2, -62), V(0.4, 13.2, -67.5),
];

export async function createFilm({ canvas, width, height, layout = "land", samples = 4 }) {
  const SAMPLES = samples;
  const VERT = layout === "vert";
  const LOOP = layout === "loop";
  await Promise.all([
    document.fonts.load("800 100px Vazirmatn"), document.fonts.load("900 100px Vazirmatn"),
    document.fonts.load("700 100px Vazirmatn"), document.fonts.load("800 100px Poppins"),
  ]);

  /* ------------------------------------------------------------ renderer */
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: false, powerPreference: "high-performance" });
  renderer.setPixelRatio(1);
  renderer.setSize(width, height, false);
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.0;
  const pmrem = new THREE.PMREMGenerator(renderer);
  const env = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  K.initMaterials(env);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(VERT ? 52 : 36, width / height, 0.05, 2000);

  /* ------------------------------------------------------------- sky */
  const skyMat = new THREE.MeshBasicMaterial({ map: K.nebulaTex(), side: THREE.BackSide, depthWrite: false, toneMapped: true });
  const sky = new THREE.Mesh(new THREE.SphereGeometry(900, 64, 32), skyMat);
  sky.renderOrder = -10;
  scene.add(sky);
  // far spiral galaxy (the "educational fantasy galaxy")
  const galaxy = (() => {
    const N = 9000, geo = new THREE.BufferGeometry(), p = new Float32Array(N * 3), c = new Float32Array(N * 3), r = new Float32Array(N);
    const cols = [[1, 0.82, 0.45], [0.45, 0.75, 1], [0.7, 0.55, 1], [1, 0.6, 0.8]];
    for (let i = 0; i < N; i++) {
      const arm = i % 3, rad = Math.pow(prand(i * 1.3), 0.7) * 120, a = rad * 0.045 + (arm * Math.PI * 2) / 3 + (prand(i * 2.1) - 0.5) * 0.5;
      p[i * 3] = Math.cos(a) * rad + (prand(i * 4.4) - 0.5) * 8;
      p[i * 3 + 1] = (prand(i * 5.5) - 0.5) * 4 * (1 - rad / 130);
      p[i * 3 + 2] = Math.sin(a) * rad + (prand(i * 6.6) - 0.5) * 8;
      const cc = rad < 18 ? cols[0] : cols[(i * 7) % 4];
      c.set(cc, i * 3);
      r[i] = prand(i * 8.8);
    }
    geo.setAttribute("position", new THREE.BufferAttribute(p, 3));
    geo.setAttribute("color", new THREE.BufferAttribute(c, 3));
    geo.setAttribute("rnd", new THREE.BufferAttribute(r, 1));
    const mat = new THREE.ShaderMaterial({
      transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, vertexColors: true,
      uniforms: { uTime: { value: 0 }, uAlpha: { value: 0 } },
      vertexShader: `attribute float rnd; uniform float uTime; varying vec3 vC; varying float vR;
        void main(){ vC = color; vR = rnd; float a = uTime*0.02*(1.0 - length(position.xz)/160.0);
          vec3 q = vec3(position.x*cos(a)-position.z*sin(a), position.y, position.x*sin(a)+position.z*cos(a));
          vec4 mv = modelViewMatrix*vec4(q,1.0); gl_Position = projectionMatrix*mv; gl_PointSize = (1.5 + rnd*3.0) * (300.0/max(1.0,-mv.z)); }`,
      fragmentShader: `uniform float uAlpha; varying vec3 vC; varying float vR;
        void main(){ float d = length(gl_PointCoord-0.5); float a = smoothstep(0.5,0.0,d); gl_FragColor = vec4(vC*1.4, a*a*uAlpha*(0.4+vR*0.6)); }`,
    });
    const pts = new THREE.Points(geo, mat);
    pts.frustumCulled = false;
    pts.rotation.set(0.55, 0, 0.3);
    pts.position.set(40, 90, -520);
    return pts;
  })();
  scene.add(galaxy);
  // distant ringed planets
  const planets = new THREE.Group();
  [[V(-95, 48, -170), 9, 0x7c6cf0, true], [V(80, 10, -200), 14, 0x3a8bff, true], [V(-40, -10, -260), 6, 0xff8fb1, false], [V(30, 55, -320), 11, 0x2dd4bf, true]].forEach(([p, r, col, ring], i) => {
    const pl = new THREE.Mesh(new THREE.SphereGeometry(r, 48, 32), new THREE.MeshStandardMaterial({ color: col, roughness: 0.7, emissive: col, emissiveIntensity: 0.12 }));
    pl.position.copy(p);
    planets.add(pl);
    if (ring) {
      const rg = new THREE.Mesh(new THREE.RingGeometry(r * 1.4, r * 2.1, 96), new THREE.MeshBasicMaterial({ color: 0xc9b98f, transparent: true, opacity: 0.22, side: THREE.DoubleSide, depthWrite: false }));
      rg.position.copy(p); rg.rotation.set(1.2 + i * 0.2, 0.3, 0.4);
      planets.add(rg);
    }
  });
  scene.add(planets);

  /* ---------------------------------------------------------- lights */
  const hemi = new THREE.HemisphereLight(0x9fb8ff, 0x251a4a, 0.7);
  const key = new THREE.DirectionalLight(0xffe2b0, 2.0); key.position.set(4, 6, 6);
  const rim = new THREE.DirectionalLight(0x6fb4ff, 1.6); rim.position.set(-5, 3, -4);
  scene.add(hemi, key, key.target, rim, rim.target);
  const goldLight = new THREE.PointLight(0xffc35a, 0, 12, 2);
  scene.add(goldLight);
  const spotLight = new THREE.PointLight(0x7fd8ff, 0, 30, 1.4);
  scene.add(spotLight);
  spotLight.visible = false; // portal glow is emissive; a lit point light is too costly under software GL

  /* ---------------------------------------------------------- dust */
  const dustGold = K.makeDust({ count: 1600, box: [34, 20, 34], size: 3.2, color: [1, 0.78, 0.38], seed: 3 });
  const dustBlue = K.makeDust({ count: 700, box: [40, 24, 40], size: 2.8, color: [0.45, 0.75, 1], seed: 11 });
  scene.add(dustGold, dustBlue);

  /* ---------------------------------------------- SCENE 1–2: book world */
  const bookWorld = new THREE.Group();
  scene.add(bookWorld);
  const platform = new THREE.Mesh(new THREE.LatheGeometry([[0, 0], [2.55, 0], [2.72, -0.06], [2.78, -0.2], [2.66, -0.36], [1.6, -0.6], [0.5, -0.9], [0, -0.95]].map(([x, y]) => new THREE.Vector2(x, y)), 96), M.navyGloss);
  const platRim = new THREE.Mesh(new THREE.TorusGeometry(2.74, 0.028, 10, 160), M.goldGlow);
  platRim.rotation.x = Math.PI / 2; platRim.position.y = -0.1;
  const runeTex = K.canvasTex(2048, 2048, (g, w) => {
    g.translate(w / 2, w / 2);
    g.strokeStyle = "rgba(255,206,110,0.9)";
    [0.96, 0.9, 0.62, 0.56].forEach((k, i) => { g.lineWidth = i % 2 ? 3 : 7; g.beginPath(); g.arc(0, 0, (w / 2) * k, 0, Math.PI * 2); g.stroke(); });
    g.font = "800 72px Vazirmatn"; g.fillStyle = "rgba(255,214,130,0.95)"; g.textAlign = "center"; g.textBaseline = "middle";
    const sy = ["۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹", "۰", "π", "+", "×", "÷", "√", "="];
    sy.forEach((s, i) => { const a = (i / sy.length) * Math.PI * 2; g.save(); g.rotate(a); g.fillText(s, 0, -(w / 2) * 0.76); g.restore(); });
    for (let i = 0; i < 5; i++) { const a = (i / 5) * Math.PI * 2 - Math.PI / 2, b = ((i + 2) / 5) * Math.PI * 2 - Math.PI / 2; g.lineWidth = 4; g.beginPath(); g.moveTo(Math.cos(a) * w * 0.28, Math.sin(a) * w * 0.28); g.lineTo(Math.cos(b) * w * 0.28, Math.sin(b) * w * 0.28); g.stroke(); }
  });
  const runes = new THREE.Mesh(new THREE.CircleGeometry(2.62, 96), new THREE.MeshBasicMaterial({ map: runeTex, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, opacity: 0, color: new THREE.Color(1.6, 1.4, 1.0), toneMapped: false }));
  runes.rotation.x = -Math.PI / 2; runes.position.y = 0.006;
  const crystal = new THREE.Mesh(new THREE.OctahedronGeometry(0.4, 0), M.cyanGlow);
  crystal.position.y = -1.45; crystal.scale.y = 1.7;
  bookWorld.add(platform, platRim, runes, crystal);
  // the book
  const book = new THREE.Group();
  book.position.y = 0.0;
  const coverGeo = new K.RoundedBox(1.55, 0.07, 2.08, 3, 0.03);
  const blockGeo = new THREE.BoxGeometry(1.44, 0.18, 1.96);
  const halves = [-1, 1].map((sd) => {
    const h = new THREE.Group();
    const cov = new THREE.Mesh(coverGeo, M.cover); cov.position.set(sd * 0.78, 0.035, 0);
    const blk = new THREE.Mesh(blockGeo, M.paper); blk.position.set(sd * 0.75, 0.16, 0);
    const pg = K.makePage(1.42, 1.94, K.pageTexture(sd > 0 ? 0 : 1));
    pg.userData.flip(sd > 0 ? Math.PI : 0, 0);
    pg.position.y = 0.252;
    h.add(cov, blk, pg);
    // gold corner trims
    [-1, 1].forEach((zz) => { const c = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.08, 0.18), M.gold); c.position.set(sd * 1.47, 0.04, zz * 0.95); h.add(c); });
    h.rotation.z = sd * -0.06;
    book.add(h);
    return h;
  });
  const spine = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.11, 2.08, 24, 1, false, 0, Math.PI), M.cover);
  spine.rotation.set(Math.PI / 2, 0, Math.PI / 2); spine.position.y = 0.02;
  book.add(spine);
  const flippers = [0, 1, 2].map((i) => {
    const pg = K.makePage(1.4, 1.92, K.pageTexture(i));
    pg.position.y = 0.256 + i * 0.002;
    book.add(pg);
    return pg;
  });
  const bookGlow = K.softGlowSprite("rgba(255,205,110,0.85)", 3.4);
  bookGlow.position.y = 0.45;
  book.add(bookGlow);
  bookWorld.add(book);
  // the first golden light + spark + constellation
  const firstLight = K.softGlowSprite("rgba(255,214,120,1)", 0.5);
  const spark = K.softGlowSprite("rgba(255,226,150,1)", 0.55);
  bookWorld.add(firstLight, spark);
  const CONST_C = V(0, 2.6, 0);
  const constNodes = [];
  const starPts = [];
  for (let i = 0; i < 10; i++) { const a = Math.PI / 2 + (i * Math.PI) / 5, rr = i % 2 === 0 ? 1.7 : 0.75; starPts.push(V(Math.cos(a) * rr, Math.sin(a) * rr, (prand(i) - 0.5) * 0.6)); }
  starPts.push(V(2.4, 1.3, -0.4), V(-2.5, 0.9, -0.3), V(0.3, -1.9, 0.2));
  starPts.forEach((p, i) => { const s = K.softGlowSprite("rgba(255,230,160,1)", i < 10 ? 0.42 : 0.3); s.userData.to = p; constNodes.push(s); bookWorld.add(s); });
  const lineGeo = new THREE.BufferGeometry();
  const segs = [];
  for (let i = 0; i < 10; i++) segs.push(starPts[i], starPts[(i + 1) % 10]);
  segs.push(starPts[0], starPts[10], starPts[4], starPts[11], starPts[5], starPts[12]);
  lineGeo.setFromPoints(segs.map((p) => p.clone().add(CONST_C)));
  const constLines = new THREE.LineSegments(lineGeo, new THREE.LineBasicMaterial({ color: new THREE.Color(1.8, 1.4, 0.7), transparent: true, opacity: 0, blending: THREE.AdditiveBlending, toneMapped: false }));
  bookWorld.add(constLines);
  const burst = makeBurst(420, CONST_C, [1, 0.8, 0.4]);
  bookWorld.add(burst.points);

  /* ------------------------------------------------------- characters */
  const moon = K.makeMoon();
  const star = K.makeStar();
  scene.add(moon.root, star.root);
  const boy = K.makeChild({ hoodie: 0xf4b52c });
  const girl = K.makeChild({ hoodie: 0x2dd4bf, hair: "brown", girl: true });
  scene.add(boy.root, girl.root);
  const badge = K.makeBadge();
  scene.add(badge);
  // orbital ring around the duo (scene 2)
  const duoRing = new THREE.Group();
  const ringMesh = new THREE.Mesh(new THREE.TorusGeometry(2.0, 0.022, 10, 200), K.glowMat(1.0, 0.75, 0.35, 1.5));
  duoRing.add(ringMesh);
  const ringSyms = ["π", "+", "√", "%", "۱", "۲", "۳", "E=mc²", "÷", "×"].map((s, i) => {
    const sp = K.glyphSprite(s, s.length > 2 ? 0.32 : 0.42, { font: `800 150px ${/[a-zA-Zπ√%÷×+=²]/.test(s) ? "Poppins" : "Vazirmatn"}`, color: i % 2 ? "#bfe9ff" : "#ffe3a0", glow: i % 2 ? "rgba(80,190,255,0.9)" : "rgba(255,190,70,0.9)" });
    duoRing.add(sp);
    return sp;
  });
  scene.add(duoRing);

  /* ------------------------------------------------------ SCENE 3 islands */
  const islands = new THREE.Group();
  scene.add(islands);
  const iMath = K.makeIsland(3.2, 0x3a5fd6, 1.3, { rim: [0.3, 0.8, 1] });
  const iSci = K.makeIsland(3.2, 0x2fae9a, 2.7, { rim: [0.3, 1, 0.75] });
  const iFa = K.makeIsland(3.2, 0x8b5cf6, 4.1, { rim: [1, 0.65, 0.95] });
  const iArt = K.makeIsland(3.2, 0xff8a4c, 5.9, { rim: [1, 0.8, 0.35] });
  iMath.position.copy(ISL.math); iSci.position.copy(ISL.sci); iFa.position.copy(ISL.fa); iArt.position.copy(ISL.art);
  islands.add(iMath, iSci, iFa, iArt);
  // floating rocks along the route
  const rockGeo = new THREE.DodecahedronGeometry(1, 0);
  const rocks = new THREE.InstancedMesh(rockGeo, M.rock2, 46);
  const rockData = [];
  for (let i = 0; i < 46; i++) {
    const zz = 2 - prand(i * 3.3) * 72, side = prand(i * 1.9) > 0.5 ? 1 : -1;
    rockData.push({ p: V(side * (12 + prand(i * 7.1) * 14), 1 + prand(i * 2.3) * 18 + zz * -0.12, zz), s: 0.2 + prand(i * 4.7) * 0.9, r: prand(i * 9.1) * 6 });
  }
  islands.add(rocks);
  // math: portals, flying digits, solids
  const mathG = new THREE.Group(); mathG.position.copy(ISL.math).add(V(0, 0.18, 0)); islands.add(mathG);
  const portalA = new THREE.Mesh(new THREE.TorusGeometry(0.95, 0.07, 12, 64), M.cyanGlow); portalA.position.set(-1.1, 1.25, 0.2); portalA.rotation.y = 0.5;
  const portalB = new THREE.Mesh(new THREE.TorusGeometry(0.8, 0.07, 12, 64), M.violetGlow); portalB.position.set(1.2, 1.1, -0.6); portalB.rotation.y = -0.6;
  mathG.add(portalA, portalB);
  const solidMats = [0x3ad0ff, 0xffc94a, 0xff6b9d, 0x8b7cf6].map((c) => K.std({ color: c, roughness: 0.15, clearcoat: 1, transparent: true, opacity: 0.85, envMap: env, envMapIntensity: 1.2, emissive: c, emissiveIntensity: 0.15 }));
  const solids = [new THREE.IcosahedronGeometry(0.42, 0), new THREE.BoxGeometry(0.6, 0.6, 0.6), new THREE.ConeGeometry(0.45, 0.7, 4), new THREE.TorusKnotGeometry(0.28, 0.09, 96, 12)].map((g, i) => {
    const m = new THREE.Mesh(g, solidMats[i]); m.position.set(Math.cos(i * 1.6) * 2.1, 1.0 + (i % 2) * 0.6, Math.sin(i * 1.6) * 1.8); mathG.add(m); return m;
  });
  const DIG = ["۱", "۲", "۳", "۵", "۸", "۱۳", "۲۱", "π", "+", "×", "÷", "=", "√", "۴", "۶", "۹"];
  const digits = DIG.map((d, i) => { const sp = K.glyphSprite(d, 0.5, { font: `800 150px ${/[π+×÷=√]/.test(d) ? "Poppins" : "Vazirmatn"}`, color: i % 3 ? "#ffe3a0" : "#bdf0ff", glow: i % 3 ? "rgba(255,190,70,0.9)" : "rgba(60,200,255,0.9)" }); mathG.add(sp); return sp; });
  // science: flasks, molecule, bubbles
  const sciG = new THREE.Group(); sciG.position.copy(ISL.sci).add(V(0, 0.18, 0)); islands.add(sciG);
  const flasks = [[V(-1.2, 0, 0.6), [1, 0.35, 0.65], "erlen"], [V(0.1, 0, -0.5), [0.25, 0.85, 1], "tube"], [V(1.3, 0, 0.5), [0.45, 1, 0.55], "erlen"]].map(([p, c, s]) => { const f = K.makeFlask(c, s); f.position.copy(p); f.scale.setScalar(1.25); sciG.add(f); return f; });
  const molecule = K.makeMolecule(); molecule.position.set(0, 2.4, 0); molecule.scale.setScalar(1.2); sciG.add(molecule);
  const bubbleGeo = new THREE.SphereGeometry(0.06, 12, 8);
  const bubbles = new THREE.InstancedMesh(bubbleGeo, new THREE.MeshBasicMaterial({ color: new THREE.Color(1.4, 1.6, 2.0), transparent: true, opacity: 0.8, toneMapped: false }), 36);
  sciG.add(bubbles);
  const droplet = K.softGlowSprite("rgba(160,255,200,1)", 0.35); sciG.add(droplet);
  const sciBurst = makeBurst(160, V(0, 2.4, 0), [0.6, 1, 0.9]); sciG.add(sciBurst.points);
  // Persian letters
  const faG = new THREE.Group(); faG.position.copy(ISL.fa).add(V(0, 0.18, 0)); islands.add(faG);
  const faBook = K.makeMiniBook(0xb07cff); faBook.scale.setScalar(2.4); faBook.position.set(0, 0.1, 0.2); faG.add(faBook);
  const LET = [["د", 0.95], ["ا", 0.42], ["ن", -0.1], ["ش", -0.75]];
  const letters = LET.map(([ch, x], i) => { const sp = K.glyphSprite(ch, 0.85, { font: "900 200px Vazirmatn", color: "#fff1c2", glow: "rgba(255,170,230,0.95)" }); sp.userData.x = x; faG.add(sp); return sp; });
  const extraLet = ["ب", "پ", "ت", "س", "ر", "م", "ه", "ک"].map((ch, i) => { const sp = K.glyphSprite(ch, 0.45, { font: "800 160px Vazirmatn", color: i % 2 ? "#ffd7f4" : "#e3dcff", glow: "rgba(200,140,255,0.9)" }); faG.add(sp); return sp; });
  const word = K.glyphSprite("دانش", 1.05, { font: "900 220px Vazirmatn", color: "#fff1c2", glow: "rgba(255,170,230,0.95)" }); faG.add(word);
  // creative: pencil rockets, flapping books, idea bulb
  const artG = new THREE.Group(); artG.position.copy(ISL.art).add(V(0, 0.18, 0)); islands.add(artG);
  const pencils = [0xffc23d, 0x3a8bff, 0xff5f8f, 0x2dd4bf, 0x8b7cf6].map((c, i) => { const p = K.makePencil(c); p.scale.setScalar(1.3); artG.add(p); const fl = K.softGlowSprite("rgba(255,150,60,1)", 0.7); artG.add(fl); return { p, fl, i }; });
  const flyBooks = [0x3a8bff, 0xff8fb1, 0xffc23d].map((c) => { const b = K.makeMiniBook(c); b.scale.setScalar(0.8); artG.add(b); return b; });
  const bulb = new THREE.Group();
  const bulbGlass = new THREE.Mesh(new THREE.SphereGeometry(0.45, 32, 24), K.std({ color: 0xfff3c0, emissive: 0xffd36a, emissiveIntensity: 1.2, roughness: 0.1, transparent: true, opacity: 0.9, envMap: env }));
  const bulbBase = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.18, 0.3, 16), M.gold); bulbBase.position.y = -0.48;
  bulb.add(bulbGlass, bulbBase, K.softGlowSprite("rgba(255,220,120,0.9)", 2.2));
  bulb.position.set(0, 2.4, -0.6); artG.add(bulb);

  /* -------------------------------------------------------- SCENE 4 portal */
  const PORTAL = VERT ? V(0, 15.6, -77.5) : V(2.4, 13.9, -77);
  const KIDS = VERT ? V(0, 9.2, -70.4) : V(-3.4, 11.5, -71.4);
  const portalG = new THREE.Group(); portalG.position.copy(PORTAL); scene.add(portalG);
  const pRing = new THREE.Mesh(new THREE.TorusGeometry(3.15, 0.09, 16, 200), M.goldGlow);
  const pRing2 = new THREE.Mesh(new THREE.TorusGeometry(3.4, 0.025, 8, 200), M.cyanGlow);
  const pDiscMat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, uniforms: { uTime: { value: 0 }, uOpen: { value: 0 } },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix*modelViewMatrix*vec4(position,1.0); }`,
    fragmentShader: `uniform float uTime, uOpen; varying vec2 vUv;
      void main(){ vec2 c = vUv-0.5; float r = length(c)*2.0; float a = atan(c.y,c.x);
        float sw = 0.5+0.5*sin(a*3.0 + r*9.0 - uTime*1.6); float rings = 0.5+0.5*sin(r*26.0 - uTime*3.0);
        vec3 col = mix(vec3(0.05,0.12,0.38), vec3(0.18,0.55,1.0), sw*0.6) + vec3(0.5,0.3,0.9)*rings*0.12*(1.0-r);
        col += vec3(1.0,0.75,0.35)*smoothstep(0.75,1.0,r)*0.6;
        float alpha = smoothstep(1.0, 0.92, r) * uOpen * 0.88; gl_FragColor = vec4(col, alpha); }`,
  });
  const pDisc = new THREE.Mesh(new THREE.CircleGeometry(3.15, 96), pDiscMat);
  portalG.add(pDisc, pRing, pRing2);
  const portalSparks = makeOrbitSparks(90, 3.3, [1, 0.85, 0.5]);
  portalG.add(portalSparks.points);
  const kidsPad = new THREE.Mesh(new THREE.LatheGeometry([[0, 0], [1.9, 0], [2.0, -0.08], [1.7, -0.3], [0.6, -0.55], [0, -0.6]].map(([x, y]) => new THREE.Vector2(x, y)), 64), M.navyGloss);
  const kidsRim = new THREE.Mesh(new THREE.TorusGeometry(1.98, 0.025, 8, 120), M.cyanGlow); kidsRim.rotation.x = Math.PI / 2; kidsRim.position.y = -0.06;
  const kidsPadG = new THREE.Group(); kidsPadG.add(kidsPad, kidsRim); kidsPadG.position.copy(KIDS); scene.add(kidsPadG);

  /* --------------------------------------------------------- SCENE 5 arena */
  const arenaG = new THREE.Group(); arenaG.position.copy(ARENA); scene.add(arenaG);
  const tiers = [[8.6, 0], [8.6, 0.5], [10.2, 0.5], [10.2, 1.3], [11.8, 1.3], [11.8, 2.1], [13.4, 2.1], [13.4, 2.9], [15.0, 2.9], [15.0, 3.7], [16.4, 4.0], [16.6, 3.2], [16.0, -1.4], [12, -3.2], [8.6, -1.2], [8.6, 0]];
  const bowl = new THREE.Mesh(new THREE.LatheGeometry(tiers.map(([x, y]) => new THREE.Vector2(x, y)), 112), K.std({ color: 0x0c1636, roughness: 0.5, metalness: 0.3, clearcoat: 0.6, envMap: env, envMapIntensity: 0.18, side: THREE.DoubleSide }));
  arenaG.add(bowl);
  [[8.62, 0.5, K.glowMat(0.25, 0.85, 1, 1.1)], [10.22, 1.3, K.glowMat(0.6, 0.45, 1, 1.1)], [11.82, 2.1, K.glowMat(1, 0.72, 0.25, 1.1)], [13.42, 2.9, K.glowMat(0.25, 0.85, 1, 1.1)], [15.02, 3.7, K.glowMat(0.6, 0.45, 1, 1.1)], [16.42, 4.0, K.glowMat(1, 0.72, 0.25, 1.1)]].forEach(([r, y, m]) => { const t = new THREE.Mesh(new THREE.TorusGeometry(r, 0.035, 8, 220), m); t.rotation.x = Math.PI / 2; t.position.y = y; arenaG.add(t); });
  const floor = new THREE.Mesh(new THREE.CircleGeometry(8.6, 128), K.std({ color: 0x0d1a44, roughness: 0.2, metalness: 0.4, clearcoat: 1, envMap: env, envMapIntensity: 0.6 }));
  floor.rotation.x = -Math.PI / 2; floor.position.y = 0.02; arenaG.add(floor);
  const floorTex = K.canvasTex(2048, 2048, (g, w) => { g.translate(w / 2, w / 2); g.strokeStyle = "rgba(90,190,255,0.7)"; for (let i = 1; i < 9; i++) { g.lineWidth = i % 3 ? 3 : 8; g.beginPath(); g.arc(0, 0, (w / 2) * (i / 9), 0, Math.PI * 2); g.stroke(); } for (let i = 0; i < 24; i++) { const a = (i / 24) * Math.PI * 2; g.beginPath(); g.moveTo(Math.cos(a) * w * 0.12, Math.sin(a) * w * 0.12); g.lineTo(Math.cos(a) * w * 0.5, Math.sin(a) * w * 0.5); g.lineWidth = 2; g.stroke(); } });
  const floorLines = new THREE.Mesh(new THREE.CircleGeometry(8.5, 128), new THREE.MeshBasicMaterial({ map: floorTex, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, color: new THREE.Color(0.35, 0.5, 0.8), toneMapped: false }));
  floorLines.rotation.x = -Math.PI / 2; floorLines.position.y = 0.03; arenaG.add(floorLines);
  const stage = new THREE.Mesh(new THREE.CylinderGeometry(2.4, 2.7, 0.8, 64), M.navyGloss); stage.position.y = 0.4; arenaG.add(stage);
  const stageRim = new THREE.Mesh(new THREE.TorusGeometry(2.42, 0.05, 10, 120), M.goldGlow); stageRim.rotation.x = Math.PI / 2; stageRim.position.y = 0.8; arenaG.add(stageRim);
  // crowd of glowing little fans on the tiers
  const crowdN = 520;
  const crowd = new THREE.InstancedMesh(new THREE.CapsuleGeometry(0.17, 0.22, 4, 8), new THREE.MeshStandardMaterial({ roughness: 0.6, emissive: 0xffffff, emissiveIntensity: 0.04 }), crowdN);
  const crowdData = [];
  const crowdCols = [0xffc94a, 0x3ad0ff, 0xff6b9d, 0x8b7cf6, 0x2dd4bf, 0xff9a52, 0xffffff];
  for (let i = 0; i < crowdN; i++) {
    const tier = i % 5, rr = [9.4, 11.0, 12.6, 14.2, 15.7][tier], yy = [0.5, 1.3, 2.1, 2.9, 3.7][tier] + 0.3;
    const a = (i / crowdN) * Math.PI * 2 * 1.0 + tier * 0.21 + prand(i) * 0.02;
    crowdData.push({ a, rr, yy, ph: prand(i * 3.3) * 6.28 });
    crowd.setColorAt(i, new THREE.Color(crowdCols[i % crowdCols.length]));
  }
  arenaG.add(crowd);
  // light beams
  const beams = [];
  for (let i = 0; i < 0; i++) { // light beams removed: heavy transparent overdraw for a barely visible effect
    const b = new THREE.Mesh(new THREE.ConeGeometry(1.4, 16, 32, 1, true), new THREE.MeshBasicMaterial({ color: new THREE.Color(...(i % 2 ? [0.3, 0.7, 1] : [1, 0.75, 0.3])), transparent: true, opacity: 0.03, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide }));
    b.geometry.translate(0, -8, 0);
    const piv = new THREE.Group(); piv.position.set(Math.cos((i / 6) * Math.PI * 2) * 15, 9, Math.sin((i / 6) * Math.PI * 2) * 15); piv.add(b);
    arenaG.add(piv); beams.push(piv);
  }
  const confN = 700;
  const confetti = new THREE.InstancedMesh(new THREE.PlaneGeometry(0.12, 0.2), new THREE.MeshStandardMaterial({ side: THREE.DoubleSide, roughness: 0.4, metalness: 0.5, emissive: 0xffffff, emissiveIntensity: 0.25 }), confN);
  const confCols = [0xffc94a, 0xffe08a, 0xf5b53f, 0x3ad0ff, 0xff6b9d, 0xffffff];
  for (let i = 0; i < confN; i++) confetti.setColorAt(i, new THREE.Color(confCols[i % 6]));
  confetti.frustumCulled = false;
  arenaG.add(confetti);
  const arenaPortal = new THREE.Group();
  arenaPortal.position.set(0, 6.4, -0.5);
  const apRing = new THREE.Mesh(new THREE.TorusGeometry(2.2, 0.08, 12, 160), M.goldGlow);
  const apDisc = new THREE.Mesh(new THREE.CircleGeometry(2.2, 96), pDiscMat.clone());
  arenaPortal.add(apDisc, apRing);
  arenaG.add(arenaPortal);

  /* --------------------------------------------------------- tunnel */
  const tunnel = new THREE.Group(); scene.add(tunnel);
  for (let i = 0; i < 16; i++) {
    const m = [M.goldGlow, M.cyanGlow, M.violetGlow][i % 3];
    const t = new THREE.Mesh(new THREE.TorusGeometry(2.6 + (i % 2) * 0.3, 0.04, 8, 120), m);
    t.position.set(Math.sin(i * 0.7) * 0.6, 16.0 - i * 0.04, -121 - i * 1.9);
    t.rotation.z = i * 0.4;
    tunnel.add(t);
  }
  const streaks = (() => {
    const N = 500, pos = new Float32Array(N * 6);
    for (let i = 0; i < N; i++) { const a = prand(i * 1.7) * Math.PI * 2, r = 1.2 + prand(i * 2.9) * 2.4, z = -118 - prand(i * 3.1) * 34, len = 0.8 + prand(i * 4.2) * 2.5; pos.set([Math.cos(a) * r, 15.8 + Math.sin(a) * r, z, Math.cos(a) * r, 15.8 + Math.sin(a) * r, z - len], i * 6); }
    const g = new THREE.BufferGeometry(); g.setAttribute("position", new THREE.BufferAttribute(pos, 3));
    return new THREE.LineSegments(g, new THREE.LineBasicMaterial({ color: new THREE.Color(1.5, 1.3, 0.9), transparent: true, opacity: 0.7, blending: THREE.AdditiveBlending, toneMapped: false }));
  })();
  tunnel.add(streaks);

  /* ------------------------------------------------------ SCENE 6 dashboard */
  const dashG = new THREE.Group(); dashG.position.copy(DASH); scene.add(dashG);
  const dashPad = new THREE.Mesh(new THREE.LatheGeometry([[0, 0], [1.7, 0], [1.8, -0.08], [1.5, -0.3], [0.5, -0.5], [0, -0.55]].map(([x, y]) => new THREE.Vector2(x, y)), 64), M.navyGloss);
  const dashRim = new THREE.Mesh(new THREE.TorusGeometry(1.78, 0.03, 8, 120), M.goldGlow); dashRim.rotation.x = Math.PI / 2; dashRim.position.y = -0.06;
  dashG.add(dashPad, dashRim);
  const sheetTex = (title, lines) => K.canvasTex(900, 1200, (g, w, h) => {
    g.fillStyle = "#fbf7ec"; g.fillRect(0, 0, w, h);
    g.fillStyle = "#1e3a6e"; g.fillRect(0, 0, w, 170);
    g.fillStyle = "#ffd87a"; g.font = "900 78px Vazirmatn"; g.textAlign = "center"; g.direction = "rtl"; g.fillText(title, w / 2, 112);
    g.fillStyle = "#1b2742"; g.font = "800 66px Vazirmatn"; g.textAlign = "right";
    lines.forEach((l, i) => { g.fillText(l, w - 80, 320 + i * 190); g.fillStyle = "#2bb673"; g.font = "900 70px Poppins"; g.textAlign = "left"; g.fillText("✓", 70, 320 + i * 190); g.fillStyle = "#1b2742"; g.font = "800 66px Vazirmatn"; g.textAlign = "right"; });
    g.strokeStyle = "rgba(30,58,110,0.15)"; g.lineWidth = 3; for (let y = 360; y < h; y += 190) { g.beginPath(); g.moveTo(60, y); g.lineTo(w - 60, y); g.stroke(); }
  });
  const sheets = [["کاربرگ ریاضی", ["۲۴ + ۱۸ = ۴۲", "۷ × ۶ = ۴۲", "۸۱ ÷ ۹ = ۹", "۱۵ − ۸ = ۷"]], ["کاربرگ فارسی", ["خورشید", "دانش", "مدرسه", "ستاره"]], ["کاربرگ علوم", ["آب ← بخار", "ریشه ← گیاه", "ماه ← زمین", "نور ← رنگ"]]].map(([t, l], i) => {
    const m = new THREE.Mesh(new THREE.PlaneGeometry(1.35, 1.8), new THREE.MeshStandardMaterial({ map: sheetTex(t, l), roughness: 0.8, side: THREE.DoubleSide, emissive: 0xffffff, emissiveIntensity: 0.08 }));
    dashG.add(m); return m;
  });
  const miniStars = [];
  for (let i = 0; i < 8; i++) { const s = new THREE.Mesh(new THREE.ExtrudeGeometry(K.starShape(0.18, 0.09, 0.25), { depth: 0.05, bevelEnabled: true, bevelThickness: 0.04, bevelSize: 0.03, bevelSegments: 3 }), M.star); dashG.add(s); miniStars.push(s); }
  const sideBadges = [0, 1, 2].map(() => { const b = K.makeBadge(); b.scale.setScalar(0.55); dashG.add(b); return b; });
  const awardBurst = makeBurst(220, V(0, 0, 0), [1, 0.85, 0.45]);
  scene.add(awardBurst.points);

  /* ------------------------------------------------------ SCENE 7 orbit */
  const orbitG = new THREE.Group(); scene.add(orbitG);
  const ORB_C = V(0, 14.4, -161.5);
  const orbitItems = [];
  const itemMakers = [
    () => { const b = K.makeMiniBook([0x3a8bff, 0xff8fb1, 0xffc23d][orbitItems.length % 3]); b.scale.setScalar(0.9); return b; },
    () => { const p = K.makePencil([0xffc23d, 0x2dd4bf, 0x8b7cf6][orbitItems.length % 3]); return p; },
    () => { const g = new THREE.Group(); const pl = new THREE.Mesh(new THREE.SphereGeometry(0.42, 32, 20), new THREE.MeshStandardMaterial({ color: [0x7c6cf0, 0x2dd4bf, 0xff8fb1, 0x3a8bff][orbitItems.length % 4], roughness: 0.5 })); const rg = new THREE.Mesh(new THREE.TorusGeometry(0.66, 0.035, 6, 48), M.goldGlow); rg.rotation.x = 1.2; g.add(pl, rg); return g; },
    () => { const s = new THREE.Mesh(new THREE.ExtrudeGeometry(K.starShape(0.3, 0.15, 0.25), { depth: 0.08, bevelEnabled: true, bevelThickness: 0.05, bevelSize: 0.04, bevelSegments: 3 }), M.star); return s; },
    () => K.glyphSprite(["π", "۲", "√", "ب", "%", "۷", "ش", "+"][orbitItems.length % 8], 0.6, { font: "900 160px Vazirmatn" }),
    () => { const g = new THREE.Group(); const pad = new THREE.Mesh(new K.RoundedBox(0.9, 0.5, 0.18, 3, 0.12), K.std({ color: 0x8b7cf6, roughness: 0.3, clearcoat: 1, envMap: env })); const b1 = new THREE.Mesh(new THREE.SphereGeometry(0.07, 12, 8), M.goldGlow); b1.position.set(0.22, 0.05, 0.1); const b2 = b1.clone(); b2.material = M.cyanGlow; b2.position.set(0.32, -0.07, 0.1); const dp = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.06, 0.04), M.eyeHL); dp.position.set(-0.24, 0, 0.1); const dp2 = dp.clone(); dp2.rotation.z = Math.PI / 2; g.add(pad, b1, b2, dp, dp2); return g; },
  ];
  for (let i = 0; i < 66; i++) {
    const obj = itemMakers[i % itemMakers.length]();
    const ring = i % 4;
    orbitItems.push({ obj, ring, r: 3.0 + ring * 1.7 + prand(i * 3.7) * 0.8, a0: prand(i * 5.3) * Math.PI * 2, spin: prand(i * 2.2) * 3, tilt: [0.25, -0.35, 0.6, -0.15][ring], yo: (prand(i * 9.1) - 0.5) * 0.7 });
    orbitG.add(obj);
  }
  const spiral = (() => {
    const N = 5000, geo = new THREE.BufferGeometry(), p = new Float32Array(N * 3), r = new Float32Array(N);
    for (let i = 0; i < N; i++) { p.set([prand(i * 1.1), prand(i * 2.2), prand(i * 3.3)], i * 3); r[i] = prand(i * 4.4); }
    geo.setAttribute("position", new THREE.BufferAttribute(p, 3)); geo.setAttribute("rnd", new THREE.BufferAttribute(r, 1));
    const mat = new THREE.ShaderMaterial({
      transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
      uniforms: { uT: { value: 0 }, uK: { value: 0 }, uA: { value: 0 } },
      vertexShader: `attribute float rnd; uniform float uT, uK; varying float vR;
        void main(){ vR = rnd; float arm = floor(position.x*3.0); float rad = (0.4 + position.y*11.0) * (1.0 - uK*0.96);
          float a = arm*2.094 + rad*0.55 + uT*(1.2+uK*6.0) + position.z*0.4;
          vec3 q = vec3(cos(a)*rad, (position.z-0.5)*1.2*(1.0-uK), sin(a)*rad);
          vec4 mv = modelViewMatrix*vec4(q,1.0); gl_Position = projectionMatrix*mv; gl_PointSize = (3.0+rnd*7.0)*(12.0/max(0.5,-mv.z)); }`,
      fragmentShader: `uniform float uA; varying float vR; void main(){ float d = length(gl_PointCoord-0.5); float a = smoothstep(0.5,0.0,d);
        gl_FragColor = vec4(mix(vec3(1.0,0.78,0.35), vec3(1.0,0.95,0.8), vR)*1.8, a*a*uA); }`,
    });
    const pts = new THREE.Points(geo, mat); pts.frustumCulled = false; pts.position.copy(ORB_C); pts.rotation.x = 0.35;
    return pts;
  })();
  scene.add(spiral);

  /* ------------------------------------------------------ SCENE 8 final */
  const finalG = new THREE.Group(); finalG.position.copy(FINAL); scene.add(finalG);
  const finalGlow = K.softGlowSprite("rgba(70,140,255,0.16)", VERT ? 9 : 12); finalGlow.position.z = -3; finalG.add(finalGlow);
  const finalGold = K.softGlowSprite("rgba(255,200,90,0.08)", VERT ? 6.5 : 8); finalGold.position.z = -2.5; finalG.add(finalGold);
  const finalSparks = makeOrbitSparks(160, VERT ? 3.6 : 4.4, [1, 0.85, 0.5]); finalSparks.points.position.z = -1.5; finalG.add(finalSparks.points);

  /* ------------------------------------------------------- post */
  const msaa = new THREE.WebGLRenderTarget(width, height, { type: THREE.HalfFloatType, samples: SAMPLES });
  const composer = new EffectComposer(renderer, msaa);
  composer.setPixelRatio(1);
  composer.setSize(width, height);
  composer.addPass(new RenderPass(scene, camera));
  const bloom = new UnrealBloomPass(new THREE.Vector2(width / 2, height / 2), 0.5, 0.45, 1.0);
  // software-rendered: run the bloom mip chain at quarter resolution (it is a soft glow anyway)
  const bloomSetSize = bloom.setSize.bind(bloom);
  bloom.setSize = (w, h) => bloomSetSize(Math.round(w / 2), Math.round(h / 2));
  bloom.setSize(width, height);
  composer.addPass(bloom);
  composer.addPass(new OutputPass());
  const grade = new ShaderPass({
    uniforms: { tDiffuse: { value: null }, uFlash: { value: 0 }, uFade: { value: 0 }, uTime: { value: 0 }, uVig: { value: 0.32 }, uRes: { value: new THREE.Vector2(width, height) } },
    vertexShader: `varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix*modelViewMatrix*vec4(position,1.0); }`,
    fragmentShader: `uniform sampler2D tDiffuse; uniform float uFlash, uFade, uTime, uVig; uniform vec2 uRes; varying vec2 vUv;
      float h(vec2 p){ return fract(sin(dot(p, vec2(12.9898,78.233))) * 43758.5453); }
      void main(){ vec3 c = texture2D(tDiffuse, vUv).rgb;
        vec2 q = vUv - 0.5; float vig = smoothstep(0.85, 0.2, length(q*vec2(1.0, 0.9)));
        c *= mix(1.0 - uVig, 1.0, vig);
        c = mix(c, vec3(1.0, 0.86, 0.58), uFlash * (0.65 + 0.35 * (1.0 - length(q))));
        c *= (1.0 - uFade);
        c += (h(vUv*uRes + fract(uTime*7.31)*91.7) - 0.5) * 0.022;
        gl_FragColor = vec4(c, 1.0); }`,
  });
  // flash / fade / vignette are composited as DOM layers by the entry page (cheaper than a full-screen pass)

  /* ================================================================ timing */
  const galCurve = new THREE.CatmullRomCurve3(GPATH, false, "centripetal");
  const flipAt = (t, t0) => smoother(win(t, t0, t0 + 0.75)) * Math.PI;

  function cameraLand(t) {
    if (t < 6) {
      const pos = trackV(t, [{ t: 0, p: [1.0, 3.1, 8.4] }, { t: 2.2, p: [0.55, 2.55, 6.1] }, { t: 4.1, p: [0.15, 2.05, 4.7] }, { t: 5.0, p: [0, 1.75, 5.2] }, { t: 6.0, p: [-0.5, 2.0, 6.3] }]);
      const look = trackV(t, [{ t: 0, p: [0, 0.35, 0] }, { t: 3.9, p: [0, 0.4, 0] }, { t: 4.8, p: [0, 2.0, 0] }, { t: 6.0, p: [0, 2.2, 0] }]);
      return { pos, look, fov: 34 };
    }
    if (t < 13) {
      const sp = galCurve.getPointAt(0.09);
      const c0 = galCurve.getPointAt(0.025).add(V(0, 1.3, 0));
      const pos = trackV(t, [{ t: 6.0, p: [-0.5, 2.0, 6.3] }, { t: 7.6, p: [-1.9, 2.1, 5.9] }, { t: 9.6, p: [-3.6, 2.5, 4.4] }, { t: 11.2, p: [-5.6, 3.6, 5.0] }, { t: 13.0, p: [c0.x, c0.y, c0.z] }]);
      const look = trackV(t, [{ t: 6.0, p: [0, 2.2, 0] }, { t: 7.2, p: [0.5, 1.7, 0] }, { t: 9.6, p: [-0.4, 2.1, 0] }, { t: 11.2, p: [-0.4, 2.8, -0.4] }, { t: 13.0, p: [sp.x, sp.y, sp.z] }]);
      return { pos, look, fov: 36 };
    }
    if (t < 21) {
      const us = starU(t), uc = Math.max(0, us - 0.065);
      const pos = galCurve.getPointAt(uc).add(V(0, 1.3, 0));
      const sp = galCurve.getPointAt(us);
      // glance toward the nearest island
      // only islands ahead of the camera may pull the framing, and never so far the star leaves frame
      const fwd = sp.clone().sub(pos).normalize();
      let best = sp, bw = 0;
      for (const c of Object.values(ISL)) { const dir = c.clone().sub(pos); const ahead = clamp((dir.normalize().dot(fwd) - 0.2) / 0.5); const w = Math.exp(-Math.pow(pos.distanceTo(c) / 11, 2)) * ahead; if (w > bw) { bw = w; best = c; } }
      // step sideways, away from the island, so both the star and the island read
      const side = best.clone().sub(pos); side.y = 0; side.normalize();
      pos.addScaledVector(side, -2.2 * clamp(bw * 1.6));
      const look = sp.clone().lerp(best, 0.4 * clamp(bw * 1.5));
      return { pos, look, fov: 40 };
    }
    if (t < 29) {
      const end = galCurve.getPointAt(starU(21) - 0.065).add(V(0, 1.3, 0));
      const pos = trackV(t, [{ t: 21, p: [end.x, end.y, end.z] }, { t: 22.2, p: [0.1, 13.6, -62.4] }, { t: 29, p: [0.35, 13.5, -63.6] }]);
      const look = trackV(t, [{ t: 21, p: [0.4, 13.4, -70] }, { t: 22.2, p: [-0.3, 13.6, -75] }, { t: 29, p: [-0.1, 13.6, -75] }]);
      return { pos, look, fov: 38 };
    }
    if (t < 37) {
      const pos = trackV(t, [{ t: 29, p: [0.35, 13.5, -63.6] }, { t: 29.9, p: [0.2, 17.5, -84] }, { t: 30.7, p: [0, 15.2, -100] }, { t: 31.5, p: [0, 12.5, -108.6] }, { t: 36.4, p: [0.9, 12.3, -109.4] }, { t: 37.0, p: [0.5, 12.8, -110.2] }]);
      const look = trackV(t, [{ t: 29, p: [-0.1, 13.6, -75] }, { t: 29.9, p: [0, 12, -110] }, { t: 30.7, p: [0, 10.6, -118] }, { t: 31.5, p: [0, 12.3, -118] }, { t: 36.4, p: [0, 12.3, -118] }, { t: 37.0, p: [0, 15.6, -118.5] }]);
      return { pos, look, fov: 40 };
    }
    if (t < 44) {
      const pos = trackV(t, [{ t: 37, p: [0.5, 12.8, -110.2] }, { t: 37.7, p: [0, 15.9, -118.0] }, { t: 38.5, p: [0, 14.6, -147.6] }, { t: 44, p: [0.6, 14.4, -149.2] }]);
      const look = trackV(t, [{ t: 37, p: [0, 15.6, -118.5] }, { t: 37.7, p: [0, 15.8, -135] }, { t: 38.5, p: [0, 14.0, -160] }, { t: 44, p: [0, 14.0, -160] }]);
      return { pos, look, fov: 38 };
    }
    if (t < 48) {
      const pos = trackV(t, [{ t: 44, p: [0.6, 14.4, -149.2] }, { t: 46.3, p: [0, 18.2, -143.5], ease: easeOut }, { t: 48, p: [0, 16.4, -148.5] }]);
      const look = trackV(t, [{ t: 44, p: [0, 13.7, -160] }, { t: 45, p: [0, 14.4, -161.5] }, { t: 48, p: [0, 14.4, -161.5] }]);
      return { pos, look, fov: lerp(38, 46, smooth(win(t, 44, 46.3))) };
    }
    const k = win(t, 48, 55);
    return { pos: V(0, 40, -248 - k * 1.6), look: V(0, 40, -262), fov: 36 };
  }

  function cameraVert(t) {
    const c = cameraLand(t);
    // portrait: keep the subject, widen the vertical field and step back a little
    const back = t < 6 ? 1.25 : t < 13 ? 1.35 : t < 21 ? 1.1 : t < 29 ? 1.0 : t < 37 ? 1.3 : t < 44 ? 1.0 : t < 48 ? 1.15 : 1.0;
    let pos = c.look.clone().add(c.pos.clone().sub(c.look).multiplyScalar(back));
    let look = c.look;
    if (t >= 21 && t < 29) { pos = trackV(t, [{ t: 21, p: [c.pos.x, c.pos.y, c.pos.z] }, { t: 22.2, p: [0, 13.4, -58.2] }, { t: 29, p: [0, 13.4, -59.2] }]); look = trackV(t, [{ t: 21, p: [c.look.x, c.look.y, c.look.z] }, { t: 22.2, p: [0, 13.2, -75] }, { t: 29, p: [0, 13.2, -75] }]); }
    if (t >= 37.7 && t < 44) { pos = trackV(t, [{ t: 37.7, p: [0, 15.9, -118.0] }, { t: 38.5, p: [0, 14.2, -146.0] }, { t: 44, p: [0.4, 14.2, -147.0] }]); look = V(0, 14.2, -160); }
    // keep characters clear of the Reels UI cards: stage lower in S5, child higher in S6
    if (t >= 30.6 && t < 37) { const k = smooth(win(t, 30.6, 31.5)) * (1 - smooth(win(t, 36.4, 37))); pos.y += 2.0 * k; look = look.clone().add(V(0, 2.0 * k, 0)); }
    if (t >= 38.5 && t < 44.2) { const k = smooth(win(t, 38.5, 39.0)) * (1 - smooth(win(t, 43.6, 44.2))); pos.y -= 1.3 * k; look = look.clone().add(V(0, -1.3 * k, 0)); }
    return { pos, look, fov: t >= 48 ? 48 : c.fov + 18 };
  }

  const islandU = Object.values(ISL).map((c) => { let bu = 0, bd = 1e9; for (let i = 0; i <= 400; i++) { const d = galCurve.getPointAt(i / 400).distanceTo(c); if (d < bd) { bd = d; bu = i / 400; } } return bu; });
  const U_KEYS = [{ t: 13, v: 0.09 }, { t: 14.1, v: islandU[0] }, { t: 16.1, v: islandU[1] }, { t: 18.1, v: islandU[2] }, { t: 20.1, v: islandU[3] }, { t: 21, v: 0.985 }];
  function starU(t) { return clamp(K.trackN(t, U_KEYS), 0, 1); }

  /* ------------------------------------------------------------ render */
  const tmpM = new THREE.Matrix4(), tmpQ = new THREE.Quaternion(), tmpS = new THREE.Vector3(), tmpP = new THREE.Vector3(), tmpE = new THREE.Euler();

  function renderAt(T) {
    const t = LOOP ? 6 + 2.5 : T; // (loop layout uses renderLoop)
    if (LOOP) return renderLoop(T);
    const cam = VERT ? cameraVert(t) : cameraLand(t);
    camera.position.copy(cam.pos);
    camera.lookAt(cam.look);
    camera.fov = cam.fov;
    camera.updateProjectionMatrix();
    sky.position.copy(camera.position);
    key.target.position.copy(cam.look); key.position.copy(cam.look).add(V(4, 6, 6));
    rim.target.position.copy(cam.look); rim.position.copy(cam.look).add(V(-5, 3, -5));

    // global look development over time
    const skyB = t < 6 ? lerp(0.0, 0.32, smooth(win(t, 0.6, 5.5))) : t < 13 ? lerp(0.32, 0.72, smooth(win(t, 9.5, 12.5))) : t < 48 ? 0.72 : 0.6;
    skyMat.color.setScalar(skyB);
    galaxy.material.uniforms.uTime.value = t;
    galaxy.material.uniforms.uAlpha.value = t < 9 ? 0 : t < 48 ? smooth(win(t, 9.5, 12.5)) : 0.35;
    planets.visible = t > 9.5 && t < 48;
    planets.children.forEach((p) => { if (p.material.opacity !== undefined && p.geometry.type === "SphereGeometry") p.material.emissiveIntensity = 0.12; });
    hemi.intensity = t < 6 ? lerp(0.05, 0.55, smooth(win(t, 0.4, 3))) : 0.7;
    key.intensity = t < 6 ? lerp(0.1, 1.8, smooth(win(t, 0.3, 2.4))) : 2.0;
    rim.intensity = t < 6 ? lerp(0.3, 1.6, smooth(win(t, 0.3, 2.4))) : 1.6;
    [dustGold, dustBlue].forEach((d) => { d.material.uniforms.uTime.value = t; d.material.uniforms.uCam.value.copy(camera.position); d.material.uniforms.uScale.value = height / 1080; });
    dustBlue.material.uniforms.uAlpha.value = t < 6 ? 0.2 : 1;
    let bloomS = 0.55, flash = 0, fade = 0;

    /* ---------- scene 1–2 : book world */
    bookWorld.visible = t < 13.5;
    goldLight.visible = bookWorld.visible;
    if (bookWorld.visible) {
      bookWorld.position.y = Math.sin(t * 0.8) * 0.05;
      const reveal = smooth(win(t, 0.25, 1.6));
      firstLight.position.set(lerp(-1.8, 0.2, smooth(win(t, 0.1, 1.4))), lerp(2.8, 1.25, smooth(win(t, 0.1, 1.4))), lerp(1.2, 0.6, smooth(win(t, 0.1, 1.4))));
      firstLight.scale.setScalar((0.25 + reveal * 0.5) * (t < 4 ? 1 : 1 - win(t, 4, 4.6)));
      firstLight.visible = t < 4.7;
      goldLight.position.copy(firstLight.position).add(bookWorld.position);
      goldLight.intensity = t < 6 ? reveal * 7 * (1 - 0.3 * win(t, 4.2, 5)) : lerp(5, 2.5, win(t, 6, 13));
      runes.material.opacity = smooth(win(t, 1.2, 3.2)) * (0.65 + 0.35 * Math.sin(t * 2.0)) * (t > 12 ? 1 - win(t, 12, 13.4) : 1);
      platRim.material = M.goldGlow;
      // pages turning by themselves (Persian books turn left → right)
      flippers[0].userData.flip(flipAt(t, 2.55), 0);
      flippers[1].userData.flip(flipAt(t, 3.35), 0.002);
      flippers[2].userData.flip(flipAt(t, 6.05), 0.004);
      bookGlow.material.opacity = t < 4 ? reveal * 0.22 : t < 6.6 ? lerp(0.22, 0.6, win(t, 4, 5)) : lerp(0.6, 0.2, win(t, 6.6, 9));
      bookGlow.scale.setScalar(3.4 + Math.sin(t * 3) * 0.1);
      // spark rises, bursts into a constellation
      const sk = win(t, 3.95, 4.8);
      spark.visible = t > 3.9 && t < 4.95;
      spark.position.set(Math.sin(sk * 9) * 0.08, lerp(0.35, CONST_C.y, easeInOut(sk)), Math.cos(sk * 7) * 0.05);
      spark.scale.setScalar(0.35 + sk * 0.4 + Math.sin(t * 40) * 0.03);
      const bk = win(t, 4.8, 5.45);
      constNodes.forEach((n, i) => {
        n.visible = t > 4.8 && t < 9.5;
        const d = clamp(easeOutBack(clamp((t - 4.8 - i * 0.02) / 0.55)));
        n.position.copy(CONST_C).add(n.userData.to.clone().multiplyScalar(d));
        n.scale.setScalar((i < 10 ? 0.26 : 0.18) * (1 + 0.25 * Math.sin(t * 5 + i)) * (1 - win(t, 8.0, 9.4)));
      });
      constLines.visible = t > 4.95 && t < 9.5;
      lineGeo.setDrawRange(0, Math.floor(win(t, 4.95, 5.5) * segs.length / 2) * 2);
      constLines.material.opacity = 0.85 * (1 - win(t, 7.6, 9.4));
      burst.update(win(t, 4.8, 6.2), t > 4.8 && t < 6.3);
      flash = Math.max(flash, pulse(t, 5.2, 6.55) * 0.85);
      bloomS += pulse(t, 4.6, 6.6) * 1.1;
      // book world dims away as the galaxy appears
      platform.visible = true;
    }

    /* ---------- characters: moon & star */
    let mP = V(0, -5, 0), mR = [0, 0, 0], mS = 1, mSq = 1, mBl = 0, mBr = 0, mMouth = 0.6, mVis = false, mLook = [0, 0];
    let sP = V(0, -5, 0), sR = [0, 0, 0], sS = 0.5, sSq = 1, sBl = 0, sBr = 0, sMouth = 0.7, sVis = false, sLook = [0, 0];
    let sGlow = 1;
    const happy = (a, b) => (t > a && t < b ? 1 : 0);
    if (t >= 6 && t < 13.8) {
      // star leaps out (anticipation → leap → laugh)
      sVis = t > 6.15;
      const pk = win(t, 6.15, 6.55), lp = win(t, 6.55, 7.45);
      const arcY = Math.sin(lp * Math.PI) * 0.9;
      sP = V(lerp(0, 1.05, easeOut(lp)), lerp(0.15 + pk * 0.25, 2.25, easeOut(lp)) + arcY * 0.4, lerp(0, 0.55, lp));
      sS = lerp(0.05, 0.42, easeOutBack(pk)) * (1 + 0.18 * lp);
      sSq = t < 6.55 ? lerp(1, 0.72, pk) : t < 7.45 ? lerp(1.35, 1, lp) : 1 + 0.12 * Math.sin((t - 7.45) * 12) * Math.exp(-(t - 7.45) * 3);
      sR = [0, -0.25, (t < 7.45 ? -easeInOut(lp) * Math.PI * 2 : 0) + Math.sin(t * 2.2) * 0.08];
      sMouth = t > 7.4 && t < 8.6 ? 0.9 + Math.sin(t * 22) * 0.12 : 0.65; // laughing
      sBl = sBr = happy(7.45, 8.5);
      // moon rises from the pages, wakes up
      mVis = t > 7.9;
      const rk = smooth(win(t, 7.95, 9.5));
      mP = V(lerp(0, -0.85, rk), lerp(-0.5, 2.15, rk), lerp(0, -0.1, rk));
      mS = lerp(0.15, 1, easeOut(win(t, 7.95, 9.2)));
      mR = [0, lerp(0.6, 0.15, rk), lerp(-0.4, 0.05, rk)];
      const wake = win(t, 9.55, 9.8);
      mBl = mBr = t < 9.55 ? 1 : t < 9.8 ? 1 - wake : t > 10.05 && t < 10.18 ? 1 : 0;
      mMouth = t < 9.6 ? 0.02 : lerp(0.02, 0.7, win(t, 9.6, 10));
      mLook = t > 9.8 && t < 10.3 ? [1, 0.3] : [0.4, 0];
      // star jumps into the moon's embrace
      const hug = win(t, 10.2, 10.95);
      const cradle = (p, s) => p.clone().add(V(0.38 * s, 0.26 * s, 0.22 * s));
      if (t > 10.2) {
        const c = cradle(mP, mS);
        sP = vlerpArc(sP, c, easeInOut(hug), 0.7);
        sS = lerp(sS, 0.42, hug);
        sSq = t < 10.95 ? 1 : 1 + 0.15 * Math.sin((t - 10.95) * 14) * Math.exp(-(t - 10.95) * 4);
        sBl = sBr = happy(10.9, 11.6);
        mBl = mBr = t > 10.95 && t < 11.7 ? 1 : mBl;
        mSq = 1 + 0.06 * Math.sin((t - 10.95) * 12) * Math.exp(-(t - 10.95) * 3) * (t > 10.95 ? 1 : 0);
      }
      // fly upward together
      const fl = smoother(win(t, 11.0, 13.4));
      if (t > 11.0) {
        const target = galCurve.getPointAt(starU(13.0));
        const moonTo = target.clone().add(V(-0.7, -0.4, 0.2));
        mP = vlerpArc(mP, moonTo, fl, 0.9);
        mR = [0.05, lerp(mR[1], -0.4, fl), lerp(mR[2], 0.12, fl)];
        sP = cradle(mP, mS);
        if (t > 13.0) { // they separate: star ahead on its journey, moon soars off
          const sep = smooth(win(t, 13.0, 13.8));
          sP = sP.lerp(galCurve.getPointAt(starU(t)), sep);
          mP = mP.clone().add(V(-2.5 * sep, 4.2 * sep, -3 * sep));
        }
      }
      // orbital ring + symbols forming
      duoRing.visible = t > 10.8 && t < 13.9;
      const rf = easeOutBack(win(t, 10.9, 12.0));
      duoRing.position.copy(mP).add(V(0.2, 0.1, 0));
      duoRing.rotation.set(1.25 + Math.sin(t) * 0.05, 0.25, t * 0.6);
      ringMesh.scale.setScalar(rf);
      ringSyms.forEach((s, i) => {
        const a = (i / ringSyms.length) * Math.PI * 2 + t * 1.1;
        s.position.set(Math.cos(a) * 2.0 * rf, Math.sin(a) * 2.0 * rf, 0);
        s.material.opacity = win(t, 11.0 + i * 0.06, 11.5 + i * 0.06) * (1 - win(t, 13.2, 13.9));
      });
      bloomS += pulse(t, 10.9, 12.4) * 0.4;
    } else duoRing.visible = false;

    /* ---------- scene 3: galaxy of learning */
    islands.visible = t > 9.0 && t < 22.5;
    if (islands.visible) {
      for (let i = 0; i < rockData.length; i++) {
        const d = rockData[i];
        tmpP.copy(d.p).add(V(0, Math.sin(t * 0.5 + i) * 0.3, 0));
        tmpE.set(d.r + t * 0.2, d.r * 2 + t * 0.15, 0); tmpQ.setFromEuler(tmpE); tmpS.setScalar(d.s);
        rocks.setMatrixAt(i, tmpM.compose(tmpP, tmpQ, tmpS));
      }
      rocks.instanceMatrix.needsUpdate = true;
      [iMath, iSci, iFa, iArt].forEach((g, i) => { g.position.y = [ISL.math, ISL.sci, ISL.fa, ISL.art][i].y + Math.sin(t * 0.7 + i * 1.7) * 0.18; g.rotation.y = Math.sin(t * 0.2 + i) * 0.15; });
      // math: digits stream from portals, then settle into a golden spiral pattern
      mathG.position.y = iMath.position.y + 0.18;
      portalA.rotation.z = t * 0.8; portalB.rotation.z = -t * 0.9;
      solids.forEach((m, i) => { m.rotation.set(t * (0.5 + i * 0.1), t * (0.7 + i * 0.1), 0); m.position.y = 1.0 + (i % 2) * 0.6 + Math.sin(t * 1.3 + i) * 0.15; });
      digits.forEach((d, i) => {
        const em = clamp((t - 12.6 - i * 0.07) / 1.0);
        const src = i % 2 ? portalA.position : portalB.position;
        const ga = i * 2.39996 + t * 0.35, gr = 0.32 * Math.sqrt(i + 1) * 1.05;
        const dst = V(Math.cos(ga) * gr, 2.6 + Math.sin(ga) * gr * 0.55, -0.2 + Math.sin(ga) * 0.3);
        d.position.copy(vlerpArc(src.clone(), dst, easeInOut(em), 1.2));
        d.material.opacity = smooth(win(t, 12.6 + i * 0.07, 12.9 + i * 0.07));
        const sc = 0.55 + 0.45 * em; d.scale.copy(d.userData.baseScale).multiplyScalar(sc * (1 + 0.08 * Math.sin(t * 4 + i)));
      });
      // science: bubbling flasks, mixing droplet, molecule discovery
      sciG.position.y = iSci.position.y + 0.18;
      for (let i = 0; i < 36; i++) {
        const f = flasks[i % 3], ph = (t * (0.6 + prand(i) * 0.5) + prand(i * 2.1)) % 1;
        tmpP.set(f.position.x + (prand(i * 3.3) - 0.5) * 0.3, 0.3 + ph * 1.6, f.position.z + (prand(i * 4.4) - 0.5) * 0.3);
        tmpS.setScalar((0.5 + prand(i * 5.5)) * (1 - ph * 0.5)); tmpQ.identity();
        bubbles.setMatrixAt(i, tmpM.compose(tmpP, tmpQ, tmpS));
      }
      bubbles.instanceMatrix.needsUpdate = true;
      const dk = (t * 0.8) % 1;
      droplet.position.set(lerp(-1.5, 1.6, dk), 1.25 + Math.sin(dk * Math.PI) * 1.4, lerp(0.75, 0.65, dk));
      const mix = smooth(win(t, 15.3, 16.4));
      flasks[2].userData.liq.material.color.setRGB(lerp(0.45, 1.0, mix) * 1.5, lerp(1.0, 0.75, mix) * 1.5, lerp(0.55, 0.25, mix) * 1.5);
      molecule.rotation.set(t * 0.4, t * 0.9, 0);
      molecule.scale.setScalar(1.2 * (1 + 0.25 * pulse(t, 15.9, 16.6)));
      sciBurst.update(win(t, 16.0, 17.2), t > 16 && t < 17.2);
      // Persian: letters fly from the book and assemble into «دانش»
      faG.position.y = iFa.position.y + 0.18;
      faBook.userData.left.rotation.z = 0.12 + Math.sin(t * 2) * 0.04; faBook.userData.right.rotation.z = -0.12 - Math.sin(t * 2) * 0.04;
      const ak = smooth(win(t, 16.6, 18.0));
      letters.forEach((l, i) => {
        const a = i * 1.7 + t * 0.9;
        const free = V(Math.cos(a) * 1.6, 1.6 + Math.sin(t * 1.4 + i) * 0.6 + i * 0.15, Math.sin(a) * 0.8);
        const dst = V(l.userData.x * 0.95, 2.7, 0.2);
        l.position.copy(free.lerp(dst, ak));
        l.material.opacity = smooth(win(t, 16.2, 16.6)) * (1 - win(t, 18.05, 18.35));
      });
      word.position.set(0.05, 2.7, 0.2);
      word.material.opacity = win(t, 18.0, 18.35);
      word.scale.copy(word.userData.baseScale).multiplyScalar(1 + 0.12 * pulse(t, 18.0, 18.8));
      extraLet.forEach((l, i) => { const a = i * 0.8 + t * 0.5; l.position.set(Math.cos(a) * 2.6, 1.0 + Math.sin(a * 1.3) * 0.9 + (i % 3) * 0.4, Math.sin(a) * 1.6); l.material.opacity = 0.75; });
      // creative: pencils tilt up and launch like rockets, books flap like birds, idea bulb
      artG.position.y = iArt.position.y + 0.18;
      pencils.forEach(({ p, fl, i }) => {
        const lt = 19.35 + i * 0.22;
        const go = clamp((t - lt) / 1.6);
        const a = (i / 5) * Math.PI * 2;
        p.position.set(Math.cos(a) * 1.5, 0.55 + easeIn(go) * 9, Math.sin(a) * 1.2);
        p.rotation.set(0, 0, lerp(0.5 * Math.sin(a), 0, smooth(win(t, lt - 0.6, lt))) + Math.sin(t * 9) * 0.03 * go);
        fl.position.copy(p.position).add(V(0, -0.75, 0));
        fl.visible = t > lt - 0.15;
        fl.scale.setScalar(0.5 + 0.3 * Math.sin(t * 30 + i) + go * 0.4);
      });
      flyBooks.forEach((b, i) => {
        const a = t * 0.9 + (i * Math.PI * 2) / 3;
        b.position.set(Math.cos(a) * 2.4, 2.0 + Math.sin(t * 1.7 + i) * 0.4, Math.sin(a) * 1.6);
        b.rotation.set(0, -a, 0);
        const flap = Math.sin(t * 9 + i) * 0.7;
        b.userData.left.rotation.z = 0.3 + flap; b.userData.right.rotation.z = -0.3 - flap;
      });
      bulb.position.y = 2.4 + Math.sin(t * 1.5) * 0.15;
      bulbGlass.material.emissiveIntensity = 1.0 + 0.6 * pulse(t, 19.0, 19.8);
    }
    if (t >= 13.8 && t < 21.4) {
      sVis = true;
      const us = starU(t);
      sP = galCurve.getPointAt(us);
      const tan = galCurve.getTangentAt(us);
      sS = 0.42; sR = [0.2, Math.atan2(tan.x, tan.z) + Math.PI + Math.sin(t * 1.3) * 0.3, -tan.x * 0.6 + Math.sin(t * 2.1) * 0.15];
      sMouth = 0.75 + 0.2 * Math.sin(t * 3); sLook = [Math.sin(t * 0.9), 0.2];
      sBl = sBr = (t % 3.1) > 3.0 ? 1 : 0;
    }

    /* ---------- scene 4: learning through play */
    portalG.visible = t > 20.4 && t < 29.6;
    kidsPadG.visible = boy.root.visible = girl.root.visible = false;
    if (portalG.visible) {
      const op = easeOutBack(win(t, 21.2, 22.1));
      portalG.scale.setScalar(Math.max(0.001, op) * (t > 28.8 ? 1 - win(t, 28.8, 29.5) : 1));
      pDiscMat.uniforms.uTime.value = t; pDiscMat.uniforms.uOpen.value = smooth(win(t, 21.3, 22.2));
      pRing.rotation.z = t * 0.2; pRing2.rotation.z = -t * 0.35;
      portalSparks.update(t, smooth(win(t, 21.2, 22)));
      spotLight.intensity = 0;
      bloomS += pulse(t, 21.1, 22.4) * 0.5;
      // kids watching & tapping
      kidsPadG.visible = boy.root.visible = girl.root.visible = true;
      kidsPadG.position.y = KIDS.y + Math.sin(t * 0.8) * 0.06;
      const facing = Math.atan2(PORTAL.x - KIDS.x, PORTAL.z - KIDS.z);
      placeChild(boy, KIDS.clone().add(VERT ? V(-0.75, 0, 0.3) : V(-0.55, 0, 0.35)), facing + (VERT ? 0.15 : 0.35), t, [22.55, 23.55, 26.0]);
      placeChild(girl, KIDS.clone().add(VERT ? V(0.75, 0, 0.1) : V(0.65, 0, -0.25)), facing + (VERT ? -0.15 : 0.15), t, [24.75, 27.0]);
      boy.root.position.y += kidsPadG.position.y - KIDS.y; girl.root.position.y += kidsPadG.position.y - KIDS.y;
      // the moon opens the portal, the star watches and cheers
      mVis = true;
      const mHome = VERT ? V(-3.1, 19.6, -78.5) : V(-4.6, 16.6, -77.5);
      mP = mHome.clone().add(V(0, Math.sin(t * 1.1) * 0.12, 0));
      mS = 0.85; mR = [0, 0.5 + (VERT ? -0.2 : 0), Math.sin(t * 0.9) * 0.05 - 0.15 * pulse(t, 20.9, 21.7)];
      mSq = 1 + 0.1 * pulse(t, 21.0, 21.6);
      mBl = mBr = (t > 21.0 && t < 21.7) || (t > 23.6 && t < 24.1) ? 1 : 0;
      mMouth = 0.7; mLook = [1, -0.5];
      sVis = true;
      const sHome = VERT ? V(3.2, 19.2, -77.8) : V(6.0, 16.2, -76.4);
      const arrive = smooth(win(t, 21.0, 22.0));
      sP = galCurve.getPointAt(starU(21)).lerp(sHome, arrive);
      const cheer = Math.max(pulse(t, 23.6, 24.3), pulse(t, 24.8, 25.5), pulse(t, 26.0, 26.7), pulse(t, 27.0, 27.7));
      sP.y += Math.sin(t * 1.6) * 0.12 + cheer * 0.45;
      sS = 0.42; sR = [0, -0.5, Math.sin(t * 1.3) * 0.1 + cheer * 0.5]; sSq = 1 + cheer * 0.15;
      sBl = sBr = cheer > 0.3 ? 1 : 0; sMouth = 0.7 + cheer * 0.25; sLook = [-1, -0.4];
    } else spotLight.intensity = 0;

    /* ---------- scene 5: live competition */
    arenaG.visible = t > 28.6 && t < 38.2;
    if (arenaG.visible) {
      sGlow = 0.45;
      floorLines.rotation.z = t * 0.1;
      for (let i = 0; i < crowdN; i++) {
        const d = crowdData[i];
        const jump = t > 35 ? Math.abs(Math.sin((t - 35) * 7 + d.ph)) * 0.35 * (1 - win(t, 36.5, 37.4) * 0.5) : Math.abs(Math.sin(t * 2 + d.ph)) * 0.05;
        tmpP.set(Math.cos(d.a) * d.rr, d.yy + jump, Math.sin(d.a) * d.rr);
        tmpQ.identity(); tmpS.setScalar(1);
        crowd.setMatrixAt(i, tmpM.compose(tmpP, tmpQ, tmpS));
      }
      crowd.instanceMatrix.needsUpdate = true;
      crowd.material.emissiveIntensity = 0.04 + 0.22 * pulse(t, 35, 36.6);
      beams.forEach((b, i) => { b.rotation.set(0.55 + Math.sin(t * 0.7 + i) * 0.25, 0, Math.cos(t * 0.6 + i * 1.3) * 0.35); b.lookAt(ARENA.x + Math.sin(t * 0.5 + i) * 3, ARENA.y, ARENA.z + Math.cos(t * 0.4 + i) * 3); b.rotateX(-Math.PI / 2); });
      // confetti from 35.0
      const ck = t - 35.0;
      confetti.visible = ck > 0 && t < 37.05;
      if (ck > 0) {
        for (let i = 0; i < confN; i++) {
          const a = prand(i * 1.3) * Math.PI * 2, r = prand(i * 2.7) * 9, h0 = 9 + prand(i * 3.9) * 4;
          const fall = ck * (1.4 + prand(i * 4.1) * 1.1);
          tmpP.set(Math.cos(a) * r + Math.sin(ck * 2 + i) * 0.4, h0 - fall + Math.min(ck * 6, 2.5) * (1 - win(ck, 0, 0.6)), Math.sin(a) * r + Math.cos(ck * 1.7 + i) * 0.4);
          tmpE.set(ck * 6 + i, ck * 4 + i * 2, ck * 5); tmpQ.setFromEuler(tmpE); tmpS.setScalar(1.3);
          confetti.setMatrixAt(i, tmpM.compose(tmpP, tmpQ, tmpS));
        }
        confetti.instanceMatrix.needsUpdate = true;
      }
      // portal above the stage for the transition
      arenaPortal.visible = t > 36.2;
      arenaPortal.scale.setScalar(Math.max(0.001, easeOutBack(win(t, 36.2, 36.9))));
      apDisc.material.uniforms.uTime.value = t; apDisc.material.uniforms.uOpen.value = smooth(win(t, 36.3, 36.9));
      bloomS += pulse(t, 34.9, 36.0) * 0.25;
      // star: lands on stage, thinks, answers, celebrates
      if (t >= 29) {
        sVis = true;
        const stageTop = ARENA.clone().add(V(0, 1.55, 0));
        const fly = smooth(win(t, 29.0, 30.9));
        const from = VERT ? V(3.2, 19.2, -77.8) : V(6.0, 16.2, -76.4);
        sP = vlerpArc(from, stageTop, fly, 3.0);
        const think = pulse(t, 32.6, 34.3), jump = pulse(t, 34.35, 34.85), joy = t > 35 ? Math.abs(Math.sin((t - 35) * 6)) * Math.exp(-(t - 35) * 0.8) : 0;
        sP.y += Math.sin(t * 2.2) * 0.06 + jump * 0.5 + joy * 0.8;
        sS = 0.55; sR = [0, (t > 35 ? (t - 35) * 5 : 0) + think * 0.3, think * 0.25]; sSq = 1 + jump * 0.25 - (t > 34.3 && t < 34.4 ? 0.2 : 0);
        sLook = think > 0.1 ? [0.6, 1] : [0, 0]; sMouth = think > 0.3 ? 0.15 : 0.8; sBl = sBr = t > 35 && t < 36.4 ? 1 : 0;
        if (t >= 36.6) { const up = smooth(win(t, 36.6, 37.6)); sP = sP.lerp(ARENA.clone().add(V(0, 3.6, 2.2)), up); }
      }
    }

    /* ---------- tunnel */
    tunnel.visible = t > 36.9 && t < 38.6;
    if (tunnel.visible) {
      streaks.material.opacity = 0.75 * pulse(t, 36.9, 38.6);
      bloomS += pulse(t, 37.0, 38.5) * 0.25;
      flash = Math.max(flash, pulse(t, 37.95, 38.65) * 0.1);
      if (t >= 37.6) { sVis = true; const cp = camera.position.clone(); sP = cp.add(V(0.4, -0.35, -2.6 - win(t, 37.6, 38.4) * 1.2)); sS = 0.3; sR = [0.3, Math.PI, 0]; }
    }

    /* ---------- scene 6: the power of progress */
    dashG.visible = t > 37.8 && t < 46.8;
    badge.visible = false;
    if (dashG.visible) {
      const fadeOut = 1 - win(t, 44.4, 46.4);
      sheets.forEach((s, i) => { const a = i * 2.1 + t * 0.12; s.position.set(Math.cos(a) * 4.6, 2.2 + Math.sin(t * 0.6 + i) * 0.4 + i * 0.4, -2.2 + Math.sin(a) * 1.4); s.rotation.set(0, -a * 0.5 + 0.3, Math.sin(t + i) * 0.08); s.scale.setScalar(fadeOut); });
      miniStars.forEach((s, i) => { const a = (i / miniStars.length) * Math.PI * 2 + t * 0.35; s.position.set(Math.cos(a) * 3.6, 2.6 + Math.sin(a * 2 + t) * 0.6, Math.sin(a) * 2.2 - 1.2); s.rotation.set(0, t * 1.5 + i, 0); s.scale.setScalar(fadeOut); });
      sideBadges.forEach((b, i) => { b.position.set((i - 1) * 1.6, 4.2 + Math.sin(t * 1.2 + i) * 0.15, -2.8); b.rotation.y = Math.sin(t + i) * 0.4; b.scale.setScalar(0.55 * fadeOut * smooth(win(t, 38.8 + i * 0.25, 39.3 + i * 0.25))); });
      // child on the pad
      boy.root.visible = t < 45.5;
      const bpos = DASH.clone().add(V(0, 0, 0.2));
      placeChild(boy, bpos, 0.0, t, []);
      const lift = smooth(win(t, 41.5, 42.1));
      boy.armL.rotation.set(-lift * 2.6, 0, 0.15 + lift * 0.1);
      boy.headPivot.rotation.x = -0.12 * lift;
      boy.smile.scale.set(1 + lift * 0.3, 1 + lift * 0.4, 1);
      boy.root.scale.setScalar(fadeOut > 0 ? 1 : 0.001);
      // badge collected by the star and given to the child
      badge.visible = t > 39.2 && t < 45.4;
      const bAppear = easeOutBack(win(t, 39.2, 39.8));
      const bFloat = DASH.clone().add(V(2.6, 4.0, -0.4));
      const hand = boy.root.position.clone().add(V(0.4 + lift * 0.0, lerp(0.85, 2.25, lift), lerp(0.35, 0.15, lift)));
      let bp = bFloat.clone();
      sVis = t > 38.3;
      const sIn = smooth(win(t, 38.3, 39.6));
      sP = DASH.clone().add(V(lerp(5.5, 3.6, sIn), 3.4 + Math.sin(t * 1.5) * 0.12, -0.6));
      if (t > 39.9) { const g2 = smooth(win(t, 39.9, 40.3)); sP = sP.lerp(bFloat.clone().add(V(0.4, -0.15, 0.3)), g2); }
      if (t > 40.3) { const go = smooth(win(t, 40.3, 41.45)); const near = hand.clone().add(V(0.45, 0.2, 0.3)); sP = vlerpArc(bFloat.clone().add(V(0.4, -0.15, 0.3)), near, go, 0.6); bp = vlerpArc(bFloat, hand, go, 0.6); }
      if (t > 41.45) { bp = hand.clone(); sP = hand.clone().add(V(0.6 + win(t, 41.5, 42.5) * 0.6, 0.5 + Math.sin(t * 2) * 0.1, 0.3)); }
      badge.position.copy(bp);
      badge.rotation.set(0, Math.sin(t * 2) * 0.4 + (t < 41.4 ? t * 2 : 0), 0);
      badge.scale.setScalar(Math.max(0.001, bAppear) * 0.85 * fadeOut);
      awardBurst.points.position.copy(hand);
      awardBurst.update(win(t, 41.5, 42.8), t > 41.5 && t < 42.8);
      sS = 0.38 * Math.max(0.001, fadeOut > 0.5 ? 1 : 1); sR = [0, -0.6, Math.sin(t * 1.4) * 0.1]; sMouth = 0.8; sBl = sBr = t > 41.5 && t < 42.4 ? 1 : 0; sLook = [-1, -0.3];
      // moon watches proudly
      mVis = true;
      mP = DASH.clone().add(VERT ? V(-1.5, 4.3, -1.2) : V(-3.0, 3.0, -0.8)).add(V(0, Math.sin(t * 1.0) * 0.1, 0));
      mS = VERT ? 0.7 : 0.85; mR = [0, 0.55, -0.08 - 0.1 * pulse(t, 41.6, 42.6)];
      mBl = mBr = t > 41.7 && t < 43.0 ? 1 : 0; mMouth = 0.55 + 0.2 * pulse(t, 41.6, 43); mLook = [1, -0.6];
      bloomS += pulse(t, 41.4, 42.6) * 0.45;
    }

    /* ---------- scene 7: the big reveal */
    orbitG.visible = t > 44.0 && t < 48.3;
    spiral.visible = t > 45.6 && t < 48.6;
    if (t >= 44 && t < 48.3) {
      const g = smooth(win(t, 44.0, 45.0));
      const con = easeIn(win(t, 46.5, 47.95));
      mVis = true;
      mP = (mP.y > -4 ? mP : ORB_C.clone()).lerp(ORB_C, g);
      mS = lerp(mS, 1.25, g) * (1 - con * 0.95);
      mR = [0, lerp(mR[1], 0.12, g), Math.sin(t * 2) * 0.05];
      mBl = mBr = t > 45.2 && t < 45.4 ? 1 : 0; mMouth = 0.75; mLook = [0.5, 0];
      sVis = true;
      const cr = ORB_C.clone().add(V(0.38 * mS, 0.26 * mS, 0.22 * mS));
      sP = sP.y > -4 ? sP.lerp(cr, smooth(win(t, 44.0, 44.9))) : cr;
      if (t > 44.9) sP = cr;
      sS = lerp(0.38, 0.42 * 1.25, g) * (1 - con * 0.95); sBl = sBr = 1; sMouth = 0.85;
      const speed = (x) => 0.5 * x + 0.9 * x * x; // accelerating orbit phase
      orbitItems.forEach((o, i) => {
        const ph = o.a0 + speed(t - 44) * (1 + o.ring * 0.15);
        const appear = smooth(win(t, 44.0 + (i % 12) * 0.05, 44.9 + (i % 12) * 0.05));
        const r = o.r * (1 - con) * (0.6 + 0.4 * appear);
        tmpP.set(Math.cos(ph) * r, o.yo + Math.sin(ph) * r * Math.sin(o.tilt), Math.sin(ph) * r * Math.cos(o.tilt));
        o.obj.position.copy(ORB_C).add(tmpP);
        o.obj.rotation.set(t * o.spin, t * (o.spin + 0.5), 0);
        const sc = appear * (1 - con);
        if (o.obj.isSprite) { o.obj.scale.copy(o.obj.userData.baseScale).multiplyScalar(Math.max(0.001, sc)); } else o.obj.scale.setScalar(Math.max(0.001, sc));
      });
      spiral.material.uniforms.uT.value = t; spiral.material.uniforms.uK.value = con;
      spiral.material.uniforms.uA.value = smooth(win(t, 45.6, 46.8)) * (1 - win(t, 48.0, 48.5));
      bloomS += win(t, 46.0, 47.9) * 1.2;
      flash = Math.max(flash, smooth(win(t, 47.55, 48.0)) * (1 - win(t, 48.0, 48.9)));
    } else if (t >= 48.3 && t < 48.6) { spiral.material.uniforms.uA.value = 1 - win(t, 48.0, 48.6); }
    if (t >= 48 && t < 49.2) flash = Math.max(flash, 1 - smooth(win(t, 48.0, 49.0)));

    /* ---------- scene 8: final brand moment */
    finalG.visible = t >= 47.95;
    if (finalG.visible) {
      finalSparks.update(t, smooth(win(t, 48.0, 49.0)));
      finalGlow.material.opacity = 0.85 + 0.15 * Math.sin(t * 1.5);
      finalGold.material.opacity = 0.7 + 0.3 * Math.sin(t * 2.1);
      skyMat.color.setScalar(0.55);
      if (t >= 50.5) {
        const inn = easeOutBack(win(t, 50.5, 51.35));
        const wink = t > 51.55 && t < 51.95;
        mVis = true; sVis = true;
        const mx = VERT ? -1.9 : -5.1, sx = VERT ? 2.0 : 5.15, yy = VERT ? -2.55 : 0.15;
        mP = FINAL.clone().add(V(lerp(mx - 4, mx, inn), yy + Math.sin(t * 1.2) * 0.08, 0.6));
        mS = (VERT ? 0.62 : 0.95) * Math.max(0.001, inn); mR = [0, 0.35, Math.sin(t * 1.1) * 0.05];
        mBl = 0; mBr = wink ? 1 : 0; mMouth = wink ? 0.85 : 0.7; mLook = [1, 0];
        const wave = t > 51.6 && t < 53.2 ? Math.sin((t - 51.6) * 11) * 0.28 * (1 - win(t, 52.7, 53.2)) : 0;
        sP = FINAL.clone().add(V(lerp(sx + 4, sx, inn), yy + 0.3 + Math.sin(t * 1.6) * 0.1, 0.6));
        sS = (VERT ? 0.42 : 0.6) * Math.max(0.001, inn); sR = [0, -0.35, wave]; sSq = 1 + Math.abs(wave) * 0.2;
        sMouth = 0.85; sBl = sBr = t > 51.6 && t < 52.2 ? 1 : 0; sLook = [-1, 0];
      }
    }

    K.pose(moon, { p: mP, r: mR, s: mS, sq: mSq, blinkL: mBl, blinkR: mBr, mouth: mMouth, look: mLook, visible: mVis });
    K.pose(star, { p: sP, r: sR, s: sS, sq: sSq, blinkL: sBl, blinkR: sBr, mouth: sMouth, look: sLook, visible: sVis, glow: sGlow });

    bloom.strength = bloomS;
    grade.uniforms.uFlash.value = clamp(flash);
    grade.uniforms.uFade.value = fade;
    grade.uniforms.uTime.value = t;
    composer.render();
    return { flash: clamp(flash), fade };
  }

  /* ---------------------------------------------------- 10 s hero loop */
  function renderLoop(T) {
    const P = (T % 10) / 10, w = P * Math.PI * 2; // everything periodic in 10 s
    const look = V(0, 1.7, 0);
    const pos = V(Math.sin(w) * 1.2 - 0.6, 2.3 + Math.sin(w) * 0.25, 7.8 + Math.cos(w) * 0.4);
    camera.position.copy(pos); camera.lookAt(look); camera.fov = 36; camera.updateProjectionMatrix();
    sky.position.copy(pos);
    key.target.position.copy(look); key.position.set(4, 7, 6); rim.target.position.copy(look); rim.position.set(-5, 3, -5);
    skyMat.color.setScalar(0.85);
    galaxy.material.uniforms.uTime.value = 30 + Math.sin(w) * 2; galaxy.material.uniforms.uAlpha.value = 0.8;
    planets.visible = true; islands.visible = true;
    [iMath, iSci, iFa, iArt].forEach((g, i) => { g.position.set([-15, 15, -20, 19][i], [2.5, 4.5, 7, 0.5][i] + Math.sin(w + i * 1.5) * 0.25, [-22, -26, -42, -44][i]); });
    [mathG, sciG, faG, artG].forEach((g) => (g.visible = false));
    rocks.visible = false;
    [dustGold, dustBlue].forEach((d) => { d.material.uniforms.uTime.value = Math.sin(w) * 3; d.material.uniforms.uCam.value.copy(pos); d.material.uniforms.uScale.value = height / 1080; d.material.uniforms.uAlpha.value = 1; });
    bookWorld.visible = true; bookWorld.position.y = Math.sin(w) * 0.06;
    runes.material.opacity = 0.7 + 0.2 * Math.sin(w * 2); runes.rotation.z = w;
    flippers[0].userData.flip(Math.PI); flippers[1].userData.flip(0); flippers[2].userData.flip(smoother(clamp((P - 0.3) / 0.15)) * Math.PI * (1 - smoother(clamp((P - 0.75) / 0.15))));
    firstLight.visible = spark.visible = false; constLines.visible = false; constNodes.forEach((n) => (n.visible = false)); burst.points.visible = false;
    bookGlow.material.opacity = 0.22 + 0.06 * Math.sin(w * 2);
    goldLight.position.set(0, 1.4, 0.6); goldLight.intensity = 2.5; goldLight.visible = true;
    const mP = V(-0.85, 2.15 + Math.sin(w) * 0.12, -0.1);
    K.pose(moon, { p: mP, r: [0, 0.15 + Math.sin(w) * 0.08, 0.05 + Math.sin(w * 2) * 0.03], s: 1, blinkL: P > 0.42 && P < 0.44 ? 1 : 0, blinkR: P > 0.42 && P < 0.44 ? 1 : 0, mouth: 0.7, look: [0.5, 0], glow: 0.55 });
    K.pose(star, { p: mP.clone().add(V(0.38, 0.26 + Math.sin(w * 2) * 0.04, 0.22)), r: [0, -0.2, Math.sin(w * 2) * 0.12], s: 0.42, blinkL: P > 0.82 && P < 0.84 ? 1 : 0, blinkR: P > 0.82 && P < 0.84 ? 1 : 0, mouth: 0.8, sq: 1 + Math.sin(w * 4) * 0.03, glow: 0.6 });
    duoRing.visible = true; duoRing.position.copy(mP).add(V(0.2, 0.1, 0)); duoRing.rotation.set(1.25, 0.25, w); ringMesh.scale.setScalar(1);
    ringSyms.forEach((s, i) => { const a = (i / ringSyms.length) * Math.PI * 2 + w; s.position.set(Math.cos(a) * 2.0, Math.sin(a) * 2.0, 0); s.material.opacity = 1; });
    [portalG, arenaG, tunnel, dashG, orbitG, spiral, finalG, kidsPadG, badge].forEach((o) => (o.visible = false));
    boy.root.visible = girl.root.visible = false;
    bloom.strength = 0.45;
    composer.render();
    return { flash: 0, fade: 0 };
  }

  /* -------------------------------------------------------- helpers */
  function placeChild(ch, p, yaw, t, taps) {
    ch.root.position.copy(p);
    ch.root.rotation.y = yaw;
    ch.root.position.y += Math.abs(Math.sin(t * 2.2 + p.x)) * 0.02;
    let tap = 0;
    taps.forEach((tt) => { tap = Math.max(tap, pulse(t, tt - 0.35, tt + 0.35)); });
    ch.armL.rotation.set(-tap * 1.9, 0, 0.15 + tap * 0.2);
    ch.armR.rotation.set(Math.sin(t * 1.5 + p.x) * 0.05, 0, -0.15);
    ch.headPivot.rotation.set(-0.08 - tap * 0.08, Math.sin(t * 0.7 + p.x) * 0.12, Math.sin(t * 0.9) * 0.05);
    ch.smile.scale.set(1, 1, 1);
  }
  function vlerpArc(a, b, k, h) { const p = K.vlerp(a, b, k); p.y += Math.sin(k * Math.PI) * h; return p; }

  return { renderAt, camera, renderer, scene, composer, bloom, CUE, lights: { goldLight, spotLight, key, rim, hemi } };
}

/* ------------------------------------------------- particle utilities */
function makeBurst(N, origin, color) {
  const geo = new THREE.BufferGeometry();
  const p = new Float32Array(N * 3), d = new Float32Array(N * 3), r = new Float32Array(N);
  for (let i = 0; i < N; i++) {
    const u = prand(i * 1.7) * 2 - 1, th = prand(i * 2.9) * Math.PI * 2, s = Math.sqrt(1 - u * u);
    d.set([s * Math.cos(th), u, s * Math.sin(th)], i * 3);
    p.set([origin.x, origin.y, origin.z], i * 3);
    r[i] = prand(i * 4.1);
  }
  geo.setAttribute("position", new THREE.BufferAttribute(p, 3));
  geo.setAttribute("dir", new THREE.BufferAttribute(d, 3));
  geo.setAttribute("rnd", new THREE.BufferAttribute(r, 1));
  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uK: { value: 0 }, uColor: { value: new THREE.Color(...color) } },
    vertexShader: `attribute vec3 dir; attribute float rnd; uniform float uK; varying float vA;
      void main(){ float k = uK; float dist = (1.0 - pow(1.0 - k, 3.0)) * (1.5 + rnd * 3.0);
        vec3 q = position + dir * dist + vec3(0.0, -k*k*0.8, 0.0);
        vec4 mv = modelViewMatrix * vec4(q, 1.0); gl_Position = projectionMatrix * mv;
        gl_PointSize = (2.0 + rnd * 6.0) * (1.0 - k*0.6) * (14.0 / max(0.5, -mv.z)); vA = (1.0 - k) * (0.6 + rnd*0.4); }`,
    fragmentShader: `uniform vec3 uColor; varying float vA; void main(){ float d = length(gl_PointCoord - 0.5); float a = smoothstep(0.5, 0.0, d); gl_FragColor = vec4(uColor * 2.0, a * a * vA); }`,
  });
  const points = new THREE.Points(geo, mat);
  points.frustumCulled = false;
  return { points, update: (k, vis) => { mat.uniforms.uK.value = k; points.visible = vis; } };
}

function makeOrbitSparks(N, R, color) {
  const geo = new THREE.BufferGeometry();
  const p = new Float32Array(N * 3), r = new Float32Array(N);
  for (let i = 0; i < N; i++) { p.set([prand(i * 1.1) * Math.PI * 2, R * (0.9 + prand(i * 2.3) * 0.25), (prand(i * 3.7) - 0.5) * 0.5], i * 3); r[i] = prand(i * 5.9); }
  geo.setAttribute("position", new THREE.BufferAttribute(p, 3));
  geo.setAttribute("rnd", new THREE.BufferAttribute(r, 1));
  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uT: { value: 0 }, uA: { value: 0 }, uColor: { value: new THREE.Color(...color) } },
    vertexShader: `attribute float rnd; uniform float uT; varying float vA;
      void main(){ float a = position.x + uT * (0.4 + rnd * 0.6); vec3 q = vec3(cos(a) * position.y, sin(a) * position.y, position.z);
        vec4 mv = modelViewMatrix * vec4(q, 1.0); gl_Position = projectionMatrix * mv;
        gl_PointSize = (2.0 + rnd * 5.0) * (14.0 / max(0.5, -mv.z)); vA = 0.5 + 0.5 * sin(uT * (2.0 + rnd * 3.0) + rnd * 40.0); }`,
    fragmentShader: `uniform vec3 uColor; uniform float uA; varying float vA; void main(){ float d = length(gl_PointCoord - 0.5); float a = smoothstep(0.5, 0.0, d); gl_FragColor = vec4(uColor * 2.0, a * a * vA * uA); }`,
  });
  const points = new THREE.Points(geo, mat);
  points.frustumCulled = false;
  return { points, update: (t, a) => { mat.uniforms.uT.value = t; mat.uniforms.uA.value = a; } };
}
