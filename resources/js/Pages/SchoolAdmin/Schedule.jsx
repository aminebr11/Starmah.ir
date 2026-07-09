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
                                            const [bg, fg] = colorFor(e.title);
                                            return (
                                                <div key={e.id} className="sched-card" style={{ background: bg, color: fg, borderColor: fg + '33' }}>
                                                    {e.period && <span className="sched-period">زنگ {fa(e.period)}</span>}
                                                    <div style={{ fontWeight: 800, fontSize: 13 }}>{e.title}</div>
                                                    {e.time && <div style={{ fontSize: 11, opacity: .85 }}>⏰ {e.time}</div>}
                                                </div>
                                            );
                                        })}
                                        {!(c.entries[i] ?? []).length && <div className="sched-empty">—</div>}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </DashLayout>
    );
}
