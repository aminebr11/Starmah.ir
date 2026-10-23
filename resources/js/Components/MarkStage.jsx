import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** ابزارهای برگه: «جابه‌جایی/زوم» و ابزارهای نوشتن. */
export const TOOLS = [
    { key: 'move', icon: '✋', label: 'جابه‌جایی' },
    { key: 'pen', icon: '✏️', label: 'قلم' },
    { key: 'ok', icon: '✔', label: 'درست', color: '#16a34a' },
    { key: 'no', icon: '✘', label: 'غلط', color: '#dc2626' },
    { key: 'star', icon: '⭐', label: 'ستاره' },
];
export const COLORS = ['#e11d48', '#16a34a', '#2563eb', '#f59e0b', '#7c3aed', '#111827'];
/** اندازه‌ها روی صفحه‌ی نمایش (پیکسل)؛ با زوم عوض نمی‌شوند: هر چه بزرگ‌نمایی بیشتر، خط روی برگه نازک‌تر. */
export const SIZES = [{ k: 's', pen: 2.5, stamp: 26, t: 'نازک' }, { k: 'm', pen: 5, stamp: 38, t: 'متوسط' }, { k: 'l', pen: 9, stamp: 54, t: 'ضخیم' }];
const MAX_ZOOM = 8;
const PREF = 'sv-pen';

const loadPref = () => { try { return JSON.parse(localStorage.getItem(PREF)) || {}; } catch { return {}; } };

/** کشیدنِ یک علامت (k = نسبتِ مختصاتِ مقصد به مختصاتِ طبیعیِ عکس). */
export function paint(g, m, k = 1) {
    g.save();
    g.lineCap = 'round'; g.lineJoin = 'round';
    if (m.type === 'pen') {
        g.strokeStyle = m.c || '#e11d48'; g.lineWidth = m.w * k;
        g.beginPath();
        const p = m.pts;
        if (p.length < 3) {
            p.forEach(([x, y], j) => (j ? g.lineTo(x * k, y * k) : g.moveTo(x * k, y * k)));
            if (p.length === 1) g.lineTo(p[0][0] * k + 0.1, p[0][1] * k);
        } else {
            // منحنیِ نرم از وسطِ نقطه‌ها (خطِ دست صاف‌تر و زیباتر)
            g.moveTo(p[0][0] * k, p[0][1] * k);
            for (let j = 1; j < p.length - 1; j++) {
                const mx = (p[j][0] + p[j + 1][0]) / 2, my = (p[j][1] + p[j + 1][1]) / 2;
                g.quadraticCurveTo(p[j][0] * k, p[j][1] * k, mx * k, my * k);
            }
            const l = p[p.length - 1];
            g.lineTo(l[0] * k, l[1] * k);
        }
        g.stroke();
    } else {
        const s = m.s * k;
        g.font = `bold ${s}px sans-serif`; g.textAlign = 'center'; g.textBaseline = 'middle';
        g.fillStyle = m.type === 'ok' ? '#16a34a' : m.type === 'no' ? '#dc2626' : '#f59e0b';
        g.strokeStyle = 'rgba(255,255,255,.9)'; g.lineWidth = Math.max(2, s / 10);
        const glyph = m.type === 'ok' ? '✔' : m.type === 'no' ? '✘' : '★';
        g.strokeText(glyph, m.x * k, m.y * k);
        g.fillText(glyph, m.x * k, m.y * k);
    }
    g.restore();
}

/**
 * برگه‌ی تصحیح با زوم و قلم (موبایل و کامپیوتر).
 *
 *  ✋ جابه‌جایی: یک انگشت = جابه‌جا کردن، دو انگشت = زوم (و جابه‌جایی)، دو ضربه = زومِ سریع.
 *  ✏️ قلم/✔/✘/⭐: یک انگشت = نوشتن یا گذاشتنِ علامت، دو انگشت همچنان زوم و جابه‌جایی است
 *     (خطی که با رسیدنِ انگشتِ دوم نیمه‌کاره ماند پاک می‌شود).
 *  کامپیوتر: چرخِ ماوس = زوم، کشیدن = جابه‌جایی (در حالتِ ✋) یا نوشتن.
 *
 * عکس با transform بزرگ می‌شود و علامت‌ها روی یک بومِ هم‌اندازه‌ی صفحه با همان تبدیل کشیده
 * می‌شوند؛ پس بومِ غول‌پیکر لازم نیست و روی گوشی روان است. علامت‌ها در مختصاتِ طبیعیِ عکس
 * ذخیره می‌شوند (برای ذخیره‌ی نهایی روی خودِ عکس).
 */
export default function MarkStage({ src, marks, setMarks, editable = false, imgRef, onSize, onError, extra = null }) {
    const pref = useRef(loadPref());
    const [tool, setToolState] = useState(editable ? (pref.current.tool && pref.current.tool !== 'move' ? pref.current.tool : 'move') : 'move');
    const [color, setColor] = useState(pref.current.color || COLORS[0]);
    const [sizeK, setSizeK] = useState(pref.current.size || 'm');
    const [zoomPct, setZoomPct] = useState(100);
    const [size, setSize] = useState(null);           // {w,h} طبیعی
    const stageRef = useRef(null);
    const layerRef = useRef(null);
    const canvasRef = useRef(null);
    const T = useRef({ s: 1, x: 0, y: 0, fit: 1 });   // تبدیلِ فعلی
    const pointers = useRef(new Map());
    const gesture = useRef(null);
    const lastTap = useRef(0);
    const marksRef = useRef(marks);
    marksRef.current = marks;
    const live = useRef(null);                       // خطِ در حالِ کشیدن
    const raf = useRef(0);
    const toolRef = useRef(tool);
    toolRef.current = tool;
    const sz = SIZES.find((x) => x.k === sizeK) || SIZES[1];
    const penRef = useRef({ color, sz });
    penRef.current = { color, sz };

    const save = (p) => { pref.current = { ...pref.current, ...p }; try { localStorage.setItem(PREF, JSON.stringify(pref.current)); } catch { /* */ } };
    const setTool = (t) => { setToolState(t); if (editable) save({ tool: t }); };

    /* ── رسم ── */
    const draw = useCallback(() => {
        cancelAnimationFrame(raf.current);
        raf.current = requestAnimationFrame(() => {
            const st = stageRef.current, c = canvasRef.current, layer = layerRef.current;
            if (!st || !c) return;
            const { s, x, y } = T.current;
            if (layer) layer.style.transform = `translate3d(${x}px, ${y}px, 0) scale(${s})`;
            const dpr = Math.min(2.5, window.devicePixelRatio || 1);
            const w = st.clientWidth, h = st.clientHeight;
            if (c.width !== Math.round(w * dpr) || c.height !== Math.round(h * dpr)) { c.width = Math.round(w * dpr); c.height = Math.round(h * dpr); }
            const g = c.getContext('2d');
            g.setTransform(1, 0, 0, 1, 0, 0);
            g.clearRect(0, 0, c.width, c.height);
            g.setTransform(dpr * s, 0, 0, dpr * s, dpr * x, dpr * y);
            marksRef.current.forEach((m) => paint(g, m, 1));
            if (live.current) paint(g, live.current, 1);
        });
    }, []);
    useEffect(() => { draw(); }, [marks, draw]);

    /* ── اندازه و جاگیری ── */
    const clamp = useCallback(() => {
        const st = stageRef.current; const t = T.current;
        if (!st || !size) return;
        const W = st.clientWidth, H = st.clientHeight, iw = size.w * t.s, ih = size.h * t.s, m = 40;
        t.x = iw <= W ? (W - iw) / 2 : Math.min(m, Math.max(W - iw - m, t.x));
        t.y = ih <= H ? Math.max(8, (H - ih) / 2) : Math.min(m, Math.max(H - ih - m, t.y));
    }, [size]);
    const fit = useCallback(() => {
        const st = stageRef.current;
        if (!st || !size) return;
        const W = st.clientWidth - 16, H = st.clientHeight - 16;
        // برگه‌ی بلند (چندصفحه‌ای): اندازه‌ی پهنا؛ وگرنه کلِ برگه در صفحه
        const s = size.h / size.w > 1.6 ? W / size.w : Math.min(W / size.w, H / size.h);
        T.current = { s, x: (st.clientWidth - size.w * s) / 2, y: 8, fit: s };
        clamp();
        setZoomPct(100);
        draw();
    }, [size, clamp, draw]);
    useLayoutEffect(() => { fit(); }, [fit]);
    useEffect(() => {
        const st = stageRef.current;
        if (!st || typeof ResizeObserver === 'undefined') return undefined;
        let w0 = st.clientWidth;
        const ro = new ResizeObserver(() => { if (Math.abs(st.clientWidth - w0) > 30) { w0 = st.clientWidth; fit(); } else { clamp(); draw(); } });
        ro.observe(st);
        return () => ro.disconnect();
    }, [fit, clamp, draw]);

    const zoomAt = (factor, px, py) => {
        const t = T.current;
        const ns = Math.min(t.fit * MAX_ZOOM, Math.max(t.fit * 0.6, t.s * factor));
        const nx = (px - t.x) / t.s, ny = (py - t.y) / t.s;
        t.x = px - nx * ns; t.y = py - ny * ns; t.s = ns;
        clamp(); setZoomPct(Math.round((ns / t.fit) * 100)); draw();
    };
    const zoomBtn = (f) => { const st = stageRef.current; zoomAt(f, st.clientWidth / 2, st.clientHeight / 2); };

    /* ── اشاره‌گرها (انگشت، قلمِ لمسی، ماوس) ── */
    const local = (e) => { const r = stageRef.current.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; };
    const toNat = (p) => { const t = T.current; return [(p.x - t.x) / t.s, (p.y - t.y) / t.s]; };
    const startPinch = () => {
        const [a, b] = [...pointers.current.values()];
        gesture.current = { type: 'pinch', d: Math.hypot(a.x - b.x, a.y - b.y) || 1, mx: (a.x + b.x) / 2, my: (a.y + b.y) / 2, t: { ...T.current } };
    };

    const onDown = (e) => {
        if (!size) return;
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        e.preventDefault();
        stageRef.current.setPointerCapture?.(e.pointerId);
        const p = local(e);
        pointers.current.set(e.pointerId, p);
        if (pointers.current.size === 2) {
            // انگشتِ دوم: خطِ تازه‌شروع‌شده پاک می‌شود و زوم شروع می‌شود
            if (gesture.current?.type === 'draw') {
                const g0 = gesture.current;
                if (g0.stroke.pts.length > 8 && Date.now() - g0.at > 350) setMarks((m) => [...m, g0.stroke]);
                live.current = null;
            }
            startPinch();
            return;
        }
        if (pointers.current.size > 2) return;
        const t = toolRef.current;
        if (!editable || t === 'move') {
            // دو ضربه = زومِ سریع / برگشت
            const now = Date.now();
            if (e.pointerType !== 'mouse' && now - lastTap.current < 300) {
                lastTap.current = 0;
                const T0 = T.current;
                if (T0.s > T0.fit * 1.3) fit(); else zoomAt(2.5, p.x, p.y);
                gesture.current = null;
                return;
            }
            lastTap.current = now;
            gesture.current = { type: 'pan', sx: p.x, sy: p.y, t: { ...T.current } };
        } else if (t === 'pen') {
            const { color: c, sz: s } = penRef.current;
            const stroke = { type: 'pen', pts: [toNat(p)], w: s.pen / T.current.s, c };
            live.current = stroke;
            gesture.current = { type: 'draw', stroke, at: Date.now(), lx: p.x, ly: p.y };
            draw();
        } else {
            gesture.current = { type: 'tap', sx: p.x, sy: p.y, at: Date.now() };
        }
    };
    const onMove = (e) => {
        if (!pointers.current.has(e.pointerId)) return;
        e.preventDefault();
        const p = local(e);
        pointers.current.set(e.pointerId, p);
        const g = gesture.current;
        if (!g) return;
        if (g.type === 'pinch' && pointers.current.size >= 2) {
            const [a, b] = [...pointers.current.values()];
            const d = Math.hypot(a.x - b.x, a.y - b.y) || 1, mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
            const t0 = g.t, tc = T.current;
            const ns = Math.min(t0.fit * MAX_ZOOM, Math.max(t0.fit * 0.6, t0.s * (d / g.d)));
            const nx = (g.mx - t0.x) / t0.s, ny = (g.my - t0.y) / t0.s;
            tc.s = ns; tc.x = mx - nx * ns; tc.y = my - ny * ns;
            clamp(); setZoomPct(Math.round((ns / tc.fit) * 100)); draw();
        } else if (g.type === 'pan') {
            T.current.x = g.t.x + (p.x - g.sx); T.current.y = g.t.y + (p.y - g.sy);
            if (Math.hypot(p.x - g.sx, p.y - g.sy) > 8) lastTap.current = 0;
            clamp(); draw();
        } else if (g.type === 'draw') {
            if (Math.hypot(p.x - g.lx, p.y - g.ly) < 1.5) return;
            g.lx = p.x; g.ly = p.y;
            g.stroke.pts.push(toNat(p));
            draw();
        }
    };
    const onUp = (e) => {
        if (!pointers.current.has(e.pointerId)) return;
        const p = local(e);
        pointers.current.delete(e.pointerId);
        const g = gesture.current;
        if (g?.type === 'draw' && pointers.current.size === 0) {
            setMarks((m) => [...m, g.stroke]);
            live.current = null;
            gesture.current = null;
        } else if (g?.type === 'tap' && pointers.current.size === 0) {
            if (Math.hypot(p.x - g.sx, p.y - g.sy) < 12 && Date.now() - g.at < 700) {
                const [x, y] = toNat(p);
                setMarks((m) => [...m, { type: toolRef.current, x, y, s: penRef.current.sz.stamp / T.current.s }]);
            }
            gesture.current = null;
        } else if (g?.type === 'pinch') {
            // یک انگشت ماند: فقط در حالتِ ✋ جابه‌جایی ادامه پیدا می‌کند (نه نوشتنِ ناخواسته)
            if (pointers.current.size === 1 && (!editable || toolRef.current === 'move')) {
                const [q] = [...pointers.current.values()];
                gesture.current = { type: 'pan', sx: q.x, sy: q.y, t: { ...T.current } };
            } else if (pointers.current.size === 0) gesture.current = null;
            else gesture.current = { type: 'idle' };
        } else if (pointers.current.size === 0) {
            gesture.current = null;
        }
        if (pointers.current.size === 0 && live.current) { live.current = null; draw(); }
    };
    useEffect(() => {
        const st = stageRef.current;
        if (!st) return undefined;
        const wheel = (e) => {
            e.preventDefault();
            const r = st.getBoundingClientRect();
            zoomAt(Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0018)), e.clientX - r.left, e.clientY - r.top);
        };
        st.addEventListener('wheel', wheel, { passive: false });
        return () => st.removeEventListener('wheel', wheel);
    }); // eslint-disable-line react-hooks/exhaustive-deps

    const writing = editable && tool !== 'move';
    return (
        <div className="ms">
            <div ref={stageRef} className={`ms-stage ${writing ? 'writing' : 'moving'}`}
                onPointerDown={onDown} onPointerMove={onMove} onPointerUp={onUp} onPointerCancel={onUp} onLostPointerCapture={onUp}
                onContextMenu={(e) => e.preventDefault()}>
                <div ref={layerRef} className="ms-layer" style={size ? { width: size.w, height: size.h } : undefined}>
                    <img ref={imgRef} src={src} alt="برگه" draggable={false} crossOrigin={undefined}
                        onLoad={(e) => { const s2 = { w: e.currentTarget.naturalWidth, h: e.currentTarget.naturalHeight }; setSize(s2); onSize?.(s2); }}
                        onError={onError} />
                </div>
                <canvas ref={canvasRef} className="ms-canvas" />
                {!size && <div className="ms-loading">⏳ در حالِ بازکردنِ برگه…</div>}
                <div className="ms-zoom" onPointerDown={(e) => e.stopPropagation()}>
                    <button type="button" onClick={() => zoomBtn(1.35)} aria-label="بزرگ‌نمایی">＋</button>
                    <button type="button" className="pct" onClick={fit} title="اندازه‌ی کامل">{fa(zoomPct)}٪</button>
                    <button type="button" onClick={() => zoomBtn(1 / 1.35)} aria-label="کوچک‌نمایی">－</button>
                </div>
                {writing && <div className="ms-hint">✌️ زوم و جابه‌جایی با دو انگشت</div>}
            </div>

            {editable && (
                <div className="ms-dock" onPointerDown={(e) => e.stopPropagation()}>
                    <div className="ms-row">
                        {TOOLS.map((t) => (
                            <button key={t.key} type="button" className={`ms-tool ${tool === t.key ? 'on' : ''}`} onClick={() => setTool(t.key)} title={t.label}
                                style={t.color && tool !== t.key ? { color: t.color } : undefined}>
                                <span>{t.icon}</span><small>{t.label}</small>
                            </button>
                        ))}
                        <button type="button" className="ms-tool" onClick={() => setMarks((m) => m.slice(0, -1))} disabled={!marks.length} title="برگرداندن">
                            <span>↶</span><small>برگردان</small>
                        </button>
                        {extra}
                    </div>
                    {tool !== 'move' && (
                        <div className="ms-row ms-opts">
                            {tool === 'pen' && COLORS.map((c) => (
                                <button key={c} type="button" className={`ms-color ${color === c ? 'on' : ''}`} style={{ '--c': c }}
                                    onClick={() => { setColor(c); save({ color: c }); }} aria-label="رنگِ قلم" />
                            ))}
                            <span className="ms-sep" />
                            {SIZES.map((s) => (
                                <button key={s.k} type="button" className={`ms-size ${sizeK === s.k ? 'on' : ''}`} onClick={() => { setSizeK(s.k); save({ size: s.k }); }} title={s.t}>
                                    {tool === 'pen'
                                        ? <i style={{ width: s.pen * 2 + 4, height: s.pen * 2 + 4, background: color }} />
                                        : <b style={{ fontSize: Math.round(s.stamp / 2.4) }}>{tool === 'ok' ? '✔' : tool === 'no' ? '✘' : '★'}</b>}
                                </button>
                            ))}
                            {marks.length > 0 && <button type="button" className="ms-clear" onClick={() => window.confirm('همه‌ی علامت‌های تازه پاک شوند؟') && setMarks([])}>🧽 پاک‌کردنِ همه</button>}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
