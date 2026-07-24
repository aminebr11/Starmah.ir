import { useState, useRef } from 'react';

/**
 * کیتِ نمودارهای سبکِ SVG برای گزارش‌های BI — بدونِ کتابخانه، RTL و رقمِ فارسی.
 * پالتِ دسته‌ای (ترتیبِ ثابت، اعتبارسنجی‌شده برای کوررنگی): آبی، نارنجی، سبزآبی، بنفش، سرخابی.
 * قواعد: خطوطِ ۲px، برچسبِ مستقیمِ مقادیر، محور/شبکه‌ی کم‌رنگ، tooltip روی هاور.
 */
export const PAL = ['#3d7bf0', '#e0912f', '#149d8a', '#8b5cf6', '#d6516c'];
export const OK = '#2bb673', WARN = '#e8862e', CRIT = '#e8505b';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/* ---------- نمودارِ روندِ ناحیه‌ای (با crosshair + tooltip) ---------- */
export function AreaTrend({ data = [], color = PAL[0], height = 150, valueLabel = 'امتیاز', empty = 'داده‌ای نیست' }) {
    const [hover, setHover] = useState(null);
    const ref = useRef(null);
    const W = 600, H = height, padX = 6, padY = 14;
    if (!data.length) return <div style={{ color: 'var(--muted)', fontSize: 13, padding: 12 }}>{empty}</div>;

    const max = Math.max(1, ...data.map((d) => d.value));
    const x = (i) => padX + (i / Math.max(1, data.length - 1)) * (W - padX * 2);
    const y = (v) => H - padY - (v / max) * (H - padY * 2);
    const pts = data.map((d, i) => `${x(i)},${y(d.value)}`).join(' ');
    const area = `${padX},${H - padY} ${pts} ${W - padX},${H - padY}`;
    const last = data[data.length - 1];

    const onMove = (e) => {
        const r = ref.current?.getBoundingClientRect();
        if (!r) return;
        // صفحه RTL است ولی محورِ زمانِ نمودار LTR رسم می‌شود
        const px = ((e.clientX - r.left) / r.width) * W;
        const i = Math.round(((px - padX) / (W - padX * 2)) * (data.length - 1));
        setHover(Math.max(0, Math.min(data.length - 1, i)));
    };

    return (
        <div style={{ position: 'relative' }} dir="ltr">
            <svg ref={ref} viewBox={`0 0 ${W} ${H}`} style={{ width: '100%', display: 'block' }}
                onMouseMove={onMove} onMouseLeave={() => setHover(null)}>
                {/* شبکه‌ی کم‌رنگ */}
                {[0.25, 0.5, 0.75].map((p) => (
                    <line key={p} x1={padX} x2={W - padX} y1={y(max * p)} y2={y(max * p)} stroke="currentColor" opacity=".08" />
                ))}
                <polygon points={area} fill={color} opacity=".14" />
                <polyline points={pts} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" />
                {/* نقطه‌ی پایانی برجسته + برچسبِ مستقیم */}
                <circle cx={x(data.length - 1)} cy={y(last.value)} r="4" fill={color} />
                {hover != null && (
                    <>
                        <line x1={x(hover)} x2={x(hover)} y1={padY} y2={H - padY} stroke="currentColor" opacity=".25" strokeDasharray="3 3" />
                        <circle cx={x(hover)} cy={y(data[hover].value)} r="5" fill={color} stroke="#fff" strokeWidth="2" />
                    </>
                )}
            </svg>
            {hover != null && (
                <div dir="rtl" style={{ position: 'absolute', top: 4, insetInlineStart: 8, background: 'var(--navy-800,#16264f)', color: '#fff', borderRadius: 10, padding: '6px 11px', fontSize: 12, pointerEvents: 'none', boxShadow: '0 8px 20px -8px rgba(0,0,0,.4)' }}>
                    <b>{fa(data[hover].value)}</b> {valueLabel} · {data[hover].label}
                </div>
            )}
            <div dir="rtl" style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>
                <span>{data[0]?.label}</span>
                <b style={{ color }}>{fa(last.value)} {valueLabel} (آخرین روز)</b>
                <span>{last?.label}</span>
            </div>
        </div>
    );
}

/* ---------- دونات (ترکیبِ سهم‌ها) + لِجندِ مقداردار ---------- */
export function Donut({ items = [], size = 150, thickness = 20, centerLabel = 'مجموع' }) {
    const total = items.reduce((s, it) => s + Math.max(0, it.value), 0);
    if (!total) return <div style={{ color: 'var(--muted)', fontSize: 13, padding: 12 }}>هنوز داده‌ای نیست</div>;
    const R = (size - thickness) / 2, C = size / 2, circ = 2 * Math.PI * R;
    let acc = 0;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 18, flexWrap: 'wrap' }}>
            <svg viewBox={`0 0 ${size} ${size}`} style={{ width: size, flex: 'none', transform: 'rotate(-90deg)' }}>
                {items.map((it, i) => {
                    const frac = Math.max(0, it.value) / total;
                    const len = Math.max(0, frac * circ - 2); // فاصله‌ی ۲px بینِ قطاع‌ها
                    const off = -acc * circ;
                    acc += frac;
                    return (
                        <circle key={i} cx={C} cy={C} r={R} fill="none"
                            stroke={PAL[i % PAL.length]} strokeWidth={thickness}
                            strokeDasharray={`${len} ${circ - len}`} strokeDashoffset={off} strokeLinecap="butt">
                            <title>{`${it.label}: ${it.value}`}</title>
                        </circle>
                    );
                })}
            </svg>
            <div style={{ flex: 1, minWidth: 150, display: 'grid', gap: 6 }}>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{centerLabel}: <b style={{ color: 'var(--navy-800,#16264f)', fontSize: 15 }}>{fa(total)}</b></div>
                {items.map((it, i) => (
                    <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5 }}>
                        <i style={{ width: 11, height: 11, borderRadius: 4, background: PAL[i % PAL.length], flex: 'none' }} />
                        <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{it.label}</span>
                        <b style={{ fontVariantNumeric: 'tabular-nums' }}>{fa(it.value)}</b>
                        <span style={{ color: 'var(--muted)', fontSize: 11, minWidth: 34, textAlign: 'end' }}>{fa(Math.round((it.value / total) * 100))}٪</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ---------- نوارهای افقیِ نازک (مقایسه‌ی مقدار) ---------- */
export function HBars({ items = [], color = PAL[0], unit = '', colorByIndex = false }) {
    const max = Math.max(1, ...items.map((it) => it.value));
    if (!items.length) return <div style={{ color: 'var(--muted)', fontSize: 13, padding: 12 }}>داده‌ای نیست</div>;
    return (
        <div style={{ display: 'grid', gap: 9 }}>
            {items.map((it, i) => (
                <div key={i}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, marginBottom: 3 }}>
                        <span style={{ fontWeight: 700 }}>{it.label}</span>
                        <b style={{ fontVariantNumeric: 'tabular-nums' }}>{fa(it.value)}{unit}{it.sub ? <span style={{ color: 'var(--muted)', fontWeight: 400 }}> · {it.sub}</span> : null}</b>
                    </div>
                    <div style={{ height: 9, borderRadius: 6, background: 'rgba(120,140,180,.14)', overflow: 'hidden' }}>
                        <div style={{ height: '100%', width: `${(it.value / max) * 100}%`, borderRadius: 6, background: it.color || (colorByIndex ? PAL[i % PAL.length] : color), transition: 'width .5s' }} />
                    </div>
                </div>
            ))}
        </div>
    );
}

/* ---------- نقشه‌ی گرما (روزِ هفته × هفته) ---------- */
export function Heatmap({ weeks = [], days = [], color = PAL[0], legend = 'فعالیت' }) {
    const flat = weeks.flat().filter((v) => v != null);
    const max = Math.max(1, ...flat);
    const cell = 16, gap = 3;
    return (
        <div>
            <div style={{ display: 'flex', gap, alignItems: 'flex-start' }} dir="rtl">
                <div style={{ display: 'grid', gap, marginTop: 0 }}>
                    {days.map((d, i) => <span key={i} style={{ height: cell, fontSize: 9.5, color: 'var(--muted)', lineHeight: `${cell}px` }}>{d}</span>)}
                </div>
                {weeks.map((week, w) => (
                    <div key={w} style={{ display: 'grid', gap }}>
                        {week.map((v, d) => (
                            <span key={d} title={v == null ? '' : `${fa(v)} ${legend}`} style={{
                                width: cell, height: cell, borderRadius: 4,
                                background: v == null ? 'transparent' : (v === 0 ? 'rgba(120,140,180,.12)' : color),
                                opacity: v ? 0.25 + 0.75 * (v / max) : 1,
                            }} />
                        ))}
                    </div>
                ))}
            </div>
            <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 6 }}>هر ستون یک هفته — رنگِ پررنگ‌تر یعنی {legend}ِ بیشتر</div>
        </div>
    );
}

/* ---------- گِیجِ دایره‌ای (درصد) ---------- */
export function Gauge({ value = 0, size = 92, label = '', color }) {
    const v = Math.max(0, Math.min(100, value));
    const c = color || (v >= 70 ? OK : v >= 40 ? WARN : CRIT);
    const R = size / 2 - 8, C = size / 2, circ = 2 * Math.PI * R;
    return (
        <div style={{ textAlign: 'center' }}>
            <svg viewBox={`0 0 ${size} ${size}`} style={{ width: size, transform: 'rotate(-90deg)' }}>
                <circle cx={C} cy={C} r={R} fill="none" stroke="rgba(120,140,180,.16)" strokeWidth="8" />
                <circle cx={C} cy={C} r={R} fill="none" stroke={c} strokeWidth="8" strokeLinecap="round"
                    strokeDasharray={`${(v / 100) * circ} ${circ}`} />
            </svg>
            <div style={{ marginTop: -size / 2 - 14, marginBottom: size / 2 - 26, fontWeight: 900, fontSize: size / 4.6, color: c }}>{fa(v)}٪</div>
            {label && <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 20 }}>{label}</div>}
        </div>
    );
}

/* ---------- کاشیِ آماری ---------- */
export function Stat({ icon, label, value, sub, tone }) {
    const c = tone === 'ok' ? OK : tone === 'warn' ? WARN : tone === 'crit' ? CRIT : 'var(--navy-800,#16264f)';
    return (
        <div style={{ background: '#fff', border: '1px solid var(--line,#e2e6ee)', borderRadius: 16, padding: '14px 16px', boxShadow: 'var(--shadow,0 6px 18px -12px rgba(20,30,60,.25))' }}>
            <div style={{ fontSize: 12, color: 'var(--muted)', display: 'flex', alignItems: 'center', gap: 6 }}>{icon} {label}</div>
            <div style={{ fontWeight: 900, fontSize: 22, color: c, marginTop: 4, fontVariantNumeric: 'tabular-nums' }}>{value}</div>
            {sub && <div style={{ fontSize: 11.5, color: 'var(--muted)', marginTop: 2 }}>{sub}</div>}
        </div>
    );
}
