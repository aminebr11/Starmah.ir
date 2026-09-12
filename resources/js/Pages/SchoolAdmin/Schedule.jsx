import { usePage } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const PALETTE = [
    ['#fff3d6', '#8a5a00'], ['#dcebff', '#1b4b8a'], ['#d4f5ef', '#0f766e'],
    ['#ffe0ec', '#a01a4a'], ['#e9e4ff', '#4c2fb0'], ['#e6f7d9', '#3a6b12'],
    ['#ffe4d1', '#a04413'], ['#d9f0ff', '#0b6ea8'],
];
const colorFor = (s) => { let h = 0; for (const c of (s || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; };

export default function Schedule() {
    const { days = [], classes = [] } = usePage().props;

    return (
        <DashLayout title="برنامه‌ی کلاس‌ها" roleLabel="مدیر مدرسه" menu={schoolMenu} active="schedule">
            {classes.length === 0 && <div className="panel"><p style={{ color: 'var(--muted)' }}>هنوز کلاسی ساخته نشده.</p></div>}
            {classes.map((c) => {
                const total = Object.values(c.entries || {}).reduce((a, g) => a + g.length, 0);
                return (
                    <div key={c.id} className="panel">
                        <h3>🏛️ {c.name}{c.grade ? ` — پایه ${c.grade}` : ''}
                            <span style={{ marginInlineStart: 'auto', color: 'var(--muted)', fontSize: 13, fontWeight: 400 }}>معلم: {c.teacher ?? '—'}</span>
                        </h3>
                        {total === 0 ? (
                            <p style={{ color: 'var(--muted)' }}>برنامه‌ای برای این کلاس ثبت نشده.</p>
                        ) : (
                            <div className="sched-board">
                                {days.map((d, i) => (
                                    <div key={i} className="sched-day">
                                        <div className="sched-day-head">{d}</div>
                                        {(c.entries[i] ?? []).map((e) => {
                                            if (e.kind === 'recess') {
                                                return <div key={e.id} className="sched-recess">☕ {e.title}{e.time && <span dir="ltr" style={{ opacity: .8, fontSize: 10, display: 'block' }}>{fa(e.time)}</span>}</div>;
                                            }
                                            const [bg, fg] = colorFor(e.title);
                                            return (
                                                <div key={e.id} className="sched-card" style={{ background: bg, color: fg, borderColor: fg + '33' }}>
                                                    {e.period && <span className="sched-period">زنگ {fa(e.period)}</span>}
                                                    <div style={{ fontWeight: 800, fontSize: 13 }}>{e.title}</div>
                                                    {e.time && <div style={{ fontSize: 11, opacity: .85 }}>⏰ <span dir="ltr" style={{ display: 'inline-block' }}>{fa(e.time)}</span></div>}
                                                </div>
                                            );
                                        })}
                                        {!(c.entries[i] ?? []).length && <div className="sched-empty">—</div>}
                                    </div>
                                ))}
                            </div>
                        )}

                        {(c.special || []).length > 0 && (
                            <div style={{ marginTop: 12 }}>
                                <div style={{ fontWeight: 800, fontSize: 13.5, marginBottom: 6 }}>📌 تاریخ‌های خاصِ پیشِ رو</div>
                                <div style={{ overflowX: 'auto' }}>
                                    <table style={{ width: '100%', borderCollapse: 'collapse', minWidth: 460 }}>
                                        <thead>
                                            <tr style={{ background: '#f6f8fc' }}>
                                                <th style={thS}>تاریخ</th>
                                                <th style={thS}>روز</th>
                                                <th style={thS}>ساعت</th>
                                                <th style={thS}>برنامه</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {c.special.map((e) => (
                                                <tr key={e.id} style={{ borderTop: '1px solid var(--line)', background: e.is_today ? '#fffbe8' : 'transparent' }}>
                                                    <td style={tdS}><b>{fa(e.jdate)}</b>{e.is_today && <span className="tag tag-warn" style={{ marginInlineStart: 6, fontSize: 10 }}>امروز</span>}</td>
                                                    <td style={tdS}>{e.day}</td>
                                                    <td style={tdS}><span dir="ltr" style={{ display: 'inline-block' }}>{fa(e.time || '—')}</span></td>
                                                    <td style={tdS}>{e.kind === 'recess' ? `☕ ${e.title}` : e.title}{e.period ? ` · زنگ ${fa(e.period)}` : ''}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                );
            })}
        </DashLayout>
    );
}

const thS = { textAlign: 'start', padding: '9px 10px', fontSize: 12.5, color: 'var(--muted)', fontWeight: 700 };
const tdS = { padding: '9px 10px', fontSize: 13 };
