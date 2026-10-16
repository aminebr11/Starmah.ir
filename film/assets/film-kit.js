// STARMAH film — shared building blocks: math helpers, textures, materials, characters, props.
// Everything is deterministic: no clocks, no unseeded randomness.
import * as THREE from "three";
import { RoundedBoxGeometry } from "three/addons/geometries/RoundedBoxGeometry.js";

/* ------------------------------------------------------------------ math */
export const clamp = (x, a = 0, b = 1) => Math.min(b, Math.max(a, x));
export const lerp = (a, b, k) => a + (b - a) * k;
export const win = (t, a, b) => clamp((t - a) / (b - a));
export const smooth = (x) => x * x * (3 - 2 * x);
export const smoother = (x) => x * x * x * (x * (x * 6 - 15) + 10);
export const easeOut = (x) => 1 - Math.pow(1 - x, 3);
export const easeIn = (x) => x * x * x;
export const easeInOut = (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2);
export const easeOutBack = (x, s = 1.70158) => 1 + (s + 1) * Math.pow(x - 1, 3) + s * Math.pow(x - 1, 2);
export const pulse = (t, a, b) => Math.sin(Math.PI * win(t, a, b)); // 0→1→0 over [a,b]
export const prand = (n) => { const x = Math.sin(n * 127.1 + 311.7) * 43758.5453; return x - Math.floor(x); };
export const V = (x, y, z) => new THREE.Vector3(x, y, z);
export const vlerp = (a, b, k) => new THREE.Vector3(lerp(a.x, b.x, k), lerp(a.y, b.y, k), lerp(a.z, b.z, k));

/** Camera / object keyframe track with Catmull–Rom position interpolation.
 *  keys: [{t, p:[x,y,z]}...] — returns Vector3 at time t (holds at ends). `ease` per segment optional. */
export function trackV(t, keys) {
  if (t <= keys[0].t) return V(...keys[0].p);
  const n = keys.length;
  if (t >= keys[n - 1].t) return V(...keys[n - 1].p);
  let i = 0;
  while (i < n - 2 && t > keys[i + 1].t) i++;
  const k0 = keys[Math.max(0, i - 1)], k1 = keys[i], k2 = keys[i + 1], k3 = keys[Math.min(n - 1, i + 2)];
  let u = (t - k1.t) / (k2.t - k1.t);
  if (k2.ease) u = k2.ease(u);
  const out = new THREE.Vector3();
  for (let a = 0; a < 3; a++) {
    const p0 = k0.p[a], p1 = k1.p[a], p2 = k2.p[a], p3 = k3.p[a];
    // tension-adjusted Catmull–Rom (Hermite with tangents scaled by segment duration)
    const d1 = k2.t - k1.t;
    const m1 = k1 === k0 ? 0 : ((p2 - p0) / (k2.t - k0.t)) * d1;
    const m2 = k3 === k2 ? 0 : ((p3 - p1) / (k3.t - k1.t)) * d1;
    const u2 = u * u, u3 = u2 * u;
    out.setComponent(a, (2 * u3 - 3 * u2 + 1) * p1 + (u3 - 2 * u2 + u) * m1 + (-2 * u3 + 3 * u2) * p2 + (u3 - u2) * m2);
  }
  return out;
}
export function trackN(t, keys) {
  const k = keys.map((q) => ({ t: q.t, p: [q.v, 0, 0], ease: q.ease }));
  return trackV(t, k).x;
}

/* -------------------------------------------------------------- textures */
export function canvasTex(w, h, draw, { srgb = true } = {}) {
  const c = document.createElement("canvas");
  c.width = w; c.height = h;
  const g = c.getContext("2d");
  draw(g, w, h);
  const tex = new THREE.CanvasTexture(c);
  if (srgb) tex.colorSpace = THREE.SRGBColorSpace;
  tex.anisotropy = 1;
  tex.needsUpdate = true;
  return tex;
}

export const radialTex = (inner = "rgba(255,255,255,1)", outer = "rgba(255,255,255,0)", size = 256) =>
  canvasTex(size, size, (g, w) => {
    const gr = g.createRadialGradient(w / 2, w / 2, 0, w / 2, w / 2, w / 2);
    gr.addColorStop(0, inner);
    gr.addColorStop(0.35, inner.replace(/[\d.]+\)$/, "0.45)"));
    gr.addColorStop(1, outer);
    g.fillStyle = gr; g.fillRect(0, 0, w, w);
  });

/** A glowing glyph (Persian or Latin text / symbol) on a transparent canvas. */
export function glyphTex(text, { font = "800 160px Vazirmatn", color = "#ffe9a8", glow = "rgba(255,200,80,0.9)", pad = 60, rtl = true } = {}) {
  const probe = document.createElement("canvas").getContext("2d");
  probe.font = font;
  const m = probe.measureText(text);
  const fs = parseInt(font.match(/(\d+)px/)[1], 10);
  const w = Math.ceil(m.width + pad * 2), h = Math.ceil(fs * 1.6 + pad * 2);
  const tex = canvasTex(w, h, (g) => {
    g.font = font; g.textAlign = "center"; g.textBaseline = "middle";
    g.direction = rtl ? "rtl" : "ltr";
    g.shadowColor = glow; g.shadowBlur = pad * 0.6;
    g.fillStyle = color;
    g.fillText(text, w / 2, h / 2);
    g.shadowBlur = 0; g.fillText(text, w / 2, h / 2);
  });
  tex.userData = { aspect: w / h };
  return tex;
}

export function glyphSprite(text, height, opts = {}) {
  const tex = glyphTex(text, opts);
  const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false, blending: opts.additive === false ? THREE.NormalBlending : THREE.AdditiveBlending, toneMapped: false });
  const s = new THREE.Sprite(mat);
  s.scale.set(height * tex.userData.aspect, height, 1);
  s.userData.baseScale = s.scale.clone();
  return s;
}

/** Midnight-blue nebula sky drawn once into an equirect canvas. */
export function nebulaTex() {
  return canvasTex(4096, 2048, (g, w, h) => {
    const bg = g.createLinearGradient(0, 0, 0, h);
    bg.addColorStop(0, "#050a1c"); bg.addColorStop(0.45, "#0b1a42"); bg.addColorStop(0.62, "#10265e"); bg.addColorStop(1, "#060c22");
    g.fillStyle = bg; g.fillRect(0, 0, w, h);
    const blobs = [["rgba(46,98,230,", 0.5], ["rgba(120,80,220,", 0.35], ["rgba(40,190,230,", 0.22], ["rgba(240,180,70,", 0.12], ["rgba(90,60,200,", 0.3]];
    for (let i = 0; i < 70; i++) {
      const [c, a] = blobs[i % blobs.length];
      const x = prand(i * 3.7) * w, y = h * (0.25 + prand(i * 5.1) * 0.55), r = 120 + prand(i * 9.3) * 520;
      const gr = g.createRadialGradient(x, y, 0, x, y, r);
      gr.addColorStop(0, c + (a * (0.35 + prand(i) * 0.65)).toFixed(3) + ")"); gr.addColorStop(1, c + "0)");
      g.fillStyle = gr; g.beginPath(); g.ellipse(x, y, r * 1.6, r, prand(i * 2.2) * 3, 0, Math.PI * 2); g.fill();
      if (x < r * 1.6) { g.save(); g.translate(w, 0); g.fill(); g.restore(); }
    }
    for (let i = 0; i < 2600; i++) {
      const x = prand(i * 1.31) * w, y = prand(i * 2.71) * h, s = prand(i * 4.1);
      g.fillStyle = s > 0.97 ? "rgba(255,236,180,0.95)" : `rgba(220,232,255,${(0.25 + s * 0.6).toFixed(2)})`;
      g.fillRect(x, y, s > 0.9 ? 3 : 2, s > 0.9 ? 3 : 2);
    }
  });
}

/* ------------------------------------------------------------- materials */
/** MeshStandardMaterial that silently drops physical-only options (fast under software GL). */
export function std(o) { const { clearcoat, clearcoatRoughness, sheen, sheenColor, transmission, envMap, envMapIntensity, ...r } = o; return new THREE.MeshStandardMaterial(r); }
export const M = {};
export function initMaterials(envMap) {
  const premium = (o) => { const { sheen, sheenColor, ...r } = o; return new THREE.MeshPhysicalMaterial({ envMap, envMapIntensity: 0.55, ...r }); };
  const phys = (o) => std({ envMap, envMapIntensity: 0.55, ...o });
  M.moon = premium({ color: 0xffbe2e, roughness: 0.36, clearcoat: 0.7, clearcoatRoughness: 0.25, sheen: 0.25, sheenColor: new THREE.Color(0xfff2c0), emissive: 0xb86a00, emissiveIntensity: 0.12 });
  M.star = premium({ color: 0xffd52e, roughness: 0.3, clearcoat: 0.8, clearcoatRoughness: 0.2, sheen: 0.2, sheenColor: new THREE.Color(0xfff6c8), emissive: 0xff9a00, emissiveIntensity: 0.22 });
  M.eye = premium({ color: 0x2a1406, roughness: 0.12, clearcoat: 1, envMapIntensity: 1.3 });
  M.eyeHL = new THREE.MeshBasicMaterial({ color: 0xffffff, toneMapped: false });
  M.mouth = phys({ color: 0x6b1b26, roughness: 0.4 });
  M.tongue = phys({ color: 0xff7d8f, roughness: 0.45 });
  M.cheek = new THREE.MeshBasicMaterial({ map: radialTex("rgba(255,120,130,0.85)", "rgba(255,120,130,0)"), transparent: true, depthWrite: false, opacity: 0.7 });
  M.navyGloss = phys({ color: 0x0f1d44, roughness: 0.3, metalness: 0.2, clearcoat: 1, clearcoatRoughness: 0.2, envMapIntensity: 0.3 });
  M.gold = phys({ color: 0xf5b53f, roughness: 0.22, metalness: 0.9, envMapIntensity: 1.4, emissive: 0x5a3300, emissiveIntensity: 0.25 });
  M.goldGlow = new THREE.MeshBasicMaterial({ color: new THREE.Color(1.0, 0.72, 0.25).multiplyScalar(2.6), toneMapped: false });
  M.cyanGlow = new THREE.MeshBasicMaterial({ color: new THREE.Color(0.25, 0.85, 1.0).multiplyScalar(2.2), toneMapped: false });
  M.violetGlow = new THREE.MeshBasicMaterial({ color: new THREE.Color(0.6, 0.45, 1.0).multiplyScalar(2.2), toneMapped: false });
  M.paper = phys({ color: 0xd9cba6, roughness: 0.95, side: THREE.DoubleSide, sheen: 0.1, envMapIntensity: 0.25 });
  M.cover = phys({ color: 0x1f4a8c, roughness: 0.45, clearcoat: 0.5, clearcoatRoughness: 0.4 });
  M.skin = phys({ color: 0xf2c4a0, roughness: 0.55, sheen: 0.4, sheenColor: new THREE.Color(0xffd8c0) });
  M.hair = phys({ color: 0x1b2340, roughness: 0.5, clearcoat: 0.4 });
  M.hairBrown = phys({ color: 0x4a2c1d, roughness: 0.5, clearcoat: 0.4 });
  M.pants = phys({ color: 0x1d2b55, roughness: 0.7 });
  M.shoe = phys({ color: 0xd8deef, roughness: 0.65 });
  M.glass = phys({ color: 0xbfe8ff, roughness: 0.05, transparent: true, opacity: 0.18, clearcoat: 1, depthWrite: false, envMapIntensity: 1.6, side: THREE.DoubleSide });
  M.rock = phys({ color: 0x2b2a5e, roughness: 0.85, flatShading: true });
  M.rock2 = phys({ color: 0x3b2f6e, roughness: 0.9, flatShading: true });
  return M;
}

export const glowMat = (r, g, b, k = 2.2) => new THREE.MeshBasicMaterial({ color: new THREE.Color(r, g, b).multiplyScalar(k), toneMapped: false });
export const softGlowSprite = (color = "rgba(255,210,110,1)", size = 1) => {
  const s = new THREE.Sprite(new THREE.SpriteMaterial({ map: radialTex(color, color.replace(/[\d.]+\)$/, "0)")), transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, toneMapped: false }));
  s.scale.set(size, size, 1);
  return s;
};

/* ------------------------------------------------------------ shapes */
function crescentShape(R1 = 1, c2 = [0.42, 0.2], R2 = 0.78, seg = 96) {
  const pts = [];
  // outer circle points that are outside the inner (cut) circle
  const outside = (x, y) => Math.hypot(x - c2[0], y - c2[1]) > R2;
  const inside1 = (x, y) => Math.hypot(x, y) < R1;
  // find contiguous outer arc: start at angle opposite to the cut and walk both ways
  const base = Math.atan2(-c2[1], -c2[0]);
  let a0 = base, a1 = base;
  const step = (Math.PI * 2) / (seg * 8);
  while (outside(Math.cos(a0 - step) * R1, Math.sin(a0 - step) * R1)) a0 -= step;
  while (outside(Math.cos(a1 + step) * R1, Math.sin(a1 + step) * R1)) a1 += step;
  for (let i = 0; i <= seg; i++) { const a = lerp(a0, a1, i / seg); pts.push(new THREE.Vector2(Math.cos(a) * R1, Math.sin(a) * R1)); }
  // inner arc back (points of the cut circle that lie inside the outer circle), from a1-end to a0-end
  const p1 = pts[pts.length - 1], p0 = pts[0];
  let b1 = Math.atan2(p1.y - c2[1], p1.x - c2[0]);
  let b0 = Math.atan2(p0.y - c2[1], p0.x - c2[0]);
  const mid = Math.atan2(-c2[1], -c2[0]);
  // choose direction passing through `mid`
  const norm = (a) => ((a % (2 * Math.PI)) + 2 * Math.PI) % (2 * Math.PI);
  let dir = 1;
  const span = norm(b0 - b1);
  if (!(norm(mid - b1) < span)) dir = -1;
  const total = dir === 1 ? span : -norm(b1 - b0);
  for (let i = 1; i < seg; i++) {
    const a = b1 + (total * i) / seg;
    const x = c2[0] + Math.cos(a) * R2, y = c2[1] + Math.sin(a) * R2;
    if (inside1(x * 0.999, y * 0.999)) pts.push(new THREE.Vector2(x, y));
  }
  return new THREE.Shape(pts);
}

export function starShape(R = 1, r = 0.52, round = 0.2) {
  const v = [];
  for (let i = 0; i < 10; i++) {
    const a = Math.PI / 2 + (i * Math.PI) / 5;
    const rr = i % 2 === 0 ? R : r;
    v.push(new THREE.Vector2(Math.cos(a) * rr, Math.sin(a) * rr));
  }
  const s = new THREE.Shape();
  for (let i = 0; i < 10; i++) {
    const P = v[i], A = v[(i + 9) % 10], B = v[(i + 1) % 10];
    const k = i % 2 === 0 ? round : round * 0.6;
    const p1 = P.clone().lerp(A, k), p2 = P.clone().lerp(B, k);
    if (i === 0) s.moveTo(p1.x, p1.y); else s.lineTo(p1.x, p1.y);
    s.quadraticCurveTo(P.x, P.y, p2.x, p2.y);
  }
  s.closePath();
  return s;
}

function arcTube(w, h, r, up = true) {
  const curve = new THREE.QuadraticBezierCurve3(V(-w, 0, 0), V(0, up ? h : -h, 0), V(w, 0, 0));
  return new THREE.TubeGeometry(curve, 16, r, 8, false);
}

/* ------------------------------------------------------------ face rig */
function makeFace({ eyes, mouth, cheeks, rot = 0, s = 1, z = 0 }) {
  const face = new THREE.Group();
  face.position.z = z;
  face.rotation.z = rot;
  const eyeGeo = new THREE.SphereGeometry(1, 24, 16);
  const hlGeo = new THREE.SphereGeometry(1, 12, 8);
  const closedGeo = arcTube(0.06 * s, 0.05 * s, 0.014 * s, true);
  const eyeObjs = eyes.map(([x, y]) => {
    const g = new THREE.Group();
    g.position.set(x, y, 0);
    const open = new THREE.Group();
    const ball = new THREE.Mesh(eyeGeo, M.eye);
    ball.scale.set(0.058 * s, 0.078 * s, 0.035 * s);
    const hl = new THREE.Mesh(hlGeo, M.eyeHL);
    hl.scale.setScalar(0.018 * s);
    hl.position.set(0.018 * s, 0.03 * s, 0.03 * s);
    const hl2 = new THREE.Mesh(hlGeo, M.eyeHL);
    hl2.scale.setScalar(0.008 * s);
    hl2.position.set(-0.016 * s, -0.022 * s, 0.032 * s);
    open.add(ball, hl, hl2);
    const closed = new THREE.Mesh(closedGeo, M.eye);
    closed.position.set(0, -0.012 * s, 0.012 * s);
    g.add(open, closed);
    face.add(g);
    return { g, open, closed };
  });
  // mouth: open smile (half ellipse) + tongue; closed smile curve
  const mw = 0.1 * s, mh = 0.085 * s;
  const ms = new THREE.Shape();
  ms.moveTo(-mw, 0); ms.lineTo(mw, 0);
  ms.absellipse(0, 0, mw, mh, 0, Math.PI, true);
  const mouthOpen = new THREE.Group();
  const mo = new THREE.Mesh(new THREE.ShapeGeometry(ms, 24), M.mouth);
  const tongue = new THREE.Mesh(new THREE.CircleGeometry(1, 24), M.tongue);
  tongue.scale.set(mw * 0.55, mh * 0.42, 1);
  tongue.position.set(0, -mh * 0.62, 0.002);
  mouthOpen.add(mo, tongue);
  mouthOpen.position.set(mouth[0], mouth[1], 0.004);
  const mouthClosed = new THREE.Mesh(arcTube(0.07 * s, 0.05 * s, 0.012 * s, false), M.mouth);
  mouthClosed.position.set(mouth[0], mouth[1], 0.006);
  face.add(mouthOpen, mouthClosed);
  const cheekObjs = (cheeks || []).map(([x, y]) => {
    const c = new THREE.Mesh(new THREE.PlaneGeometry(0.17 * s, 0.11 * s), M.cheek);
    c.position.set(x, y, 0.003);
    face.add(c);
    return c;
  });
  face.userData.set = ({ blinkL = 0, blinkR = 0, mouthOpen: mopen = 0.6, look = [0, 0] }) => {
    [blinkL, blinkR].forEach((b, i) => {
      const e = eyeObjs[i];
      if (!e) return;
      e.open.scale.y = Math.max(0.05, 1 - b);
      e.open.visible = b < 0.92;
      e.closed.visible = b >= 0.92;
      e.open.position.set(look[0] * 0.012 * s, look[1] * 0.012 * s, 0);
    });
    mouthOpen.visible = mopen > 0.06;
    mouthClosed.visible = mopen <= 0.06;
    mouthOpen.scale.set(0.85 + mopen * 0.15, Math.max(0.1, mopen), 1);
  };
  face.userData.set({});
  return face;
}

/* ---------------------------------------------------------- characters */
export function makeMoon() {
  const root = new THREE.Group();
  const squash = new THREE.Group();
  root.add(squash);
  const shape = crescentShape();
  const depth = 0.3, bevel = 0.13;
  const geo = new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: true, bevelThickness: bevel, bevelSize: 0.1, bevelSegments: 8, curveSegments: 64, steps: 1 });
  geo.translate(0, 0, -depth / 2);
  geo.computeVertexNormals();
  const body = new THREE.Mesh(geo, M.moon);
  squash.add(body);
  const front = depth / 2 + bevel + 0.004;
  // upright face on the thick part of the crescent (eyes kept clear of the inner cut)
  const face = makeFace({
    eyes: [[-0.46, 0.1], [-0.76, 0.12]], mouth: [-0.6, -0.14], cheeks: [[-0.36, -0.08], [-0.86, -0.05]],
    s: 1.55, z: front,
  });
  squash.add(face);
  // soft inner glow
  const halo = softGlowSprite("rgba(255,180,60,0.2)", 3.8);
  halo.position.z = -0.4;
  root.add(halo);
  return { root, squash, body, face, halo, kind: "moon" };
}

export function makeStar() {
  const root = new THREE.Group();
  const squash = new THREE.Group();
  root.add(squash);
  const depth = 0.26, bevel = 0.2;
  const geo = new THREE.ExtrudeGeometry(starShape(1, 0.54, 0.26), { depth, bevelEnabled: true, bevelThickness: bevel, bevelSize: 0.13, bevelSegments: 8, curveSegments: 24 });
  geo.translate(0, 0, -depth / 2);
  geo.computeVertexNormals();
  const body = new THREE.Mesh(geo, M.star);
  squash.add(body);
  const face = makeFace({ eyes: [[0.22, 0.12], [-0.22, 0.12]], mouth: [0, -0.12], cheeks: [[0.4, -0.1], [-0.4, -0.1]], s: 1.9, z: depth / 2 + bevel + 0.004 });
  squash.add(face);
  const halo = softGlowSprite("rgba(255,200,90,0.3)", 2.8);
  halo.position.z = -0.3;
  root.add(halo);
  return { root, squash, body, face, halo, kind: "star" };
}

/** Pose helper: {p, r, s, sq (y-stretch), blinkL, blinkR, mouth, look, glow} */
export function pose(ch, o) {
  if (o.p) ch.root.position.copy(o.p);
  if (o.r) ch.root.rotation.set(o.r[0], o.r[1], o.r[2]);
  const s = o.s ?? 1;
  ch.root.scale.setScalar(s);
  const sq = o.sq ?? 1;
  ch.squash.scale.set(1 / Math.sqrt(sq), sq, 1 / Math.sqrt(sq));
  ch.face.userData.set({ blinkL: o.blinkL ?? o.blink ?? 0, blinkR: o.blinkR ?? o.blink ?? 0, mouthOpen: o.mouth ?? 0.6, look: o.look || [0, 0] });
  ch.halo.material.opacity = o.glow ?? 1;
  ch.root.visible = o.visible ?? true;
}

/* ----------------------------------------------------------------- child */
export function makeChild({ hoodie = 0xf4b52c, hair = "dark", girl = false } = {}) {
  const root = new THREE.Group();
  const hoodMat = std({ color: hoodie, roughness: 0.75, sheen: 0.6, sheenColor: new THREE.Color(0xffffff), envMap: M.moon.envMap, envMapIntensity: 0.6 });
  const hairMat = hair === "dark" ? M.hair : M.hairBrown;
  const body = new THREE.Mesh(new THREE.CapsuleGeometry(0.34, 0.42, 8, 24), hoodMat);
  body.position.y = 0.98;
  body.scale.set(1, 1, 0.82);
  const legGeo = new THREE.CapsuleGeometry(0.11, 0.38, 6, 12);
  const legL = new THREE.Mesh(legGeo, M.pants); legL.position.set(0.14, 0.42, 0);
  const legR = legL.clone(); legR.position.x = -0.14;
  const shoeGeo = new RoundedBoxGeometry(0.22, 0.12, 0.32, 3, 0.05);
  const shoeL = new THREE.Mesh(shoeGeo, M.shoe); shoeL.position.set(0.14, 0.08, 0.05);
  const shoeR = shoeL.clone(); shoeR.position.x = -0.14;
  const headPivot = new THREE.Group(); headPivot.position.y = 1.55;
  const head = new THREE.Mesh(new THREE.SphereGeometry(0.33, 40, 28), M.skin);
  head.position.y = 0.2; head.scale.set(1, 0.96, 0.95);
  const hairCap = new THREE.Mesh(new THREE.SphereGeometry(0.352, 40, 24, 0, Math.PI * 2, 0, Math.PI * 0.55), hairMat);
  hairCap.position.y = 0.24; hairCap.rotation.x = -0.32;
  headPivot.add(head, hairCap);
  for (let i = 0; i < 5; i++) {
    const tuft = new THREE.Mesh(new THREE.SphereGeometry(0.1, 16, 12), hairMat);
    tuft.position.set(-0.2 + i * 0.1, 0.43 - Math.abs(i - 2) * 0.02, 0.2);
    tuft.scale.set(1, 0.7, 0.8);
    headPivot.add(tuft);
  }
  if (girl) {
    [-1, 1].forEach((sd) => {
      const puff = new THREE.Mesh(new THREE.SphereGeometry(0.15, 20, 14), hairMat);
      puff.position.set(sd * 0.36, 0.4, -0.05);
      headPivot.add(puff);
    });
  }
  // face
  const eyeG = new THREE.SphereGeometry(1, 16, 12);
  const eyes = [-1, 1].map((sd) => {
    const e = new THREE.Mesh(eyeG, M.eye); e.scale.set(0.038, 0.05, 0.025); e.position.set(sd * 0.12, 0.22, 0.3);
    const hl = new THREE.Mesh(eyeG, M.eyeHL); hl.scale.setScalar(0.012); hl.position.set(sd * 0.12 + 0.012, 0.24, 0.322);
    headPivot.add(e, hl); return e;
  });
  const smile = new THREE.Mesh(arcTube(0.07, 0.045, 0.012, false), M.mouth);
  smile.position.set(0, 0.1, 0.31);
  headPivot.add(smile);
  [-1, 1].forEach((sd) => {
    const c = new THREE.Mesh(new THREE.PlaneGeometry(0.12, 0.08), M.cheek);
    c.position.set(sd * 0.2, 0.13, 0.29); c.rotation.y = sd * 0.5; headPivot.add(c);
  });
  // arms (pivot at shoulders)
  const armGeo = new THREE.CapsuleGeometry(0.085, 0.36, 6, 12);
  const mkArm = (sd) => {
    const piv = new THREE.Group(); piv.position.set(sd * 0.4, 1.28, 0);
    const a = new THREE.Mesh(armGeo, hoodMat); a.position.y = -0.24;
    const hand = new THREE.Mesh(new THREE.SphereGeometry(0.095, 16, 12), M.skin); hand.position.y = -0.48;
    piv.add(a, hand); piv.rotation.z = sd * 0.15; return piv;
  };
  const armL = mkArm(1), armR = mkArm(-1);
  // backpack with a star
  const pack = new THREE.Mesh(new RoundedBoxGeometry(0.5, 0.56, 0.24, 4, 0.08), M.navyGloss);
  pack.position.set(0, 1.02, -0.34);
  const packStar = new THREE.Mesh(new THREE.ExtrudeGeometry(starShape(0.11, 0.055, 0.2), { depth: 0.02, bevelEnabled: false }), M.star);
  packStar.position.set(0, 1.04, -0.47); packStar.rotation.y = Math.PI;
  root.add(body, legL, legR, shoeL, shoeR, headPivot, armL, armR, pack, packStar);
  return { root, headPivot, armL, armR, eyes, smile };
}

/* ----------------------------------------------------------------- props */
export function makeBadge() {
  const g = new THREE.Group();
  const disc = new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.34, 0.07, 48), M.gold);
  disc.rotation.x = Math.PI / 2;
  const rim = new THREE.Mesh(new THREE.TorusGeometry(0.34, 0.03, 12, 48), M.goldGlow);
  const st = new THREE.Mesh(new THREE.ExtrudeGeometry(starShape(0.2, 0.1, 0.22), { depth: 0.03, bevelEnabled: true, bevelThickness: 0.02, bevelSize: 0.015, bevelSegments: 3 }), M.star);
  st.position.z = 0.04;
  const rib1 = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.42, 0.02), new THREE.MeshStandardMaterial({ color: 0x3a8bff, roughness: 0.6 }));
  rib1.position.set(0.09, 0.42, -0.03); rib1.rotation.z = -0.35;
  const rib2 = rib1.clone(); rib2.material = new THREE.MeshStandardMaterial({ color: 0xff5f8f, roughness: 0.6 });
  rib2.position.x = -0.09; rib2.rotation.z = 0.35;
  const glow = softGlowSprite("rgba(255,210,100,0.9)", 1.6);
  g.add(rib1, rib2, disc, rim, st, glow);
  return g;
}

export function makePencil(color = 0xffc23d) {
  const g = new THREE.Group();
  const body = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.8, 6), new THREE.MeshStandardMaterial({ color, roughness: 0.5 }));
  const wood = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.2, 6), new THREE.MeshStandardMaterial({ color: 0xf2d2a2, roughness: 0.7 }));
  wood.position.y = 0.5;
  const lead = new THREE.Mesh(new THREE.ConeGeometry(0.028, 0.07, 6), new THREE.MeshStandardMaterial({ color: 0x333344 }));
  lead.position.y = 0.585;
  const band = new THREE.Mesh(new THREE.CylinderGeometry(0.084, 0.084, 0.08, 12), M.gold);
  band.position.y = -0.42;
  const eraser = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.1, 12), new THREE.MeshStandardMaterial({ color: 0xff8fb1, roughness: 0.7 }));
  eraser.position.y = -0.5;
  g.add(body, wood, lead, band, eraser);
  return g;
}

export function makeMiniBook(color = 0x3a8bff) {
  const g = new THREE.Group();
  const cov = new THREE.MeshStandardMaterial({ color, roughness: 0.5 });
  const L = new THREE.Mesh(new RoundedBoxGeometry(0.5, 0.06, 0.7, 2, 0.02), cov); L.position.x = -0.25;
  const R = L.clone(); R.position.x = 0.25;
  const pL = new THREE.Mesh(new THREE.BoxGeometry(0.46, 0.05, 0.64), M.paper); pL.position.set(-0.24, 0.05, 0);
  const pR = pL.clone(); pR.position.x = 0.24;
  const left = new THREE.Group(); left.add(L, pL);
  const right = new THREE.Group(); right.add(R, pR);
  g.add(left, right);
  g.userData = { left, right };
  return g;
}

export function makeFlask(liquid = [1.0, 0.35, 0.6], shape = "erlen") {
  const g = new THREE.Group();
  const prof = shape === "erlen"
    ? [[0.02, 0], [0.42, 0], [0.45, 0.04], [0.16, 0.62], [0.13, 0.66], [0.13, 0.95], [0.16, 0.98]]
    : [[0.02, 0], [0.26, 0], [0.28, 0.04], [0.28, 0.92], [0.31, 0.96]];
  const glass = new THREE.Mesh(new THREE.LatheGeometry(prof.map(([x, y]) => new THREE.Vector2(x, y)), 40), M.glass);
  const lprof = shape === "erlen" ? [[0, 0.03], [0.4, 0.03], [0.24, 0.42], [0, 0.42]] : [[0, 0.03], [0.25, 0.03], [0.25, 0.55], [0, 0.55]];
  const liq = new THREE.Mesh(new THREE.LatheGeometry(lprof.map(([x, y]) => new THREE.Vector2(x, y)), 40), glowMat(...liquid, 0.9));
  const glow = softGlowSprite(`rgba(${Math.round(liquid[0] * 255)},${Math.round(liquid[1] * 255)},${Math.round(liquid[2] * 255)},0.8)`, 1.4);
  glow.position.y = 0.3;
  g.add(liq, glass, glow);
  g.userData.liq = liq;
  return g;
}

export function makeMolecule() {
  const g = new THREE.Group();
  const cols = [0xff6b9d, 0x3ad0ff, 0xffd87a, 0x8b7cf6, 0x2dd4bf];
  const nodes = [[0, 0, 0], [0.55, 0.3, 0], [-0.5, 0.35, 0.2], [0.1, -0.55, 0.25], [0.2, 0.2, -0.55], [-0.45, -0.35, -0.3]];
  nodes.forEach((p, i) => {
    const s = new THREE.Mesh(new THREE.SphereGeometry(i === 0 ? 0.2 : 0.13, 24, 16), std({ color: cols[i % cols.length], roughness: 0.2, clearcoat: 1, emissive: cols[i % cols.length], emissiveIntensity: 0.25, envMap: M.moon.envMap }));
    s.position.set(...p);
    g.add(s);
    if (i > 0) {
      const a = V(0, 0, 0), b = V(...p);
      const len = a.distanceTo(b);
      const c = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, len, 8), new THREE.MeshStandardMaterial({ color: 0xe8eeff, roughness: 0.4 }));
      c.position.copy(b.clone().multiplyScalar(0.5));
      c.quaternion.setFromUnitVectors(V(0, 1, 0), b.clone().normalize());
      g.add(c);
    }
  });
  return g;
}

export function makeIsland(radius, topColor, seed, { rim = [0.3, 0.8, 1] } = {}) {
  const g = new THREE.Group();
  const top = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius * 0.96, 0.36, 64, 1), std({ color: topColor, roughness: 0.75, sheen: 0.15, sheenColor: new THREE.Color(0xffffff), envMap: M.moon.envMap, envMapIntensity: 0.5 }));
  const coneGeo = new THREE.ConeGeometry(radius * 0.96, radius * 1.5, 18, 6, true);
  const pos = coneGeo.attributes.position;
  for (let i = 0; i < pos.count; i++) {
    const y = pos.getY(i);
    const k = 1 + (prand(seed + i * 1.7) - 0.5) * 0.3 * (1 - Math.abs(y) / (radius * 0.75));
    pos.setX(i, pos.getX(i) * k); pos.setZ(i, pos.getZ(i) * k);
  }
  coneGeo.computeVertexNormals();
  const under = new THREE.Mesh(coneGeo, M.rock);
  under.rotation.x = Math.PI;
  under.position.y = -radius * 0.75 - 0.18;
  const ring = new THREE.Mesh(new THREE.TorusGeometry(radius * 1.0, 0.035, 8, 96), glowMat(...rim, 2));
  ring.rotation.x = Math.PI / 2; ring.position.y = 0.18;
  // floating crystals beneath
  for (let i = 0; i < 4; i++) {
    const c = new THREE.Mesh(new THREE.OctahedronGeometry(0.18 + prand(seed + i) * 0.15, 0), glowMat(...rim, 1.4));
    const a = (i / 4) * Math.PI * 2 + seed;
    c.position.set(Math.cos(a) * radius * 0.55, -radius * 1.15 - prand(seed * 3 + i) * 0.8, Math.sin(a) * radius * 0.55);
    c.scale.y = 1.6;
    g.add(c);
  }
  g.add(top, under, ring);
  return g;
}

/** Bendable page: lies on the left half (x ∈ [-W,0]); flip(θ) turns it to the right with a soft curl. */
export function makePage(W, D, tex) {
  const geo = new THREE.PlaneGeometry(W, D, 28, 1);
  geo.rotateX(-Math.PI / 2);
  geo.translate(-W / 2, 0, 0);
  const base = geo.attributes.position.array.slice();
  const mat = M.paper.clone();
  if (tex) mat.map = tex;
  const mesh = new THREE.Mesh(geo, mat);
  mesh.userData.flip = (theta, lift = 0.0) => {
    const p = geo.attributes.position;
    for (let i = 0; i < p.count; i++) {
      const x0 = base[i * 3], z0 = base[i * 3 + 2];
      const r = Math.max(0, -x0); // distance from spine
      const phi = clamp(theta - 0.55 * Math.sin(theta) * Math.pow(r / W, 1.4), 0, Math.PI);
      p.setXYZ(i, -Math.cos(phi) * r, Math.sin(phi) * r + lift + 0.0015 * r, z0);
    }
    p.needsUpdate = true;
    geo.computeVertexNormals();
  };
  mesh.userData.flip(0);
  return mesh;
}

export function pageTexture(seed = 0) {
  return canvasTex(1024, 1400, (g, w, h) => {
    g.fillStyle = "#f8f1dc"; g.fillRect(0, 0, w, h);
    const vg = g.createRadialGradient(w / 2, h / 2, h * 0.2, w / 2, h / 2, h * 0.75);
    vg.addColorStop(0, "rgba(0,0,0,0)"); vg.addColorStop(1, "rgba(120,80,20,0.18)");
    g.fillStyle = vg; g.fillRect(0, 0, w, h);
    g.strokeStyle = "rgba(60,80,140,0.18)"; g.lineWidth = 3;
    for (let y = 160; y < h - 80; y += 62) { g.beginPath(); g.moveTo(70, y); g.lineTo(w - 70, y); g.stroke(); }
    const items = seed % 2 === 0 ? ["E = mc²", "a² + b² = c²", "π", "۲ + ۳ = ۵"] : ["√۹ = ۳", "%", "H₂O", "۷ × ۸ = ۵۶"];
    g.fillStyle = "rgba(40,70,150,0.75)";
    items.forEach((it, i) => {
      g.font = `700 ${i === 2 ? 120 : 64}px ${/[a-zA-Zπ√%]/.test(it) && !/[۰-۹]/.test(it) ? "Poppins" : "Vazirmatn"}`;
      g.direction = /[۰-۹]/.test(it) ? "rtl" : "ltr";
      g.textAlign = "center";
      g.fillText(it, w * (0.32 + (i % 2) * 0.38), 300 + i * 260);
    });
    g.strokeStyle = "rgba(220,150,40,0.6)"; g.lineWidth = 6;
    g.beginPath(); g.moveTo(240, 1220); g.lineTo(380, 1000); g.lineTo(520, 1220); g.closePath(); g.stroke();
    g.beginPath(); g.ellipse(760, 1110, 110, 40, 0.5, 0, Math.PI * 2); g.ellipse(760, 1110, 110, 40, -0.5, 0, Math.PI * 2); g.stroke();
  });
}

/** Seeded deterministic point cloud with a soft round sprite + twinkle in the shader. */
export function makeDust({ count, box, color = [1, 0.82, 0.45], size = 6, twinkle = 1, followCamera = true, seed = 1 }) {
  const geo = new THREE.BufferGeometry();
  const p = new Float32Array(count * 3), r = new Float32Array(count);
  for (let i = 0; i < count; i++) {
    p[i * 3] = prand(seed + i * 3.1) * box[0];
    p[i * 3 + 1] = prand(seed + i * 5.7) * box[1];
    p[i * 3 + 2] = prand(seed + i * 7.3) * box[2];
    r[i] = prand(seed + i * 9.9);
  }
  geo.setAttribute("position", new THREE.BufferAttribute(p, 3));
  geo.setAttribute("rnd", new THREE.BufferAttribute(r, 1));
  const mat = new THREE.ShaderMaterial({
    transparent: true, depthWrite: false, blending: THREE.AdditiveBlending,
    uniforms: { uTime: { value: 0 }, uCam: { value: V(0, 0, 0) }, uBox: { value: V(...box) }, uSize: { value: size }, uColor: { value: new THREE.Color(...color) }, uAlpha: { value: 1 }, uTw: { value: twinkle }, uFollow: { value: followCamera ? 1 : 0 }, uScale: { value: 1 } },
    vertexShader: `
      attribute float rnd; uniform float uTime, uSize, uFollow, uScale; uniform vec3 uCam, uBox; varying float vA;
      void main(){
        vec3 p = position + vec3(sin(uTime*0.13+rnd*40.0), cos(uTime*0.11+rnd*30.0)*0.6 + uTime*0.05, cos(uTime*0.09+rnd*50.0)) * 0.6;
        if (uFollow > 0.5) { p = mod(p - uCam + uBox*0.5, uBox) + uCam - uBox*0.5; }
        vec4 mv = modelViewMatrix * vec4(p, 1.0);
        gl_Position = projectionMatrix * mv;
        gl_PointSize = min(uSize * uScale * (0.4 + rnd) * (10.0 / max(0.5, -mv.z)), 26.0 * uScale);
        vA = 0.55 + 0.45 * sin(uTime * (1.5 + rnd * 2.5) + rnd * 60.0);
      }`,
    fragmentShader: `
      uniform vec3 uColor; uniform float uAlpha, uTw; varying float vA;
      void main(){ vec2 c = gl_PointCoord - 0.5; float d = length(c); float a = smoothstep(0.5, 0.0, d); a *= a;
        gl_FragColor = vec4(uColor * 1.6, a * uAlpha * mix(1.0, vA, uTw)); }`,
  });
  const pts = new THREE.Points(geo, mat);
  pts.frustumCulled = false;
  return pts;
}
export { RoundedBoxGeometry as RoundedBox };
