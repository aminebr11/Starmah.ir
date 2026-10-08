import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ThemedDash from '@/Layouts/ThemedDash';
import { makeSubjectPalette, titlesOf, DAY_TINTS, lessonState } from '@/lib/subjects';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Schedule() {
    const { days = [], entries = {}, special = [], today, jtoday } = usePage().props;
    const pal = useMemo(() => makeSubjectPalette([...titlesOf(entries), ...special.map((e) => e.title)]), [entries, special]);
    // هر دقیقه دوباره رسم شود تا «الان» و «بعدی» به‌روز بماند
    const [, tick] = useState(0);
    useEffect(() => { const t = setInterval(() => tick((x) => x + 1), 60000); return () => clearInterval(t); }, []);

    // درس‌های هفته با تعداد (راهنمای رنگ‌ها)
    const legend = useMemo(() => {
        const m = new Map();
        titlesOf(entries).forEach((t) => m.set(t, (m.get(t) || 0) + 1));
        return [...m.entries()];
    }, [entries]);
    const todayList = (entries[today] ?? []).filter((e) => e.kind !== 'recess');
    const nextId = (entries[today] ?? []).find((e) => e.kind !== 'recess' && lessonState(e.time, true) === 'later')?.id;

    return (
        <ThemedDash title="برنامه کلاسی" active="schedule">
            <div className="ks-hero">
                <span className="ks-hero-b b1" /><span className="ks-hero-b b2" /><span className="ks-hero-b b3" />
                <div className="ks-hero-ic">🗓️</div>
                <div>
                    <h2>برنامه‌ی هفتگیِ من</h2>
                    <p>امروز <b>{jtoday}</b>{todayList.length ? <> · {fa(todayList.length)} درس داری 🎒</> : ' · امروز درسی نداری 🎈'}</p>
                </div>
            </div>

            {legend.length > 0 && (
                <div className="ks-legend">
                    {legend.map(([t, n]) => {
                        const c = pal(t);
                        return <span key={t} className="ks-chip" style={{ background: c.grad, color: c.ink }}><i>{c.emoji}</i>{t}<b>{fa(n)}</b></span>;
                    })}
                </div>
            )}

            <div className="ks-week">
                {days.map((d, i) => {
                    const list = entries[i] ?? [];
                    const isToday = i === today;
                    return (
                        <section key={i} className={`ks-day ${isToday ? 'today' : ''}`} style={{ '--day': DAY_TINTS[i % DAY_TINTS.length] }}>
                            <header className="ks-day-h">
                                <span>{d}</span>
                                {isToday && <em>⭐ امروز</em>}
                            </header>
                            {list.length === 0 && <div className="ks-free">🎈<span>برنامه‌ای نیست</span></div>}
                            {list.map((e, k) => {
                                if (e.kind === 'recess') {
                                    return (
                                        <div key={e.id} className="ks-recess">
                                            <span>🍎</span> {e.title}
                                            {e.time && <small dir="ltr">{fa(e.time)}</small>}
                                        </div>
                                    );
                                }
                                const c = pal(e.title);
                                const st = lessonState(e.time, isToday);
                                return (
                                    <div key={e.id} className={`ks-lesson ${st === 'now' ? 'now' : ''} ${st === 'done' ? 'done' : ''}`}
                                        style={{ background: c.grad, color: c.ink, '--sh': c.b, animationDelay: `${k * 60}ms` }}>
                                        <span className="ks-sticker">{c.emoji}</span>
                                        <div className="ks-lesson-b">
                                            <b>{e.title}</b>
                                            {e.time && <small>⏰ <span dir="ltr">{fa(e.time)}</span></small>}
                                        </div>
                                        {e.period && <span className="ks-period">زنگ<b>{fa(e.period)}</b></span>}
                                        {st === 'now' && <span className="ks-flag now">🔔 الان</span>}
                                        {e.id === nextId && <span className="ks-flag next">بعدی ⏭️</span>}
                                    </div>
                                );
                            })}
                        </section>
                    );
                })}
            </div>

            {/* برنامه‌های تاریخ‌دار — جدا و پس از هفته‌ی همیشگی */}
            {special.length > 0 && (
                <div className="ks-special">
                    <div className="ks-special-h">📌 برنامه‌های تاریخ‌دارِ پیشِ رو</div>
                    <div className="ks-special-s">این‌ها فقط در همین تاریخ‌ها برگزار می‌شوند.</div>
                    <div style={{ overflowX: 'auto' }}>
                        <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 420 }}>
                            <thead>
                                <tr className="ks-special-th">
                                    <th style={thD}>تاریخ</th>
                                    <th style={thD}>روز</th>
                                    <th style={thD}>ساعت</th>
                                    <th style={thD}>برنامه</th>
                                </tr>
                            </thead>
                            <tbody>
                                {special.map((e) => (
                                    <tr key={e.id} className={e.is_today ? 'today' : ''}>
                                        <td style={tdD}>
                                            <b>{fa(e.jdate)}</b>
                                            {e.is_today && <span className="ks-today-tag">⭐ امروز</span>}
                                        </td>
                                        <td style={tdD}>{e.day}</td>
                                        <td style={tdD}><span dir="ltr" style={{ display: 'inline-block' }}>{fa(e.time || '—')}</span></td>
                                        <td style={tdD}>{e.kind === 'recess' ? `🍎 ${e.title}` : <span className="ks-chip sm" style={{ background: pal(e.title).grad, color: pal(e.title).ink }}><i>{pal(e.title).emoji}</i>{e.title}</span>}{e.period ? ` · زنگ ${fa(e.period)}` : ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </ThemedDash>
    );
}

const thD = { textAlign: 'start', padding: '7px 9px', fontWeight: 700 };
const tdD = { padding: '9px 9px', fontSize: 13 };
