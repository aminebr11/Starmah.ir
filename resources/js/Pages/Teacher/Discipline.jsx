import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Discipline() {
    const { students = [], topics = [], records = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [selTopics, setSelTopics] = useState([]);
    const [selStudents, setSelStudents] = useState([]);
    const [note, setNote] = useState('');
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const topicForm = useForm({ name: '', kind: 'negative', points: 5 });
    const addTopic = (e) => { e.preventDefault(); topicForm.post(route('teacher.discipline.topics'), { preserveScroll: true, onSuccess: () => topicForm.setData('name', '') }); };
    const delTopic = (id) => router.delete(route('teacher.discipline.topics.destroy', id), { preserveScroll: true });

    const toggle = (arr, set, id) => set(arr.includes(id) ? arr.filter((x) => x !== id) : [...arr, id]);
    const submit = () => {
        if (!selTopics.length || !selStudents.length) { alert('موضوع و دانش‌آموز را انتخاب کن'); return; }
        router.post(route('teacher.discipline.record'), { topic_ids: selTopics, student_ids: selStudents, note }, {
            preserveScroll: true, onSuccess: () => { setSelTopics([]); setSelStudents([]); setNote(''); },
        });
    };

    const pos = topics.filter((t) => t.kind === 'positive');
    const neg = topics.filter((t) => t.kind === 'negative');

    return (
        <DashLayout title="موارد انضباطی" roleLabel="معلم" menu={teacherMenu} active="discipline">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div className="panel">
                <h3>⭐ ثبت مورد انضباطی</h3>
                <div style={{ fontWeight: 700, fontSize: 13, margin: '6px 0 8px' }}>۱) موضوع را انتخاب کن (یک یا چند):</div>
                <div style={{ marginBottom: 6, color: '#2bb673', fontWeight: 700, fontSize: 13 }}>🌟 تشویقی</div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>
                    {pos.map((t) => <Topic key={t.id} t={t} on={() => toggle(selTopics, setSelTopics, t.id)} sel={selTopics.includes(t.id)} del={() => delTopic(t.id)} />)}
                    {pos.length === 0 && <span style={{ color: 'var(--muted)', fontSize: 13 }}>موضوع تشویقی نساخته‌ای.</span>}
                </div>
                <div style={{ marginBottom: 6, color: '#e8505b', fontWeight: 700, fontSize: 13 }}>⚠️ تخلف</div>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 14 }}>
                    {neg.map((t) => <Topic key={t.id} t={t} on={() => toggle(selTopics, setSelTopics, t.id)} sel={selTopics.includes(t.id)} del={() => delTopic(t.id)} />)}
                    {neg.length === 0 && <span style={{ color: 'var(--muted)', fontSize: 13 }}>موضوع تخلف نساخته‌ای.</span>}
                </div>

                <div style={{ fontWeight: 700, fontSize: 13, margin: '6px 0 8px' }}>۲) دانش‌آموز(ان) را انتخاب کن:</div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 8 }}>
                    <button onClick={() => setSelStudents(selStudents.length === students.length ? [] : students.map((s) => s.id))} className="tag tag-info" style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 13px' }}>
                        {selStudents.length === students.length ? 'لغو همه' : '✓ کل کلاس'}
                    </button>
                    {students.map((s) => (
                        <button key={s.id} onClick={() => toggle(selStudents, setSelStudents, s.id)} className={`tag ${selStudents.includes(s.id) ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '7px 11px' }}>
                            {s.emoji} {s.name}
                        </button>
                    ))}
                </div>
                <input className="input" value={note} onChange={(e) => setNote(e.target.value)} placeholder="یادداشت (اختیاری)" style={{ marginBottom: 12 }} />
                <button onClick={submit} className="btn">ثبت برای {fa(selStudents.length)} دانش‌آموز ({fa(selTopics.length)} موضوع)</button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.6fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                <form onSubmit={addTopic} className="panel">
                    <h3>➕ ساخت موضوع جدید</h3>
                    <div className="field"><label>عنوان موضوع</label>
                        <input className="input" value={topicForm.data.name} onChange={(e) => topicForm.setData('name', e.target.value)} placeholder="مثلاً: نظم در صف / فراموشی تکلیف" />
                    </div>
                    <div className="field"><label>نوع</label>
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button type="button" onClick={() => topicForm.setData('kind', 'positive')} className={`tag ${topicForm.data.kind === 'positive' ? 'tag-ok' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>🌟 تشویقی</button>
                            <button type="button" onClick={() => topicForm.setData('kind', 'negative')} className={`tag ${topicForm.data.kind === 'negative' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '8px 14px' }}>⚠️ تخلف</button>
                        </div>
                    </div>
                    <div className="field"><label>امتیاز ({topicForm.data.kind === 'negative' ? 'منفی می‌شود' : 'مثبت'})</label>
                        <input type="number" min="1" className="input" value={topicForm.data.points} onChange={(e) => topicForm.setData('points', e.target.value)} />
                    </div>
                    <button type="submit" disabled={topicForm.processing} className="btn" style={{ width: '100%' }}>ساخت موضوع</button>
                </form>

                <div className="panel">
                    <h3>📋 سوابق ثبت‌شده</h3>
                    {records.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز موردی ثبت نشده.</p>}
                    {records.length > 0 && (
                        <table className="tbl">
                            <thead><tr><th>دانش‌آموز</th><th>موضوع</th><th>امتیاز</th><th>تاریخ</th></tr></thead>
                            <tbody>{records.map((r, i) => (
                                <tr key={i}>
                                    <td style={{ fontWeight: 700 }}>{r.student}</td>
                                    <td>{r.kind === 'positive' ? '🌟' : '⚠️'} {r.title}</td>
                                    <td><b style={{ color: r.points >= 0 ? '#2bb673' : '#e8505b' }}>{r.points >= 0 ? '+' : ''}{fa(r.points)}</b></td>
                                    <td style={{ color: 'var(--muted)', fontSize: 12 }}>{r.date}</td>
                                </tr>
                            ))}</tbody>
                        </table>
                    )}
                </div>
            </div>
        </DashLayout>
    );
}

function Topic({ t, on, sel, del }) {
    return (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, background: sel ? (t.kind === 'positive' ? '#e3f7ec' : '#fdeaea') : '#f0f3f9', border: `1.5px solid ${sel ? (t.kind === 'positive' ? '#2bb673' : '#e8505b') : 'transparent'}`, borderRadius: 20, padding: '6px 4px 6px 12px' }}>
            <button onClick={on} style={{ border: 0, background: 'none', cursor: 'pointer', fontFamily: 'inherit', fontWeight: 700, fontSize: 13 }}>
                {t.name} <b style={{ color: t.points >= 0 ? '#2bb673' : '#e8505b' }}>({t.points >= 0 ? '+' : ''}{t.points})</b>
            </button>
            <button onClick={del} title="حذف موضوع" style={{ border: 0, background: 'none', cursor: 'pointer', color: '#b9c0cf', fontSize: 12 }}>✕</button>
        </span>
    );
}
