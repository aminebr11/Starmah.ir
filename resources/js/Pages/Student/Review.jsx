import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const when = (d) => (d === 0 ? 'همان روز' : d === 1 ? 'فردا' : `${fa(d)} روز بعد`);

/**
 * «مرورِ اشتباه‌های من» — صفحه‌ی اختصاصیِ دانش‌آموز.
 *
 * هر سؤالی که در آزمون، بازی یا مأموریت اشتباه بزند (و هر فصلی که معلم بخواهد) اینجا
 * می‌آید: همان سؤال با گزینه‌های جابه‌جا + سؤال‌های مشابه، با زمان‌بندیِ معلمِ کلاس.
 */
export default function Review() {
    const { board = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    if (!board.enabled) {
        return (
            <ThemedDash title="مرورِ اشتباه‌های من" active="review">
                <div className="k3-card">بخشِ مرور هنوز روی سرور فعال نشده است.</div>
            </ThemedDash>
        );
    }
    const { plan = {}, summary: s = {}, ready = [], waiting = [], done = [] } = board;
    const empty = !ready.length && !waiting.length && !done.length;

    return (
        <ThemedDash title="مرورِ اشتباه‌های من" active="review">
            {banner && <div className="k3-card" style={{ background: 'linear-gradient(135deg,#2bb673,#0f9d58)', fontWeight: 800, marginBottom: 12 }}>{banner}</div>}

            {/* ── سرلوحه ── */}
            <div className="k3-card" style={{ border: 0, background: ready.length ? 'linear-gradient(135deg,#ff7a45,#e8505b)' : 'linear-gradient(135deg,#7c3aed,#2e8bff)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                    <span style={{ fontSize: 42 }}>🔁</span>
                    <div style={{ flex: 1, minWidth: 180 }}>
                        <div style={{ fontWeight: 900, fontSize: 20 }}>{ready.length ? `${fa(ready.length)} اشتباه آماده‌ی مرور است` : 'امروز مرورِ آماده‌ای نداری'}</div>
                        <div style={{ fontSize: 13, lineHeight: 1.9, opacity: .95 }}>
                            {ready.length ? 'همان سؤال‌هایی که اشتباه زدی (گزینه‌ها جابه‌جا شده‌اند) و چند سؤالِ شبیهشان. درستشان کن تا یاد بگیری و بخشی از امتیازت برگردد.'
                                : s.next ? <>نوبتِ بعدیِ مرور: <b>{s.next}</b></> : 'هر اشتباهی در آزمون، بازی یا مأموریت خودکار اینجا می‌آید.'}
                        </div>
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 10, fontSize: 12.5 }}>
                    {s.open > 0 && <span className="k3-chip">{fa(s.open)} مورد در مسیرِ جبران</span>}
                    {s.done > 0 && <span className="k3-chip">✅ {fa(s.done)} جبران شد</span>}
                    {s.xp_recovered > 0 && <span className="k3-chip">⚡ {fa(s.xp_recovered)} امتیاز برگشت</span>}
                    {s.xp_left > 0 && <span className="k3-chip">تا ⚡ {fa(s.xp_left)} امتیازِ دیگر</span>}
                </div>
                {ready.length > 0 && (
                    <Link href={route('missions.remedial.play')} className="k3-btn" style={{ width: '100%', marginTop: 12, background: 'linear-gradient(180deg,#ffd23f,#e9a400)', color: '#2b1d00', fontSize: 16 }}>▶ شروعِ مرور</Link>
                )}
            </div>

            {/* ── زمان‌بندیِ معلم ── */}
            <div className="k3-card" style={{ marginTop: 14 }}>
                <b style={{ fontSize: 16 }}>🗓️ زمان‌بندیِ مرور (اعلامِ معلم)</b>
                {plan.enabled === false ? (
                    <div style={{ fontSize: 13.5, marginTop: 6, lineHeight: 1.9 }}>معلمت فعلاً مرورِ خودکار را برای کلاس خاموش کرده است؛ مرورهایی که خودش بفرستد اینجا می‌آید.</div>
                ) : (
                    <>
                        <div className="rv-steps">
                            <span className="rv-step bad">❌ اشتباه</span>
                            <span className="rv-arrow">←</span>
                            <span className="rv-step">نوبتِ ۱<small>{when(plan.first_delay ?? 0)}</small></span>
                            {(plan.gaps || []).map((g, k) => (
                                <span key={k} style={{ display: 'contents' }}>
                                    <span className="rv-arrow">←</span>
                                    <span className="rv-step">نوبتِ {fa(k + 2)}<small>{fa(g)} روز بعد</small></span>
                                </span>
                            ))}
                            <span className="rv-arrow">←</span>
                            <span className="rv-step ok">✅ جبران شد</span>
                        </div>
                        <ul style={{ margin: '8px 0 0', paddingInlineStart: 18, fontSize: 13, lineHeight: 2 }}>
                            <li>هر نوبت: همان سؤال با <b>گزینه‌های جابه‌جا</b> + {fa(plan.similar ?? 2)} سؤالِ مشابه از همان فصل.</li>
                            <li>قبولیِ هر نوبت: دستِ‌کم {fa(plan.pass ?? 60)}٪ — اگر نشد، {when(plan.retry ?? 1)} دوباره می‌آید.</li>
                            <li>تا {fa(plan.share ?? 50)}٪ امتیازِ از دست‌رفته برمی‌گردد (کسی که از اول درست زده همیشه کمی جلوتر است).</li>
                        </ul>
                    </>
                )}
            </div>

            {empty && (
                <div className="k3-card" style={{ marginTop: 14, textAlign: 'center', lineHeight: 2 }}>
                    <div style={{ fontSize: 40 }}>🌟</div>
                    هنوز اشتباهی برای مرور نداری. بعد از هر آزمون، بازی یا مأموریت، اگر سؤالی را اشتباه بزنی، مرورش خودکار اینجا ساخته می‌شود.
                </div>
            )}

            <Section title="🔥 آماده‌ی امروز" list={ready} />
            <Section title="⏳ در صف (با تاریخ)" list={waiting} />
            <Section title="✅ جبران‌شده" list={done} />
        </ThemedDash>
    );
}

function Section({ title, list }) {
    if (!list.length) return null;
    return (
        <div style={{ marginTop: 16 }}>
            <div style={{ fontWeight: 900, fontSize: 16, margin: '0 4px 8px' }}>{title} <span style={{ opacity: .7 }}>({fa(list.length)})</span></div>
            <div className="rv-list">
                {list.map((r) => <Row key={r.id} r={r} />)}
            </div>
        </div>
    );
}

function Row({ r }) {
    return (
        <div className="k3-card rv-row">
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center', fontSize: 12 }}>
                <span className="k3-chip">{r.source}</span>
                {r.chapter && <span className="k3-chip">📘 {r.chapter}</span>}
                <span style={{ marginInlineStart: 'auto', opacity: .8 }}>{r.created}</span>
            </div>
            <div style={{ fontWeight: 800, marginTop: 6, fontSize: 14 }}>{r.title}</div>
            {r.prompt && <div style={{ fontSize: 13, opacity: .9, marginTop: 4, lineHeight: 1.8 }}>«{r.prompt}»</div>}
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 8, flexWrap: 'wrap', fontSize: 12.5 }}>
                <span className="rv-dots" title={`${fa(r.step)} از ${fa(r.steps)} نوبت`}>
                    {Array.from({ length: r.steps || 0 }).map((_, k) => <i key={k} className={k < r.step ? 'on' : ''} />)}
                </span>
                <span>{fa(r.step)} از {fa(r.steps)} نوبت</span>
                {r.status === 'open' && <span className="k3-chip">{r.ready ? '▶ امروز' : `🗓️ ${r.due}${r.days ? ` (${fa(r.days)} روز دیگر)` : ''}`}</span>}
                {r.cap > 0 && <span style={{ marginInlineStart: 'auto' }}>⚡ {fa(r.recovered)} / {fa(r.cap)}</span>}
            </div>
        </div>
    );
}
