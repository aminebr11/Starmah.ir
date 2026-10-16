/**
 * صداهای ماهو — همه با Web Audio ساخته می‌شوند (فایلی دانلود نمی‌شود، حجم صفر).
 * تا اولین لمسِ کاربر، مرورگر اجازه‌ی پخش نمی‌دهد؛ بعد از آن خودکار باز می‌شود.
 * کاربر می‌تواند صدا را خاموش کند (در همین دستگاه می‌ماند).
 */
let ctx = null;
let master = null;
const KEY = 'mahoo-mute';

export function muted() {
    try { return localStorage.getItem(KEY) === '1'; } catch { return false; }
}
export function setMuted(v) {
    try { localStorage.setItem(KEY, v ? '1' : '0'); } catch { /* */ }
}

function ac() {
    if (typeof window === 'undefined') return null;
    if (!ctx) {
        const C = window.AudioContext || window.webkitAudioContext;
        if (!C) return null;
        try {
            ctx = new C();
            master = ctx.createGain();
            master.gain.value = 0.55;
            master.connect(ctx.destination);
        } catch { return null; }
    }
    if (ctx.state === 'suspended') ctx.resume().catch(() => {});
    return ctx;
}

/** با اولین لمس/کلید، صدا آماده شود (سیاستِ پخشِ خودکارِ مرورگرها). */
export function unlockSound() {
    if (typeof window === 'undefined') return;
    const go = () => { ac(); window.removeEventListener('pointerdown', go); window.removeEventListener('keydown', go); };
    window.addEventListener('pointerdown', go, { once: true, passive: true });
    window.addEventListener('keydown', go, { once: true });
}

function tone(t0, { f = 440, f2 = null, d = 0.15, type = 'sine', v = 0.5, a = 0.008, vib = 0 }) {
    const c = ctx; const o = c.createOscillator(); const g = c.createGain();
    o.type = type;
    o.frequency.setValueAtTime(f, t0);
    if (f2) o.frequency.exponentialRampToValueAtTime(Math.max(30, f2), t0 + d);
    if (vib) {
        const l = c.createOscillator(); const lg = c.createGain();
        l.frequency.value = vib; lg.gain.value = f * 0.06;
        l.connect(lg); lg.connect(o.frequency); l.start(t0); l.stop(t0 + d + 0.05);
    }
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(v, t0 + a);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + d);
    o.connect(g); g.connect(master);
    o.start(t0); o.stop(t0 + d + 0.05);
}

function noise(t0, { d = 0.3, v = 0.25, from = 400, to = 3000, q = 0.8 }) {
    const c = ctx; const len = Math.ceil(c.sampleRate * d);
    const buf = c.createBuffer(1, len, c.sampleRate); const ch = buf.getChannelData(0);
    for (let i = 0; i < len; i++) ch[i] = Math.random() * 2 - 1;
    const src = c.createBufferSource(); src.buffer = buf;
    const bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = q;
    bp.frequency.setValueAtTime(from, t0); bp.frequency.exponentialRampToValueAtTime(to, t0 + d);
    const g = c.createGain(); g.gain.setValueAtTime(0.0001, t0); g.gain.exponentialRampToValueAtTime(v, t0 + d * 0.3); g.gain.exponentialRampToValueAtTime(0.0001, t0 + d);
    src.connect(bp); bp.connect(g); g.connect(master); src.start(t0); src.stop(t0 + d + 0.02);
}

const SFX = {
    // لمس: «بویـنگ»ِ فنری
    boing: (t) => tone(t, { f: 520, f2: 140, d: 0.42, type: 'sine', v: 0.45, vib: 18 }),
    // قلقلک: خنده‌ی ریز (چند جیک‌جیکِ پشتِ هم)
    giggle: (t) => [0, 0.09, 0.18, 0.27, 0.36].forEach((k, i) => tone(t + k, { f: 900 + (i % 2 ? 220 : 0) + i * 40, f2: 700 + i * 30, d: 0.08, type: 'triangle', v: 0.32 })),
    pop: (t) => { tone(t, { f: 380, f2: 900, d: 0.09, type: 'sine', v: 0.45 }); },
    squeak: (t) => tone(t, { f: 1200, f2: 1900, d: 0.16, type: 'triangle', v: 0.28, vib: 30 }),
    // خوش‌آمد: زنگوله‌ی بالارونده
    chime: (t) => [523.3, 659.3, 784, 1046.5].forEach((f, i) => { tone(t + i * 0.11, { f, d: 0.55, type: 'sine', v: 0.3 }); tone(t + i * 0.11, { f: f * 2, d: 0.3, type: 'sine', v: 0.08 }); }),
    whoosh: (t) => noise(t, { d: 0.45, v: 0.22, from: 300, to: 2600 }),
    land: (t) => { tone(t, { f: 160, f2: 60, d: 0.22, type: 'sine', v: 0.6 }); noise(t, { d: 0.12, v: 0.12, from: 900, to: 300 }); },
    // موفقیت: «تا-دا» + برق
    tada: (t) => { [392, 523.3, 659.3].forEach((f, i) => tone(t + i * 0.08, { f, d: 0.32, type: 'triangle', v: 0.3 })); tone(t + 0.26, { f: 784, d: 0.6, type: 'triangle', v: 0.32 }); [1568, 2093, 2637].forEach((f, i) => tone(t + 0.3 + i * 0.06, { f, d: 0.25, v: 0.06 })); },
    dizzy: (t) => tone(t, { f: 700, f2: 250, d: 0.8, type: 'sine', v: 0.3, vib: 9 }),
    love: (t) => [659.3, 784].forEach((f, i) => tone(t + i * 0.16, { f, d: 0.35, type: 'sine', v: 0.32, vib: 6 })),
    msg: (t) => { tone(t, { f: 880, d: 0.12, type: 'sine', v: 0.22 }); tone(t + 0.08, { f: 1318.5, d: 0.2, type: 'sine', v: 0.18 }); },
    send: (t) => tone(t, { f: 600, f2: 1200, d: 0.12, type: 'sine', v: 0.2 }),
    // پایانِ تایمر: ملودیِ کوتاه و شاد
    alarm: (t) => [784, 659.3, 784, 1046.5, 784, 1046.5].forEach((f, i) => tone(t + i * 0.16, { f, d: 0.22, type: 'triangle', v: 0.32 })),
};

/** پخشِ یک صدا؛ اگر صدا خاموش است یا مرورگر اجازه نمی‌دهد، بی‌صدا می‌گذرد. */
export function play(name, { force = false } = {}) {
    if (!force && muted()) return;
    if (typeof window !== 'undefined' && window.__mahooSound === false) return;
    const c = ac();
    if (!c || !SFX[name]) return;
    try { SFX[name](c.currentTime + 0.01); } catch { /* */ }
}

/** لرزشِ کوتاه روی گوشی (اگر پشتیبانی شود). */
export function buzz(ms = 18) {
    try { if (!muted()) navigator.vibrate?.(ms); } catch { /* */ }
}
