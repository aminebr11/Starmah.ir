import { useState, useMemo, useId, useRef, useLayoutEffect } from 'react';

/**
 * نمودارهای روندِ امتیاز و رتبه — هفته‌به‌هفته در سالِ تحصیلی.
 *
 * سه نما: امتیازِ هر هفته (ستونی)، جمعِ امتیاز از ابتدای سال (مساحتی) و
 * رتبه در کلاس (خطی، با محورِ وارونه چون رتبه‌ی ۱ بالاترین است).
 * SVG خالص است: نه کتابخانه‌ای اضافه می‌شود، نه در چاپ خراب می‌شود.
 */
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const VIEWS = [
    { v: 'xp', t: '⭐ امتیازِ هفتگی', hint: 'امتیازی که در هر هفته گرفته' },
    { v: 'total', t: '📈 جمعِ امتیاز', hint: 'امتیازِ جمع‌شده از ابتدای سال' },
    { v: 'rank', t: '🏅 رتبه در کلاس', hint: 'رتبه بر پایه‌ی جمعِ امتیاز از ابتدای سال' },
];

const H = 240, PAD = { t: 16, r: 16, b: 34, l: 42 };

export default function PointsTrend({ data, compact = false, title = '📊 روندِ امتیاز و رتبه' }) {
    const [view, setView] = useState('xp');
    const [hover, setHover] = useState(null);
    // عرضِ نمودار با عرضِ واقعیِ ظرف یکی می‌شود تا کشیده نشود و نوشته‌ها خوانا بمانند
    const box = useRef(null);
    const [W, setW] = useState(760);
    useLayoutEffect(() => {
        const el = box.current;
        if (!el) return;
        const set = () => setW(Math.max(280, Math.round(el.clientWidth)));
        set();
        if (typeof ResizeObserver === 'undefined') return;
        const ro = new ResizeObserver(set);
        ro.observe(el);
        return () => ro.disconnect();
    }, []);
    const uid = useId().replace(/:/g, '');
    const pts = data?.points || [];
    const s = data?.summary || {};

    const meta = useMemo(() => {
        if (!pts.length) return null;
        const vals = pts.map((p) => (view === 'rank' ? p.class_rank : view === 'total' ? p.total : p.xp));
        const max = Math.max(...vals, view === 'rank' ? (s.class_size || 1) : 1);
        const min = view === 'rank' ? 1 : 0;
        const iw = W - PAD.l - PAD.r, ih = H - PAD.t - PAD.b;
        const x = (i) => PAD.l + (pts.length === 1 ? iw / 2 : (i * iw) / (pts.length - 1));
        // رتبه: هرچه کمتر، بالاتر
        const y = (v) => view === 'rank'
            ? PAD.t + ((v - min) / Math.max(1, max - min)) * ih
            : PAD.t + ih - (v / Math.max(1, max)) * ih;
        return { vals, max, min, iw, ih, x, y };
    }, [pts, view, s.class_size, W]);

    if (!pts.length) {
        return (
            <div className="pt-card">
                <div className="pt-empty">📉 هنوز داده‌ای برای نمودار نیست. با گرفتنِ امتیاز در هفته‌های آینده، روندِ پیشرفت اینجا ساخته می‌شود.</div>
            </div>
        );
    }

    const { max, min, iw, ih, x, y } = meta;
    const valOf = (p) => (view === 'rank' ? p.class_rank : view === 'total' ? p.total : p.xp);
    const line = pts.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(valOf(p)).toFixed(1)}`).join(' ');
    const area = `${line} L${x(pts.length - 1).toFixed(1)},${PAD.t + ih} L${x(0).toFixed(1)},${PAD.t + ih} Z`;
    const barW = Math.max(6, Math.min(34, (iw / pts.length) * 0.62));
    // خطوطِ راهنما
    const ticks = view === 'rank'
        ? [...new Set([1, Math.ceil(max / 2), max])]
        : [0, Math.round(max / 2), max];
    const active = hover != null ? pts[hover] : pts[pts.length - 1];
    const best = view === 'xp' ? Math.max(...pts.map((p) => p.xp)) : null;

    return (
        <div className="pt-card">
            <div className="pt-head">
                <b>{title}</b>
                <span className="pt-year">{data.label}</span>
            </div>

            {!compact && (
                <div className="pt-kpis">
                    <div><em>⭐ {fa(s.total)}</em><span>امتیازِ امسال</span></div>
                    <div><em>🏅 {s.class_rank ? `${fa(s.class_rank)} از ${fa(s.class_size)}` : '—'}</em><span>رتبه در کلاس</span></div>
                    <div className={s.rank_delta > 0 ? 'up' : s.rank_delta < 0 ? 'down' : ''}>
                        <em>{s.rank_delta > 0 ? `▲ ${fa(s.rank_delta)}` : s.rank_delta < 0 ? `▼ ${fa(Math.abs(s.rank_delta))}` : '—'}</em>
                        <span>تغییرِ رتبه نسبت به هفته‌ی قبل</span>
                    </div>
                    <div><em>🚀 {s.best_week ? fa(s.best_week.xp) : '—'}</em><span>بهترین هفته{s.best_week ? ` (${s.best_week.label})` : ''}</span></div>
                </div>
            )}

            <div className="pt-tabs no-print">
                {VIEWS.map((v) => (
                    <button type="button" key={v.v} className={view === v.v ? 'on' : ''} onClick={() => setView(v.v)} title={v.hint}>{v.t}</button>
                ))}
            </div>

            <div className="pt-plot" ref={box}>
                <svg viewBox={`0 0 ${W} ${H}`} role="img"
                    aria-label={`نمودارِ ${VIEWS.find((v) => v.v === view).t} هفته‌به‌هفته`}
                    onMouseLeave={() => setHover(null)}>
                    <defs>
                        <linearGradient id={`g${uid}`} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#6aa6ff" stopOpacity=".55" />
                            <stop offset="100%" stopColor="#6aa6ff" stopOpacity="0" />
                        </linearGradient>
                        <linearGradient id={`b${uid}`} x1="0" y1="1" x2="0" y2="0">
                            <stop offset="0%" stopColor="#ffb13d" />
                            <stop offset="100%" stopColor="#ffd98a" />
                        </linearGradient>
                    </defs>

                    {ticks.map((t) => (
                        <g key={t}>
                            <line x1={PAD.l} x2={W - PAD.r} y1={y(t)} y2={y(t)} className="pt-grid" />
                            <text x={PAD.l - 8} y={y(t) + 4} className="pt-ylab">{view === 'rank' ? fa(t) : fa(t)}</text>
                        </g>
                    ))}

                    {view === 'xp' ? (
                        pts.map((p, i) => {
                            const h = Math.max(p.xp > 0 ? 3 : 0, PAD.t + ih - y(p.xp));
                            return (
                                <rect key={p.n} x={x(i) - barW / 2} y={PAD.t + ih - h} width={barW} height={h} rx={Math.min(7, barW / 2)}
                                    className={`pt-bar ${p.xp === best && best > 0 ? 'best' : ''} ${hover === i ? 'on' : ''}`}
                                    style={p.xp === best && best > 0 ? { fill: `url(#b${uid})` } : undefined}
                                    onMouseEnter={() => setHover(i)} onClick={() => setHover(i)} />
                            );
                        })
                    ) : (
                        <>
                            {view === 'total' && <path d={area} fill={`url(#g${uid})`} />}
                            <path d={line} className={`pt-line ${view === 'rank' ? 'rank' : ''}`} />
                            {pts.map((p, i) => (
                                <circle key={p.n} cx={x(i)} cy={y(valOf(p))} r={hover === i ? 6 : 4}
                                    className={`pt-dot ${view === 'rank' ? 'rank' : ''}`}
                                    onMouseEnter={() => setHover(i)} onClick={() => setHover(i)} />
                            ))}
                        </>
                    )}

                    {pts.map((p, i) => (
                        (pts.length <= 14 || i % Math.ceil(pts.length / 12) === 0 || i === pts.length - 1) && (
                            <text key={p.n} x={x(i)} y={H - 12} className={`pt-xlab ${hover === i ? 'on' : ''}`}>{p.tick}</text>
                        )
                    ))}
                    {/* ناحیه‌های نامرئیِ لمس برای موبایل */}
                    {pts.map((p, i) => (
                        <rect key={`h${p.n}`} x={x(i) - iw / pts.length / 2} y={PAD.t} width={iw / pts.length} height={ih}
                            fill="transparent" onMouseEnter={() => setHover(i)} onClick={() => setHover(i)} />
                    ))}
                </svg>
            </div>

            {active && (
                <div className="pt-tip">
                    <b>{active.label}</b>
                    <span>{active.range}</span>
                    <em>⭐ {fa(active.xp)} امتیازِ این هفته · جمع {fa(active.total)}</em>
                    <em>🏅 رتبه {fa(active.class_rank)} از {fa(active.class_size)} در کلاس{active.school_size > active.class_size ? ` · ${fa(active.school_rank)} از ${fa(active.school_size)} در مدرسه` : ''}</em>
                </div>
            )}
        </div>
    );
}
