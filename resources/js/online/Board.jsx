import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';
import '@/../css/online.css';

/**
 * تخته‌ی کلاسِ آنلاین.
 *
 * - مختصات «منطقی» است (۱۶۰۰×۹۰۰)، پس روی هر صفحه‌نمایشی همان‌جا می‌نشیند.
 * - سه لایه: زمینه (ساده/شطرنجی/خط‌دار/نقطه‌ای/گچی/اسلاید)، جوهر (خط‌ها و شکل‌های
 *   ثبت‌شده) و لایه‌ی زنده (خطی که همین حالا کشیده می‌شود + نشانگرِ لیزری).
 * - خطِ در حالِ کشیدن هر ۵۰ میلی‌ثانیه برای بقیه فرستاده می‌شود (onStroke)، پس
 *   بچه‌ها کشیده‌شدنِ خط را زنده می‌بینند؛ در پایان یک «op» کامل ثبت می‌شود.
 * - پاک‌کن «خط‌به‌خط» است (مناسبِ بچه‌ها و قابلِ برگشت).
 */

export const W = 1600;
export const H = 900;

export const COLORS = ['#1f2a44', '#e53935', '#fb8c00', '#fdd835', '#43a047', '#1e88e5', '#8e24aa', '#ffffff'];
export const STAMPS = ['⭐', '✅', '❌', '❤️', '👍', '😀', '🍎', '🌙', '🏆', '❓'];
const SIZES = { pen: [4, 8, 14], hl: [20, 32, 46], text: [34, 48, 70], stamp: [64, 96, 140], line: [4, 8, 14] };

const uid = () => Math.random().toString(36).slice(2, 9);

/** کدام opها دیده می‌شوند («پاک کردنِ همه» و «پاک‌کن» را اعمال می‌کند). */
function visible(ops) {
    let out = [];
    const removed = new Set();
    for (const op of ops) {
        if (op.k === 'clear') { out = []; continue; }
        if (op.k === 'erase') { (op.ids || []).forEach((i) => removed.add(i)); continue; }
        out.push(op);
    }
    return out.filter((o) => !removed.has(o.id));
}

function strokePath(ctx, p) {
    if (p.length < 2) return;
    ctx.beginPath();
    ctx.moveTo(p[0], p[1]);
    if (p.length === 2) { ctx.lineTo(p[0] + 0.1, p[1] + 0.1); ctx.stroke(); return; }
    for (let i = 2; i < p.length - 2; i += 2) {
        const mx = (p[i] + p[i + 2]) / 2; const my = (p[i + 1] + p[i + 3]) / 2;
        ctx.quadraticCurveTo(p[i], p[i + 1], mx, my);
    }
    ctx.lineTo(p[p.length - 2], p[p.length - 1]);
    ctx.stroke();
}

export function drawOp(ctx, op, faded = false) {
    const p = op.p || [];
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = op.c || '#1f2a44';
    ctx.fillStyle = op.c || '#1f2a44';
    ctx.lineWidth = op.w || 6;
    if (faded) ctx.globalAlpha = 0.25;
    switch (op.k) {
        case 'pen': strokePath(ctx, p); break;
        case 'hl':
            ctx.globalAlpha = faded ? 0.12 : 0.35;
            ctx.lineCap = 'square';
            strokePath(ctx, p);
            break;
        case 'line': case 'arrow': {
            if (p.length < 4) break;
            ctx.beginPath(); ctx.moveTo(p[0], p[1]); ctx.lineTo(p[2], p[3]); ctx.stroke();
            if (op.k === 'arrow') {
                const a = Math.atan2(p[3] - p[1], p[2] - p[0]); const L = 18 + op.w * 2.2;
                ctx.beginPath();
                ctx.moveTo(p[2], p[3]);
                ctx.lineTo(p[2] - L * Math.cos(a - 0.45), p[3] - L * Math.sin(a - 0.45));
                ctx.lineTo(p[2] - L * Math.cos(a + 0.45), p[3] - L * Math.sin(a + 0.45));
                ctx.closePath(); ctx.fill();
            }
            break;
        }
        case 'rect':
            if (p.length >= 4) { ctx.beginPath(); ctx.roundRect ? ctx.roundRect(Math.min(p[0], p[2]), Math.min(p[1], p[3]), Math.abs(p[2] - p[0]), Math.abs(p[3] - p[1]), 10) : ctx.rect(Math.min(p[0], p[2]), Math.min(p[1], p[3]), Math.abs(p[2] - p[0]), Math.abs(p[3] - p[1])); ctx.stroke(); }
            break;
        case 'ell':
            if (p.length >= 4) {
                ctx.beginPath();
                ctx.ellipse((p[0] + p[2]) / 2, (p[1] + p[3]) / 2, Math.abs(p[2] - p[0]) / 2 || 1, Math.abs(p[3] - p[1]) / 2 || 1, 0, 0, Math.PI * 2);
                ctx.stroke();
            }
            break;
        case 'text':
            ctx.font = `800 ${op.w || 40}px Vazirmatn, Tahoma, sans-serif`;
            ctx.textBaseline = 'top';
            ctx.direction = 'rtl';
            ctx.textAlign = 'right';
            String(op.s || '').split('\n').forEach((line, i) => ctx.fillText(line, p[0], p[1] + i * (op.w || 40) * 1.35));
            break;
        case 'stamp':
            ctx.font = `${op.w || 90}px "Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(op.s || '⭐', p[0], p[1]);
            break;
        default: break;
    }
    ctx.restore();
}

function hit(op, x, y) {
    const p = op.p || [];
    const r = Math.max(16, (op.w || 6) / 2 + 12);
    if (op.k === 'pen' || op.k === 'hl') {
        for (let i = 0; i < p.length; i += 2) if (Math.hypot(p[i] - x, p[i + 1] - y) < r) return true;
        return false;
    }
    if (op.k === 'text') {
        const len = String(op.s || '').length * (op.w || 40) * 0.6;
        return x <= p[0] + 10 && x >= p[0] - len && y >= p[1] - 10 && y <= p[1] + (op.w || 40) * 1.4;
    }
    if (op.k === 'stamp') return Math.hypot(p[0] - x, p[1] - y) < (op.w || 90) / 1.6;
    if (p.length >= 4) {
        if (op.k === 'line' || op.k === 'arrow') {
            const [x1, y1, x2, y2] = p; const dx = x2 - x1; const dy = y2 - y1; const l = dx * dx + dy * dy || 1;
            const t = Math.max(0, Math.min(1, ((x - x1) * dx + (y - y1) * dy) / l));
            return Math.hypot(x1 + t * dx - x, y1 + t * dy - y) < r;
        }
        const minX = Math.min(p[0], p[2]) - r; const maxX = Math.max(p[0], p[2]) + r;
        const minY = Math.min(p[1], p[3]) - r; const maxY = Math.max(p[1], p[3]) + r;
        const inner = x > minX + 2 * r && x < maxX - 2 * r && y > minY + 2 * r && y < maxY - 2 * r;
        return x >= minX && x <= maxX && y >= minY && y <= maxY && !inner;
    }
    return false;
}

const imgCache = new Map();
/** پیش‌بارگذاریِ تصویرِ صفحه‌های بعدیِ فایلِ درس (ورق‌زدنِ فوری). */
export function preloadImg(url) { if (url && !imgCache.has(url)) loadImg(url, () => {}); }
function loadImg(url, cb) {
    if (imgCache.has(url)) { const im = imgCache.get(url); if (im.complete) return im; im.addEventListener('load', cb, { once: true }); return null; }
    const im = new Image();
    im.decoding = 'async';
    im.addEventListener('load', cb, { once: true });
    im.src = url;
    imgCache.set(url, im);
    return null;
}

export function drawBg(ctx, bg, onLoad) {
    const chalk = bg === 'chalk';
    ctx.fillStyle = chalk ? '#1f4d3a' : '#ffffff';
    ctx.fillRect(0, 0, W, H);
    ctx.save();
    if (bg === 'grid' || bg === 'dots') {
        ctx.fillStyle = '#c9d6ee'; ctx.strokeStyle = '#e3eaf7'; ctx.lineWidth = 2;
        for (let x = 0; x <= W; x += 50) for (let y = 0; y <= H; y += 50) {
            if (bg === 'dots') { ctx.beginPath(); ctx.arc(x, y, 2.6, 0, Math.PI * 2); ctx.fill(); }
        }
        if (bg === 'grid') {
            for (let x = 0; x <= W; x += 50) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); }
            for (let y = 0; y <= H; y += 50) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
        }
    } else if (bg === 'lines') {
        // دفترِ خط‌دارِ املا: خط‌های آبی و حاشیه‌ی قرمز در سمتِ راست
        ctx.strokeStyle = '#bcd3f5'; ctx.lineWidth = 2.4;
        for (let y = 110; y < H; y += 70) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); }
        ctx.strokeStyle = '#f2a1a1'; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.moveTo(W - 110, 0); ctx.lineTo(W - 110, H); ctx.stroke();
    } else if (bg && bg !== 'blank' && bg !== 'chalk') {
        const im = loadImg(bg, onLoad);
        if (im && im.naturalWidth) {
            const s = Math.min(W / im.naturalWidth, H / im.naturalHeight);
            const w = im.naturalWidth * s; const h = im.naturalHeight * s;
            ctx.drawImage(im, (W - w) / 2, (H - h) / 2, w, h);
        } else {
            ctx.fillStyle = '#9aa7c2'; ctx.font = '700 34px Vazirmatn, Tahoma'; ctx.textAlign = 'center';
            ctx.fillText('در حالِ بارگذاریِ اسلاید…', W / 2, H / 2);
        }
    }
    ctx.restore();
}

/** نمای تخته (بزرگ‌نمایی): z از ۱ تا ۵، x و y گوشه‌ی بالای ناحیه‌ی دیده‌شده (به مختصاتِ تخته). */
export const ZMAX = 5;
export function clampView(v) {
    const z = Math.max(1, Math.min(ZMAX, Number(v?.z) || 1));
    const x = Math.max(0, Math.min(W - W / z, Number(v?.x) || 0));
    const y = Math.max(0, Math.min(H - H / z, Number(v?.y) || 0));
    return { z: Math.round(z * 1000) / 1000, x: Math.round(x), y: Math.round(y) };
}

const Board = forwardRef(function Board({ canDraw = false, isHost = false, me, onOp, onStroke, onPtr, onView, tools = true }, ref) {
    const wrapRef = useRef(null);
    const bgRef = useRef(null);
    const inkRef = useRef(null);
    const liveRef = useRef(null);
    const st = useRef({ ops: [], bg: 'blank', live: new Map(), ptr: null, cur: null, redo: [], erasing: new Set(), dirty: true, raf: 0, scale: 1, view: { z: 1, x: 0, y: 0 } });
    const [zoom, setZoom] = useState(1);
    const ptrs = useRef(new Map());   // انگشت‌های روی تخته (برای بزرگ‌نماییِ دوانگشتی)
    const pinch = useRef(null);
    const viewSent = useRef(0);
    const [tool, setTool] = useState('pen');
    const [color, setColor] = useState(COLORS[0]);
    const [size, setSize] = useState(1);
    const [stamp, setStamp] = useState(STAMPS[0]);
    const [textAt, setTextAt] = useState(null);
    const [text, setText] = useState('');
    const [showStamps, setShowStamps] = useState(false);

    const sizeFor = (k) => (SIZES[k === 'arrow' || k === 'rect' || k === 'ell' ? 'line' : k] || SIZES.pen)[size];

    // ── رسم ───────────────────────────────────────────────────────────
    /** تبدیلِ مختصاتِ تخته به بوم با در نظر گرفتنِ بزرگ‌نمایی. */
    const tf = (c) => { const s = st.current; const k = s.scale * s.view.z; c.setTransform(k, 0, 0, k, -s.view.x * k, -s.view.y * k); };
    const paintAll = () => {
        const s = st.current;
        const bgc = bgRef.current?.getContext('2d'); const ink = inkRef.current?.getContext('2d');
        if (!bgc || !ink) return;
        bgc.setTransform(1, 0, 0, 1, 0, 0); bgc.clearRect(0, 0, bgRef.current.width, bgRef.current.height);
        tf(bgc); drawBg(bgc, s.bg, () => { s.dirty = true; schedule(); });
        ink.setTransform(1, 0, 0, 1, 0, 0); ink.clearRect(0, 0, inkRef.current.width, inkRef.current.height);
        tf(ink);
        for (const op of visible(s.ops)) drawOp(ink, op, s.erasing.has(op.id));
        s.dirty = false;
    };
    const paintLive = () => {
        const s = st.current;
        const c = liveRef.current?.getContext('2d');
        if (!c) return;
        c.setTransform(1, 0, 0, 1, 0, 0); c.clearRect(0, 0, liveRef.current.width, liveRef.current.height);
        tf(c);
        for (const op of s.live.values()) drawOp(c, op);
        if (s.cur) drawOp(c, s.cur);
        if (s.ptr && Date.now() - s.ptr.at < 1500) {
            const g = c.createRadialGradient(s.ptr.x, s.ptr.y, 2, s.ptr.x, s.ptr.y, 26);
            g.addColorStop(0, 'rgba(255,40,40,1)'); g.addColorStop(0.35, 'rgba(255,40,40,.55)'); g.addColorStop(1, 'rgba(255,40,40,0)');
            c.fillStyle = g; c.beginPath(); c.arc(s.ptr.x, s.ptr.y, 26, 0, Math.PI * 2); c.fill();
        }
    };
    const schedule = () => {
        const s = st.current;
        if (s.raf) return;
        s.raf = requestAnimationFrame(() => {
            s.raf = 0;
            if (s.dirty) paintAll();
            paintLive();
            if (s.live.size || s.cur || (s.ptr && Date.now() - s.ptr.at < 1600)) schedule();
        });
    };

    // اندازه‌ی بوم با ظرف
    useEffect(() => {
        const el = wrapRef.current;
        if (!el) return undefined;
        const fit = () => {
            const r = el.getBoundingClientRect();
            const dpr = Math.min(2, window.devicePixelRatio || 1);
            const w = Math.max(1, Math.round(r.width * dpr)); const h = Math.max(1, Math.round(r.height * dpr));
            for (const c of [bgRef.current, inkRef.current, liveRef.current]) { if (c && (c.width !== w || c.height !== h)) { c.width = w; c.height = h; } }
            st.current.scale = w / W;
            st.current.dirty = true;
            schedule();
        };
        fit();
        const ro = new ResizeObserver(fit);
        ro.observe(el);
        return () => ro.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // ── رابطِ بیرونی ─────────────────────────────────────────────────
    useImperativeHandle(ref, () => ({
        load(ops, bg) { const s = st.current; s.ops = Array.isArray(ops) ? ops : []; s.bg = bg || 'blank'; s.live.clear(); s.redo = []; s.dirty = true; schedule(); },
        setBg(bg) { st.current.bg = bg || 'blank'; st.current.dirty = true; schedule(); },
        ops() { return st.current.ops; },
        bg() { return st.current.bg; },
        add(op) { const s = st.current; s.live.delete(op.id); if (!s.ops.some((o) => o.id === op.id)) s.ops.push(op); s.dirty = true; schedule(); },
        undo(id) { const s = st.current; s.ops = s.ops.filter((o) => o.id !== id); s.dirty = true; schedule(); },
        stroke(msg) {
            const s = st.current;
            const cur = s.live.get(msg.id) || { id: msg.id, k: msg.k, c: msg.c, w: msg.w, p: [] };
            cur.p = cur.p.concat(msg.p || []);
            s.live.set(msg.id, cur);
            schedule();
        },
        pointer(x, y) { st.current.ptr = x == null ? null : { x, y, at: Date.now() }; schedule(); },
        canvases() { return [bgRef.current, inkRef.current, liveRef.current]; },
        /** نمای دیگران (معلم یا دانش‌آموزِ مجاز) — همه همان بخش را می‌بینند. */
        setView(v) { if (pinch.current) return; applyView(v ? clampView(v) : { z: 1, x: 0, y: 0 }); },
        view() { return { ...st.current.view }; },
        /** کلِ صفحه (بدونِ بزرگ‌نمایی) به اندازه‌ی واقعی — برای ذخیره‌ی عکس. */
        snapshot() {
            const c = document.createElement('canvas'); c.width = W; c.height = H;
            const g = c.getContext('2d');
            g.fillStyle = '#fff'; g.fillRect(0, 0, W, H);
            drawBg(g, st.current.bg, () => {});
            for (const op of visible(st.current.ops)) drawOp(g, op, false);
            return c;
        },
    }));

    // ── بزرگ‌نمایی ───────────────────────────────────────────────────
    const applyView = (v) => {
        const s = st.current;
        if (v.z === s.view.z && v.x === s.view.x && v.y === s.view.y) return;
        s.view = v; s.dirty = true;
        setZoom(v.z);
        schedule();
    };
    const emitView = (final = false) => {
        const now = Date.now();
        if (!final && now - viewSent.current < 70) return;
        viewSent.current = now;
        onView?.({ ...st.current.view });
    };
    /** بزرگ‌نمایی حولِ یک نقطه‌ی صفحه (fx,fy بین ۰ و ۱). */
    const zoomAt = (z, fx = 0.5, fy = 0.5, final = true) => {
        const s = st.current;
        const bx = s.view.x + fx * (W / s.view.z); const by = s.view.y + fy * (H / s.view.z);
        const nz = Math.max(1, Math.min(ZMAX, z));
        applyView(clampView({ z: nz, x: bx - fx * (W / nz), y: by - fy * (H / nz) }));
        emitView(final);
    };
    // Ctrl + چرخِ ماوس / دو انگشت روی تاچ‌پد
    useEffect(() => {
        const el = liveRef.current;
        if (!el || !canDraw) return undefined;
        const onWheel = (e) => {
            if (!e.ctrlKey && !e.metaKey) return;
            e.preventDefault();
            const r = el.getBoundingClientRect();
            zoomAt(st.current.view.z * Math.exp(-e.deltaY * 0.01), (e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height, true);
        };
        el.addEventListener('wheel', onWheel, { passive: false });
        return () => el.removeEventListener('wheel', onWheel);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canDraw]);

    // ── کشیدن ────────────────────────────────────────────────────────
    const pos = (e) => {
        const r = liveRef.current.getBoundingClientRect();
        const v = st.current.view;
        return [Math.round(v.x + ((e.clientX - r.left) / r.width) * (W / v.z)), Math.round(v.y + ((e.clientY - r.top) / r.height) * (H / v.z))];
    };
    /** دو انگشت: کارِ نیمه‌تمام کنار می‌رود و بزرگ‌نمایی/جابه‌جاییِ تخته شروع می‌شود. */
    const startPinch = () => {
        const s = st.current;
        s.cur = null; s.erasing = new Set(); s.dirty = true;
        drag.current = null;
        const [a, b] = [...ptrs.current.values()];
        const r = liveRef.current.getBoundingClientRect();
        const mx = ((a.x + b.x) / 2 - r.left) / r.width; const my = ((a.y + b.y) / 2 - r.top) / r.height;
        pinch.current = { d0: Math.hypot(a.x - b.x, a.y - b.y) || 1, z0: s.view.z, bx: s.view.x + mx * (W / s.view.z), by: s.view.y + my * (H / s.view.z) };
        schedule();
    };
    const movePinch = () => {
        const p = pinch.current;
        const [a, b] = [...ptrs.current.values()];
        if (!p || !a || !b) return;
        const r = liveRef.current.getBoundingClientRect();
        const z = Math.max(1, Math.min(ZMAX, p.z0 * (Math.hypot(a.x - b.x, a.y - b.y) / p.d0)));
        const mx = ((a.x + b.x) / 2 - r.left) / r.width; const my = ((a.y + b.y) / 2 - r.top) / r.height;
        // همان نقطه‌ی تخته زیرِ وسطِ دو انگشت می‌ماند (هم بزرگ‌نمایی، هم جابه‌جایی)
        applyView(clampView({ z, x: p.bx - mx * (W / z), y: p.by - my * (H / z) }));
        emitView(false);
    };
    const commit = (op) => {
        const s = st.current;
        s.ops.push(op);
        s.redo = [];
        s.dirty = true;
        schedule();
        onOp?.(op);
    };
    const drag = useRef(null);

    const down = (e) => {
        if (!canDraw || e.button > 0) return;
        if (e.pointerType === 'touch') {
            ptrs.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (ptrs.current.size === 2) { liveRef.current.setPointerCapture?.(e.pointerId); startPinch(); return; }
            if (ptrs.current.size > 2 || pinch.current) return;
        }
        const [x, y] = pos(e);
        const s = st.current;
        if (tool === 'laser') { drag.current = { tool }; s.ptr = { x, y, at: Date.now() }; onPtr?.(x, y); schedule(); liveRef.current.setPointerCapture?.(e.pointerId); return; }
        if (tool === 'text') { setTextAt({ x, y }); setText(''); return; }
        if (tool === 'stamp') { commit({ id: `${me}-${uid()}`, k: 'stamp', s: stamp, p: [x, y], w: sizeFor('stamp'), by: me }); return; }
        liveRef.current.setPointerCapture?.(e.pointerId);
        if (tool === 'erase') { drag.current = { tool }; s.erasing = new Set(); eraseAt(x, y); return; }
        const k = tool;
        s.cur = { id: `${me}-${uid()}`, k, c: color, w: sizeFor(k), p: [x, y], by: me };
        drag.current = { tool, sent: 0, last: 0 };
        schedule();
    };
    const eraseAt = (x, y) => {
        const s = st.current;
        for (const op of visible(s.ops)) if (!s.erasing.has(op.id) && hit(op, x, y)) { s.erasing.add(op.id); s.dirty = true; }
        schedule();
    };
    const move = (e) => {
        if (ptrs.current.has(e.pointerId)) {
            ptrs.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pinch.current) { movePinch(); return; }
        }
        const d = drag.current;
        if (!d) return;
        const s = st.current;
        const evs = e.nativeEvent.getCoalescedEvents?.() || [e];
        for (const ev of evs) {
            const [x, y] = pos(ev);
            if (d.tool === 'laser') { s.ptr = { x, y, at: Date.now() }; continue; }
            if (d.tool === 'erase') { eraseAt(x, y); continue; }
            if (!s.cur) continue;
            if (s.cur.k === 'pen' || s.cur.k === 'hl') {
                const n = s.cur.p.length;
                if (Math.hypot(s.cur.p[n - 2] - x, s.cur.p[n - 1] - y) >= 3) s.cur.p.push(x, y);
            } else {
                s.cur.p = [s.cur.p[0], s.cur.p[1], x, y];
            }
        }
        if (d.tool === 'laser') { const now = Date.now(); if (now - (d.last || 0) > 60) { d.last = now; onPtr?.(s.ptr.x, s.ptr.y); } schedule(); return; }
        if (s.cur && Date.now() - d.last > 50) {
            d.last = Date.now();
            if (s.cur.k === 'pen' || s.cur.k === 'hl') {
                onStroke?.({ id: s.cur.id, k: s.cur.k, c: s.cur.c, w: s.cur.w, p: s.cur.p.slice(d.sent) });
                d.sent = s.cur.p.length;
            }
        }
        schedule();
    };
    const up = (e) => {
        if (e && ptrs.current.has(e.pointerId)) {
            ptrs.current.delete(e.pointerId);
            if (pinch.current) {
                if (ptrs.current.size < 2) { pinch.current = null; ptrs.current.clear(); emitView(true); }
                return;
            }
        }
        const d = drag.current;
        drag.current = null;
        if (!d) return;
        const s = st.current;
        if (d.tool === 'laser') { setTimeout(() => onPtr?.(null, null), 1200); return; }
        if (d.tool === 'erase') {
            const ids = [...s.erasing];
            s.erasing = new Set();
            s.dirty = true;
            if (ids.length) commit({ id: `${me}-${uid()}`, k: 'erase', ids, by: me });
            schedule();
            return;
        }
        const op = s.cur;
        s.cur = null;
        if (!op) return;
        if ((op.k !== 'pen' && op.k !== 'hl') && op.p.length >= 4 && Math.hypot(op.p[2] - op.p[0], op.p[3] - op.p[1]) < 6) { schedule(); return; }
        commit(op);
    };

    const commitText = () => {
        if (textAt && text.trim()) commit({ id: `${me}-${uid()}`, k: 'text', s: text.trim(), p: [textAt.x, textAt.y], c: color, w: sizeFor('text'), by: me });
        setTextAt(null);
        setText('');
    };

    const undo = () => {
        const s = st.current;
        for (let i = s.ops.length - 1; i >= 0; i -= 1) {
            if (s.ops[i].by === me) {
                const [op] = s.ops.splice(i, 1);
                s.redo.push(op);
                s.dirty = true; schedule();
                onOp?.({ k: '__undo', id: op.id });
                return;
            }
        }
    };
    const redo = () => {
        const s = st.current;
        const op = s.redo.pop();
        if (!op) return;
        s.ops.push(op); s.dirty = true; schedule();
        onOp?.(op);
    };
    const clear = () => {
        if (!visible(st.current.ops).length) return;
        commit({ id: `${me}-${uid()}`, k: 'clear', by: me });
    };

    // کلیدهای میان‌بر برای معلم روی رایانه
    useEffect(() => {
        if (!canDraw) return undefined;
        const onKey = (e) => {
            if (e.target.closest?.('input,textarea')) return;
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    const TOOLS = [
        ['pen', '✏️', 'مداد'], ['hl', '🖍️', 'ماژیکِ رنگی'], ['erase', '🧽', 'پاک‌کن'], ['line', '➖', 'خطِ صاف'],
        ['arrow', '↗️', 'فلش'], ['rect', '⬜', 'مستطیل'], ['ell', '⭕', 'دایره'], ['text', '🔤', 'نوشتن'], ['stamp', '⭐', 'برچسب'],
        ...(isHost ? [['laser', '🔴', 'نشانگرِ لیزری']] : []),
    ];

    return (
        <div className={`ob ${canDraw ? 'can-draw' : ''}`}>
            {canDraw && tools && (
                <div className="ob-tools" role="toolbar" aria-label="ابزارهای تخته">
                    {TOOLS.map(([k, ic, label]) => (
                        <button key={k} type="button" className={tool === k ? 'on' : ''} title={label} aria-label={label}
                            onClick={() => { setTool(k); setShowStamps(k === 'stamp' ? (v) => !v : false); }}>{k === 'stamp' ? stamp : ic}</button>
                    ))}
                    <span className="ob-sep" />
                    {COLORS.map((c) => (
                        <button key={c} type="button" className={`ob-color ${color === c ? 'on' : ''}`} style={{ '--c': c }} aria-label={`رنگ ${c}`} onClick={() => setColor(c)} />
                    ))}
                    <span className="ob-sep" />
                    {[0, 1, 2].map((i) => (
                        <button key={i} type="button" className={`ob-size ${size === i ? 'on' : ''}`} aria-label={['نازک', 'متوسط', 'کلفت'][i]} title={['نازک', 'متوسط', 'کلفت'][i]} onClick={() => setSize(i)}>
                            <i style={{ width: 6 + i * 5, height: 6 + i * 5, background: color === '#ffffff' ? '#cfd6e4' : color }} />
                        </button>
                    ))}
                    <span className="ob-sep" />
                    <button type="button" onClick={undo} title="برگرداندن (Ctrl+Z)" aria-label="برگرداندن">↶</button>
                    <button type="button" onClick={redo} title="دوباره" aria-label="دوباره">↷</button>
                    {isHost && <button type="button" onClick={clear} title="پاک کردنِ کلِ صفحه" aria-label="پاک کردنِ صفحه">🗑️</button>}
                    <span className="ob-sep" />
                    <button type="button" onClick={() => zoomAt(st.current.view.z * 1.5)} disabled={zoom >= ZMAX} title="بزرگ‌نمایی (برای همه) — روی گوشی با دو انگشت" aria-label="بزرگ‌نمایی">🔍</button>
                    <button type="button" onClick={() => zoomAt(st.current.view.z / 1.5)} disabled={zoom <= 1} title="کوچک‌نمایی" aria-label="کوچک‌نمایی">➖</button>
                    {showStamps && tool === 'stamp' && (
                        <div className="ob-stamps">
                            {STAMPS.map((s) => <button key={s} type="button" onClick={() => { setStamp(s); setShowStamps(false); }}>{s}</button>)}
                        </div>
                    )}
                </div>
            )}
            <div className="ob-stage" ref={wrapRef}>
                <canvas ref={bgRef} className="ob-c" />
                <canvas ref={inkRef} className="ob-c" />
                <canvas ref={liveRef} className={`ob-c ob-top tool-${tool}`} onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up}
                    style={{ touchAction: canDraw ? 'none' : 'auto' }} />
                {zoom > 1.01 && (
                    <div className="ob-zoom">
                        🔍 {String(Math.round(zoom * 10) / 10).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]).replace('.', '٫')}×
                        {canDraw && <button type="button" onClick={() => zoomAt(1)} aria-label="اندازه‌ی کامل">⟲ کامل</button>}
                    </div>
                )}
                {textAt && (
                    <div className="ob-text" style={{ right: `${(1 - (textAt.x - st.current.view.x) / (W / st.current.view.z)) * 100}%`, top: `${((textAt.y - st.current.view.y) / (H / st.current.view.z)) * 100}%` }}>
                        <textarea autoFocus value={text} onChange={(e) => setText(e.target.value)} placeholder="بنویس…" rows={2}
                            onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); commitText(); } if (e.key === 'Escape') setTextAt(null); }} />
                        <div><button type="button" onClick={commitText}>✓</button><button type="button" onClick={() => setTextAt(null)}>✕</button></div>
                    </div>
                )}
            </div>
        </div>
    );
});

export default Board;
