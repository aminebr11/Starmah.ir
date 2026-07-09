import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

// رنگ ثابت برای هر درس (بر اساس نام)
const PALETTE = [
    ['#fff3d6', '#8a5a00'], ['#dcebff', '#1b4b8a'], ['#d4f5ef', '#0f766e'],
    ['#ffe0ec', '#a01a4a'], ['#e9e4ff', '#4c2fb0'], ['#e6f7d9', '#3a6b12'],
    ['#ffe4d1', '#a04413'], ['#d9f0ff', '#0b6ea8'],
];
const colorFor = (s) => { let h = 0; for (const c of (s || '')) h = (h * 31 + c.charCodeAt(0)) >>> 0; return PALETTE[h % PALETTE.length]; };

export default function Schedule() {
    const { classroom, days = [], entries = {}, books = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ day_of_week: 0, title: '', time_range: '', period: 1 });
    const submit = (e) => { e.preventDefault(); if (!form.data.title) return; form.post(route('teacher.schedule.store'), { preserveScroll: true, onSuccess: () => form.setData('title', '') }); };
    const del = (id) => router.delete(route('teacher.schedule.destroy', id), { preserveScroll: true });

    if (!classroom) {
        return <DashLayout title="برنامه‌ی کلاسی" roleLabel="معلم" menu={teacherMenu} active="schedule">
            <div className="panel"><p style={{ color: 'var(--muted)' }}>ابتدا باید یک کلاس داشته باشی.</p></div>
        </DashLayout>;
    }

    return (
        <DashLayout title="برنامه‌ی کلاسی" roleLabel="معلم" menu={teacherMenu} active="schedule">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <form onSubmit={submit} className="panel">
                <h3>➕ افزودن درس به برنامه — {classroom.name}{classroom.grade ? ` (پایه ${classroom.grade})` : ''}</h3>
                {books.length > 0 && (
                    <div style={{ marginBottom: 12 }}>
                        <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 6, color: 'var(--muted)' }}>انتخاب سریع از دروس پایه:</div>
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                            {books.map((b, i) => (
                                <button type="button" key={i} onClick={() => form.setData('title', b.name)}
                                    className={`tag ${form.data.title === b.name ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 12px' }}>
                                    {b.icon} {b.name}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr 1fr 0.7fr auto', gap: 10, alignItems: 'end' }} className="sched-form">
                    <div className="field"><label>روز</label>
                        <select className="input" value={form.data.day_of_week} onChange={(e) => form.setData('day_of_week', +e.target.value)}>
                            {days.map((d, i) => <option key={i} value={i}>{d}</option>)}
                        </select>
                    </div>
                    <div className="field"><label>درس / موضوع</label>
                        <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: ریاضی" />
                    </div>
                    <div className="field"><label>ساعت</label>
                        <input className="input" value={form.data.time_range} onChange={(e) => form.setData('time_range', e.target.value)} placeholder="۸:۰۰ - ۹:۰۰" />
                    </div>
                    <div className="field"><label>زنگ</label>
                        <input type="number" min="1" max="12" className="input" value={form.data.period} onChange={(e) => form.setData('period', e.target.value)} />
                    </div>
                    <button type="submit" disabled={form.processing} className="btn">افزودن</button>
                </div>
            </form>

            <div className="panel">
                <h3>🗓️ برنامه‌ی هفته</h3>
                <div className="sched-board">
                    {days.map((d, i) => (
                        <div key={i} className="sched-day">
                            <div className="sched-day-head">{d}</div>
                            {(entries[i] ?? []).map((e) => {
                                const [bg, fg] = colorFor(e.title);
                                return (
                                    <div key={e.id} className="sched-card" style={{ background: bg, color: fg, borderColor: fg + '33' }}>
                                        {e.period && <span className="sched-period">زنگ {fa(e.period)}</span>}
                                        <div style={{ fontWeight: 800, fontSize: 13.5 }}>{e.title}</div>
                                        {e.time && <div style={{ fontSize: 11, opacity: .85 }}>⏰ {e.time}</div>}
                                        <button onClick={() => del(e.id)} className="sched-del" title="حذف">✕</button>
                                    </div>
                                );
                            })}
                            {!(entries[i] ?? []).length && <div className="sched-empty">—</div>}
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
