import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Schedule() {
    const { classroom, days = [], entries = {}, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ day_of_week: 0, title: '', time_range: '', period: 1 });
    const submit = (e) => { e.preventDefault(); form.post(route('teacher.schedule.store'), { preserveScroll: true, onSuccess: () => form.setData('title', '') }); };
    const del = (id) => router.delete(route('teacher.schedule.destroy', id), { preserveScroll: true });

    return (
        <DashLayout title="برنامه کلاسی" roleLabel="معلم" menu={teacherMenu} active="schedule">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <form onSubmit={submit} className="panel">
                <h3>➕ افزودن به برنامه {classroom ? `— ${classroom.name}` : ''}</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr 1fr auto', gap: 10, alignItems: 'end' }} className="sched-form">
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
                    <button type="submit" disabled={form.processing} className="btn">افزودن</button>
                </div>
            </form>

            <div className="panel">
                <h3>🗓️ برنامه‌ی هفته</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(150px,1fr))', gap: 12 }}>
                    {days.map((d, i) => (
                        <div key={i} style={{ background: 'var(--cream)', borderRadius: 14, padding: 12, minHeight: 100 }}>
                            <div style={{ fontWeight: 800, color: 'var(--navy-700)', marginBottom: 8, textAlign: 'center' }}>{d}</div>
                            {(entries[i] ?? []).map((e) => (
                                <div key={e.id} style={{ background: '#fff', border: '1px solid var(--line)', borderRadius: 10, padding: '8px 10px', marginBottom: 6, position: 'relative' }}>
                                    <div style={{ fontWeight: 700, fontSize: 13 }}>{e.title}</div>
                                    {e.time && <div style={{ color: 'var(--muted)', fontSize: 11 }}>{e.time}</div>}
                                    <button onClick={() => del(e.id)} style={{ position: 'absolute', top: 4, insetInlineStart: 4, border: 0, background: 'none', color: '#e8505b', cursor: 'pointer', fontSize: 12 }}>✕</button>
                                </div>
                            ))}
                            {!(entries[i] ?? []).length && <div style={{ color: 'var(--muted-2)', fontSize: 12, textAlign: 'center' }}>—</div>}
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}
