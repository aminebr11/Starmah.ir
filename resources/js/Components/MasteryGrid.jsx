import { useState } from 'react';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/** رنگِ روشنِ هر سطح برای پنلِ روشنِ معلم. */
const TINT = {
    master: ['#ccf3df', '#05784a'],
    proficient: ['#dbeafe', '#1d4ed8'],
    developing: ['#fff1bf', '#8a5a00'],
    beginning: ['#fde2d8', '#b4400f'],
    none: ['#f1f4f9', '#9aa6be'],
};
const tint = (key) => TINT[key] || TINT.none;

/**
 * نقشه‌ی تسلطِ کلاس: هر خانه سطحِ یک دانش‌آموز در یک مبحث (از همان موتورِ تسلطِ کارنامه)،
 * کنارِ فهرستِ «چه کسی امروز کمک لازم دارد».
 */
export default function MasteryGrid({ data }) {
    const subjects = data?.subjects || [];
    const [si, setSi] = useState(0);
    const sub = subjects[Math.min(si, subjects.length - 1)];
    const levels = data?.levels || [];
    const lowLimit = sub ? Math.max(2, Math.ceil(sub.rows.length * 0.2)) : 2;

    return (
        <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 340px', gap: 20, alignItems: 'start' }} className="themes-grid">
            <div className="panel">
                <h3>🧭 نقشه‌ی تسلطِ کلاس <span style={{ background: '#2bb673', color: '#fff', fontSize: 11, borderRadius: 20, padding: '1px 9px' }}>جدید</span></h3>
                <p style={{ color: 'var(--muted)', fontSize: 12.5, margin: '0 0 10px', lineHeight: 1.9 }}>
                    هر خانه سطحِ یک دانش‌آموز در یک مبحث است — برآوردشده از همه‌ی پاسخ‌هایش در مأموریت، مرور، آزمون، بازی و نمره‌ی شما.
                    سطح‌ها همان ارزشیابیِ توصیفی است و برای پر کردنِ کارنامه‌ی توصیفی کمک می‌کند.
                </p>

                {subjects.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز پاسخی ثبت نشده است. وقتی بچه‌ها مأموریت، آزمون یا بازی انجام دهند، این جدول پر می‌شود.</p>}

                {subjects.length > 1 && (
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 10 }}>
                        {subjects.map((s, k) => (
                            <button key={s.name} type="button" onClick={() => setSi(k)} className={`btn btn-sm ${k === si ? '' : 'btn-ghost'}`}>{s.name}</button>
                        ))}
                    </div>
                )}

                {sub && (
                    <>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '0 0 12px' }}>
                            {levels.map((l) => (
                                <span key={l.key} style={{ fontSize: 12, fontWeight: 700, borderRadius: 20, padding: '2px 10px', background: tint(l.key)[0], color: tint(l.key)[1] }}>{l.label}{l.min > 0 ? ` (${fa(l.min)}٪+)` : ''}</span>
                            ))}
                            <span style={{ fontSize: 12, fontWeight: 700, borderRadius: 20, padding: '2px 10px', background: TINT.none[0], color: TINT.none[1] }}>— هنوز کافی نیست</span>
                        </div>
                        <div style={{ overflowX: 'auto' }}>
                            <table style={{ borderCollapse: 'separate', borderSpacing: 5, minWidth: 140 + sub.topics.length * 112 }}>
                                <thead>
                                    <tr>
                                        <th style={{ textAlign: 'start', fontSize: 12.5, color: 'var(--navy-700)', position: 'sticky', insetInlineStart: 0, background: '#fff' }}>دانش‌آموز</th>
                                        {sub.topics.map((t) => {
                                            const warn = (sub.low[t] || 0) >= lowLimit;
                                            return (
                                                <th key={t} style={{ fontSize: 12, fontWeight: 800, color: warn ? '#c2410c' : 'var(--navy-700)', minWidth: 104, lineHeight: 1.5, verticalAlign: 'bottom' }}>
                                                    {warn && '⚠️ '}{t}
                                                    {warn && <div style={{ fontSize: 11, background: '#fde7dc', borderRadius: 8, padding: '1px 4px', marginTop: 3 }}>{fa(sub.low[t])} نفر «نیاز به تلاش بیشتر»</div>}
                                                </th>
                                            );
                                        })}
                                    </tr>
                                </thead>
                                <tbody>
                                    {sub.rows.map((r) => (
                                        <tr key={r.id}>
                                            <td style={{ fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap', position: 'sticky', insetInlineStart: 0, background: '#fff', paddingInlineEnd: 8 }}>
                                                {r.name}
                                                {r.trend != null && r.trend <= -10 && <span title="افت در دو هفته‌ی اخیر" style={{ color: '#c2410c', marginInlineStart: 4 }}>↓</span>}
                                                {r.trend != null && r.trend >= 10 && <span title="رشد در دو هفته‌ی اخیر" style={{ color: '#05784a', marginInlineStart: 4 }}>↑</span>}
                                            </td>
                                            {sub.topics.map((t) => <Cell key={t} c={r.cells[t]} />)}
                                        </tr>
                                    ))}
                                    <tr>
                                        <td style={{ fontSize: 12.5, color: 'var(--muted)', fontWeight: 700, position: 'sticky', insetInlineStart: 0, background: '#fff', borderTop: '2px solid var(--line)' }}>میانگینِ کلاس</td>
                                        {sub.topics.map((t) => <Cell key={t} c={sub.avg[t]} />)}
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            <div className="panel">
                <h3>🙋 چه کسی امروز کمک لازم دارد؟</h3>
                <p style={{ color: 'var(--muted)', fontSize: 12.5, margin: '0 0 6px', lineHeight: 1.9 }}>
                    دانش‌آموزانی که در دو مبحث یا بیشتر «نیاز به تلاش بیشتر» دارند، یا تسلطشان در یک درس پایین آمده.
                </p>
                {(data?.needsHelp || []).length === 0 && <p style={{ color: 'var(--muted)', fontSize: 13 }}>فعلاً کسی در این فهرست نیست 🌟</p>}
                {(data?.needsHelp || []).map((h) => (
                    <div key={h.id} style={{ padding: '11px 0', borderTop: '1px solid var(--line)' }}>
                        <b style={{ color: 'var(--navy-800)', fontSize: 14.5 }}>{h.name}</b>
                        {h.low.length > 0 && (
                            <div style={{ fontSize: 12.5, color: 'var(--muted)', marginTop: 3, lineHeight: 2 }}>
                                {fa(h.low.length)} مبحث در «نیاز به تلاش بیشتر»:{' '}
                                {h.low.map((t) => <span key={t} style={{ display: 'inline-block', fontSize: 11.5, fontWeight: 800, borderRadius: 20, padding: '0 9px', margin: '0 0 2px 3px', background: TINT.beginning[0], color: TINT.beginning[1] }}>{t}</span>)}
                            </div>
                        )}
                        {h.drops.map((d) => (
                            <div key={d.subject} style={{ fontSize: 12.5, color: '#c2410c', marginTop: 3 }}>↓ افتِ {fa(Math.abs(d.trend))} درصدی در {d.subject} (دو هفته‌ی اخیر)</div>
                        ))}
                    </div>
                ))}
                <div style={{ marginTop: 10, background: '#f6f8fd', border: '1px dashed #c9d3e6', borderRadius: 12, padding: '9px 12px', fontSize: 12.5, color: '#4a5878', lineHeight: 1.9 }}>
                    «مرورِ امروزِ» هر دانش‌آموز خودکار روی مبحث‌هایی تمرکز می‌کند که موعدِ مرورشان رسیده یا ضعیف‌اند.
                </div>
            </div>
        </div>
    );
}

function Cell({ c }) {
    const [bg, fg] = tint(c?.mastery != null ? c.key : 'none');
    return (
        <td title={c ? (c.mastery != null ? `${fa(c.mastery)}٪ · ${fa(c.n)} نشانه` : `${fa(c.n)} نشانه — هنوز کافی نیست`) : 'تمرینی ثبت نشده'}
            style={{ height: 38, borderRadius: 10, textAlign: 'center', fontSize: 12, fontWeight: 800, background: bg, color: fg, padding: '0 6px', whiteSpace: 'nowrap' }}>
            {c?.mastery != null ? c.label : '—'}
        </td>
    );
}
