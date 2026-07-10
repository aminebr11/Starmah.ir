import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const TYPES = [['game', '🎮 بازی'], ['exam', '📝 آزمون'], ['homework', '📚 تکلیف'], ['podcast', '🎧 پادکست'], ['online_exam', '💻 آزمون آنلاین'], ['custom', '⭐ فعالیت']];

export default function Activities() {
    const { classroom, students = [], groups = [], activities = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [awardFor, setAwardFor] = useState(null);
    const [picked, setPicked] = useState([]);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ type: 'game', title: '', points: 50, scheduled_at: '', description: '' });
    const submit = (e) => { e.preventDefault(); form.post(route('teacher.activities.store'), { preserveScroll: true, onSuccess: () => form.reset() }); };

    const toggle = (id) => setPicked((p) => p.includes(id) ? p.filter((x) => x !== id) : [...p, id]);
    const award = (actId, ids) => {
        if (!ids.length) { alert('حداقل یک نفر/تیم را انتخاب کن'); return; }
        router.post(route('teacher.activities.award', actId), { student_ids: ids }, { preserveScroll: true, onSuccess: () => { setAwardFor(null); setPicked([]); } });
    };

    if (!classroom) {
        return <DashLayout title="فعالیت‌ها و امتیاز" roleLabel="معلم" menu={teacherMenu} active="activities">
            <div className="panel"><p style={{ color: 'var(--muted)' }}>ابتدا باید یک کلاس داشته باشی.</p></div>
        </DashLayout>;
    }

    return (
        <DashLayout title="فعالیت‌ها و امتیاز" roleLabel="معلم" menu={teacherMenu} active="activities">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.5fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                {/* ساخت فعالیت */}
                <form onSubmit={submit} className="panel">
                    <h3>➕ ثبت فعالیت جدید</h3>
                    <div className="field"><label>نوع فعالیت</label>
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                            {TYPES.map(([v, l]) => (
                                <button type="button" key={v} onClick={() => form.setData('type', v)}
                                    className={`tag ${form.data.type === v ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 12px' }}>{l}</button>
                            ))}
                        </div>
                    </div>
                    <Field label="عنوان" err={form.errors.title}>
                        <input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: بازی ریاضی شنبه" />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="امتیاز" err={form.errors.points}>
                            <input type="number" min="1" max="1000" className="input" value={form.data.points} onChange={(e) => form.setData('points', e.target.value)} />
                        </Field>
                        <Field label="تاریخ (اختیاری)">
                            <JalaliDatePicker value={form.data.scheduled_at} onChange={(v) => form.setData('scheduled_at', v)} />
                        </Field>
                    </div>
                    <button type="submit" disabled={form.processing} className="btn" style={{ width: '100%' }}>ثبت فعالیت</button>
                    <p style={{ color: 'var(--muted)', fontSize: 12, marginTop: 10 }}>بعد از ثبت، با دکمه‌ی «دادن امتیاز» به دانش‌آموزها یا کل تیم امتیاز بده.</p>
                </form>

                {/* لیست فعالیت‌ها */}
                <div className="panel">
                    <h3>🎯 فعالیت‌های کلاس {classroom.name}</h3>
                    {activities.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز فعالیتی ثبت نشده.</p>}
                    {activities.map((a) => (
                        <div key={a.id} style={{ padding: '14px 0', borderBottom: '1px solid var(--line)' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                                <div>
                                    <span className="tag tag-info">{a.type_label}</span>
                                    <b style={{ marginInlineStart: 8 }}>{a.title}</b>
                                    {a.scheduled && <span style={{ color: 'var(--muted)', fontSize: 12, marginInlineStart: 8 }}>📅 {a.scheduled}</span>}
                                </div>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <span className="tag tag-warn">⭐ {fa(a.points)} امتیاز</span>
                                    <span style={{ color: 'var(--muted)', fontSize: 12 }}>{fa(a.awarded)} نفر گرفته</span>
                                    <button onClick={() => { setAwardFor(awardFor === a.id ? null : a.id); setPicked([]); }} className="btn btn-sm">🎁 دادن امتیاز</button>
                                </div>
                            </div>

                            {awardFor === a.id && (
                                <div style={{ marginTop: 12, background: 'var(--cream)', borderRadius: 14, padding: 14 }}>
                                    <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 8 }}>به کدام تیم؟ (همه‌ی اعضای تیم امتیاز می‌گیرند)</div>
                                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 12 }}>
                                        {groups.map((g) => (
                                            <button key={g.name} onClick={() => award(a.id, g.ids)} className="tag tag-ok" style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>
                                                {g.emoji} {g.name} ({fa(g.ids.length)})
                                            </button>
                                        ))}
                                        <button onClick={() => award(a.id, students.map((s) => s.id))} className="tag tag-warn" style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>👥 کل کلاس</button>
                                    </div>
                                    <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 8 }}>یا انتخاب دانش‌آموزها:</div>
                                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                                        {students.map((s) => (
                                            <button key={s.id} onClick={() => toggle(s.id)} className={`tag ${picked.includes(s.id) ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '6px 11px' }}>
                                                {s.emoji} {s.name}
                                            </button>
                                        ))}
                                    </div>
                                    <button onClick={() => award(a.id, picked)} className="btn btn-sm">ثبت امتیاز برای {fa(picked.length)} نفر انتخاب‌شده</button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
