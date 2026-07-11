import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect, Fragment } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

const TYPES = [
    { v: 'descriptive', t: '📝 توصیفی' },
    { v: 'numeric', t: '🔢 عددی' },
    { v: 'homework', t: '📚 تکلیف' },
];
// رنگ هر ارزیابی
const RATING_COLOR = {
    'خیلی خوب': ['#e3f7ec', '#177a45'], 'خوب': ['#e7f0ff', '#1b4b8a'], 'قابل قبول': ['#fff3d6', '#8a5a00'],
    'نیاز به تلاش': ['#ffe9d6', '#a04413'], 'غایب': ['#fdecec', '#c0392b'],
    'کامل': ['#e3f7ec', '#177a45'], 'ناقص': ['#fff3d6', '#8a5a00'], 'انجام نداده': ['#fdecec', '#c0392b'],
};
const ICON = { 'غایب': '🚫', 'کامل': '✅', 'ناقص': '⚠️', 'انجام نداده': '❌' };

export default function Gradebook() {
    const { classroom, subjects = [], students = [], activities = [], descriptiveOptions = [], homeworkOptions = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [tab, setTab] = useState('new'); // new | history
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    // فرم فعالیت جدید
    const blank = () => Object.fromEntries(students.map((s) => [s.id, { score: '', text: '', feedback: '' }]));
    const form = useForm({ title: '', score_type: 'descriptive', lesson: '', topic: '', max: 20, date: '', grades: {} });
    const [rows, setRows] = useState(blank());
    useEffect(() => { setRows(blank()); }, [students.length]);

    const setRow = (sid, patch) => setRows((r) => ({ ...r, [sid]: { ...r[sid], ...patch } }));
    const applyAll = (field, val) => setRows((r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, { ...v, [field]: val }])));

    const submit = (e) => {
        e.preventDefault();
        const grades = students.map((s) => ({ student_id: s.id, ...rows[s.id] }));
        router.post(route('teacher.gradebook.activities'), { ...form.data, grades }, {
            preserveScroll: true,
            onSuccess: () => { form.setData({ ...form.data, title: '', topic: '' }); setRows(blank()); setTab('history'); },
        });
    };
    const del = (id) => { if (confirm('این فعالیت و نمراتش حذف شود؟')) router.delete(route('teacher.gradebook.columns.destroy', id), { preserveScroll: true }); };

    const st = form.data.score_type;
    const opts = st === 'descriptive' ? descriptiveOptions : st === 'homework' ? homeworkOptions : [];

    // فیلترهای سوابق
    const [fLesson, setFLesson] = useState('');
    const [fType, setFType] = useState('');
    const [fStudent, setFStudent] = useState('');
    const [fq, setFq] = useState('');
    const filtered = activities.filter((a) =>
        (!fLesson || a.lesson === fLesson) &&
        (!fType || a.score_type === fType) &&
        (!fStudent || a.grades?.[fStudent]) &&
        (!fq || (a.title || '').includes(fq) || (a.topic || '').includes(fq)));

    // ویرایشِ یک فعالیت
    const [editAct, setEditAct] = useState(null);
    const [erows, setErows] = useState({});
    const optsFor = (type) => type === 'descriptive' ? descriptiveOptions : type === 'homework' ? homeworkOptions : [];
    const startEditAct = (a) => {
        const r = {}; students.forEach((s) => { const g = a.grades?.[s.id]; r[s.id] = { score: g?.score ?? '', text: g?.text ?? '', feedback: g?.feedback ?? '' }; });
        setErows(r); setEditAct(a.id);
    };
    const setERow = (sid, patch) => setErows((r) => ({ ...r, [sid]: { ...r[sid], ...patch } }));
    const saveEditAct = (a) => {
        const grades = students.map((s) => ({ student_id: s.id, ...erows[s.id] }));
        router.post(route('teacher.gradebook.grades', a.id), { grades }, { preserveScroll: true, onSuccess: () => setEditAct(null) });
    };

    if (!classroom) return <DashLayout title="دفتر کلاسی" roleLabel="معلم" menu={teacherMenu} active="gradebook"><div className="panel">ابتدا کلاس بساز.</div></DashLayout>;

    return (
        <DashLayout title="دفتر کلاسی" roleLabel="معلم" menu={teacherMenu} active="gradebook">
            {banner && <div className="panel no-print" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {/* تب‌ها */}
            <div className="panel no-print" style={{ display: 'flex', gap: 8, padding: 10 }}>
                <button onClick={() => setTab('new')} className={`btn btn-sm ${tab === 'new' ? '' : 'btn-ghost'}`}>➕ ثبت فعالیت</button>
                <button onClick={() => setTab('history')} className={`btn btn-sm ${tab === 'history' ? '' : 'btn-ghost'}`}>📚 سوابق نمرات ({fa(activities.length)})</button>
            </div>

            {tab === 'new' && (
                <form onSubmit={submit}>
                    {/* گام ۱: مشخصات فعالیت */}
                    <div className="panel">
                        <h3 style={{ marginTop: 0 }}>① مشخصات فعالیت <span style={{ fontSize: 12, fontWeight: 500, color: 'var(--muted)' }}>درس، موضوع و نوع نمره‌دهی</span></h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12 }}>
                            <Field label="📖 درس">
                                <select className="input" value={form.data.lesson} onChange={(e) => { form.setData('lesson', e.target.value); if (!form.data.title) form.setData('title', e.target.value); }}>
                                    <option value="">— انتخاب درس —</option>
                                    {subjects.map((s) => <option key={s.name} value={s.name}>{s.icon} {s.name}</option>)}
                                </select>
                            </Field>
                            <Field label="📋 موضوع (اختیاری)"><input className="input" value={form.data.topic} onChange={(e) => form.setData('topic', e.target.value)} placeholder="مثلاً: جمع و تفریق" /></Field>
                            <Field label="🎯 نوع نمره">
                                <select className="input" value={form.data.score_type} onChange={(e) => form.setData('score_type', e.target.value)}>
                                    {TYPES.map((t) => <option key={t.v} value={t.v}>{t.t}</option>)}
                                </select>
                            </Field>
                            {st === 'numeric' && <Field label="بارم (حداکثر نمره)"><input type="number" className="input" value={form.data.max} onChange={(e) => form.setData('max', e.target.value)} /></Field>}
                            <Field label="🏷️ عنوان" err={form.errors.title}><input className="input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="مثلاً: ارزیابی کلاسی" /></Field>
                            <Field label="📅 تاریخ (پیش‌فرض امروز)"><JalaliDatePicker value={form.data.date} onChange={(v) => form.setData('date', v)} placeholder="امروز" /></Field>
                        </div>
                    </div>

                    {/* گام ۲: نمرات دانش‌آموزان */}
                    <div className="panel">
                        <h3 style={{ marginTop: 0 }}>② نمره و بازخورد دانش‌آموزان</h3>

                        {/* اعمال گروهی */}
                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
                            {st !== 'numeric' && opts.map((o) => (
                                <button type="button" key={o} onClick={() => applyAll('text', o)} className="btn btn-ghost btn-sm">همه: {ICON[o] || ''} {o}</button>
                            ))}
                            {st === 'numeric' && <>
                                <button type="button" onClick={() => { const v = prompt('نمره برای همه:'); if (v !== null) applyAll('score', v); }} className="btn btn-ghost btn-sm">اعمال به همه</button>
                                <button type="button" onClick={() => applyAll('score', '')} className="btn btn-ghost btn-sm">پاک کردن همه</button>
                            </>}
                        </div>

                        <div style={{ overflowX: 'auto' }}>
                            <table className="tbl">
                                <thead><tr><th>#</th><th>دانش‌آموز</th><th>{st === 'numeric' ? 'نمره' : 'ارزیابی'}</th><th style={{ minWidth: 160 }}>بازخورد</th></tr></thead>
                                <tbody>
                                    {students.map((s, i) => (
                                        <tr key={s.id}>
                                            <td>{fa(i + 1)}</td>
                                            <td style={{ fontWeight: 700 }}>{s.name}</td>
                                            <td>
                                                {st === 'numeric' ? (
                                                    <input type="number" step="0.25" max={form.data.max} value={rows[s.id]?.score ?? ''} onChange={(e) => setRow(s.id, { score: e.target.value })} className="grade-input" placeholder="—" style={{ width: 80 }} />
                                                ) : (
                                                    <div style={{ display: 'flex', gap: 5, flexWrap: 'wrap' }}>
                                                        {opts.map((o) => {
                                                            const on = rows[s.id]?.text === o; const [bg, fg] = RATING_COLOR[o] || ['#eee', '#333'];
                                                            return <button type="button" key={o} onClick={() => setRow(s.id, { text: on ? '' : o })}
                                                                style={{ cursor: 'pointer', fontFamily: 'inherit', fontSize: 12, fontWeight: 700, padding: '6px 10px', borderRadius: 9, border: on ? `2px solid ${fg}` : '1px solid var(--line)', background: on ? bg : '#fff', color: on ? fg : 'var(--muted)' }}>
                                                                {ICON[o] || ''} {o}</button>;
                                                        })}
                                                    </div>
                                                )}
                                            </td>
                                            <td><input value={rows[s.id]?.feedback ?? ''} onChange={(e) => setRow(s.id, { feedback: e.target.value })} className="grade-input" style={{ width: '100%' }} placeholder="بازخورد (اختیاری)" /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" disabled={form.processing || !form.data.title} className="btn" style={{ marginTop: 14 }}>💾 ثبت فعالیت و نمرات</button>
                    </div>
                </form>
            )}

            {tab === 'history' && (
                <div className="panel printable">
                    <div className="no-print" style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 6 }}>
                        <h3 style={{ margin: 0 }}>📚 سوابق نمرات — {classroom?.name}</h3>
                        <button onClick={() => window.print()} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>🖨️ پرینت</button>
                    </div>
                    <h3 className="print-title">دفتر کلاسی — {classroom?.name}</h3>

                    {/* فیلترها */}
                    {activities.length > 0 && (
                        <div className="no-print" style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginBottom: 12, background: 'var(--cream)', padding: 12, borderRadius: 12 }}>
                            <select className="input" style={{ width: 'auto' }} value={fLesson} onChange={(e) => setFLesson(e.target.value)}>
                                <option value="">همه‌ی درس‌ها</option>
                                {[...new Set(activities.map((a) => a.lesson).filter(Boolean))].map((l) => <option key={l} value={l}>{l}</option>)}
                            </select>
                            <select className="input" style={{ width: 'auto' }} value={fType} onChange={(e) => setFType(e.target.value)}>
                                <option value="">همه‌ی انواع</option>
                                {TYPES.map((t) => <option key={t.v} value={t.v}>{t.t}</option>)}
                            </select>
                            <select className="input" style={{ width: 'auto' }} value={fStudent} onChange={(e) => setFStudent(e.target.value)}>
                                <option value="">همه‌ی دانش‌آموزان</option>
                                {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                            <input className="input" style={{ width: 'auto', flex: 1, minWidth: 140 }} value={fq} onChange={(e) => setFq(e.target.value)} placeholder="🔍 عنوان یا موضوع…" />
                            {(fLesson || fType || fStudent || fq) && <button onClick={() => { setFLesson(''); setFType(''); setFStudent(''); setFq(''); }} className="btn btn-ghost btn-sm">پاک کردن</button>}
                        </div>
                    )}

                    {activities.length === 0 && <p className="no-print" style={{ color: 'var(--muted)' }}>هنوز فعالیتی ثبت نشده. از تب «ثبت فعالیت» شروع کن.</p>}
                    {activities.length > 0 && filtered.length === 0 && <p className="no-print" style={{ color: 'var(--muted)' }}>با این فیلترها موردی پیدا نشد.</p>}

                    {filtered.map((a) => {
                        const editing = editAct === a.id;
                        const rowStudents = fStudent ? students.filter((s) => String(s.id) === String(fStudent)) : students;
                        const aopts = optsFor(a.score_type);
                        return (
                            <div key={a.id} style={{ border: '1px solid var(--line)', borderRadius: 14, padding: 14, marginBottom: 12 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', marginBottom: 8 }}>
                                    <b>{a.title}</b>
                                    {a.lesson && <span className="tag tag-info">{a.lesson}</span>}
                                    {a.topic && <span style={{ color: 'var(--muted)', fontSize: 12 }}>· {a.topic}</span>}
                                    <span className="tag" style={{ background: '#eef2fb', color: 'var(--navy-700)' }}>{TYPES.find((t) => t.v === a.score_type)?.t || a.score_type}</span>
                                    {a.jdate && <span style={{ color: 'var(--muted)', fontSize: 12 }}>📅 {a.jdate}</span>}
                                    <div className="no-print" style={{ marginInlineStart: 'auto', display: 'flex', gap: 6 }}>
                                        {editing ? <>
                                            <button onClick={() => saveEditAct(a)} className="btn btn-sm">💾 ذخیره</button>
                                            <button onClick={() => setEditAct(null)} className="btn btn-ghost btn-sm">انصراف</button>
                                        </> : <>
                                            <button onClick={() => startEditAct(a)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                            <button onClick={() => del(a.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️ حذف</button>
                                        </>}
                                    </div>
                                </div>
                                <div style={{ overflowX: 'auto' }}>
                                    <table className="tbl">
                                        <thead><tr><th>دانش‌آموز</th><th>{a.score_type === 'numeric' ? `نمره (از ${fa(a.max)})` : 'ارزیابی'}</th><th>بازخورد</th></tr></thead>
                                        <tbody>
                                            {rowStudents.map((s) => {
                                                const g = a.grades?.[s.id];
                                                if (editing) {
                                                    const er = erows[s.id] || {};
                                                    return (
                                                        <tr key={s.id}>
                                                            <td style={{ fontWeight: 700 }}>{s.name}</td>
                                                            <td>
                                                                {a.score_type === 'numeric' ? (
                                                                    <input type="number" step="0.25" max={a.max} value={er.score ?? ''} onChange={(e) => setERow(s.id, { score: e.target.value })} className="grade-input" style={{ width: 80 }} placeholder="—" />
                                                                ) : (
                                                                    <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                                                                        {aopts.map((o) => { const on = er.text === o; const rc = RATING_COLOR[o] || ['#eee', '#333']; return <button type="button" key={o} onClick={() => setERow(s.id, { text: on ? '' : o })} style={{ cursor: 'pointer', fontFamily: 'inherit', fontSize: 11.5, fontWeight: 700, padding: '5px 8px', borderRadius: 8, border: on ? `2px solid ${rc[1]}` : '1px solid var(--line)', background: on ? rc[0] : '#fff', color: on ? rc[1] : 'var(--muted)' }}>{ICON[o] || ''} {o}</button>; })}
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td><input value={er.feedback ?? ''} onChange={(e) => setERow(s.id, { feedback: e.target.value })} className="grade-input" style={{ width: '100%' }} placeholder="بازخورد" /></td>
                                                        </tr>
                                                    );
                                                }
                                                const val = a.score_type === 'numeric' ? (g?.score != null ? fa(g.score) : '—') : (g?.text || '—');
                                                const col = RATING_COLOR[g?.text];
                                                return (
                                                    <tr key={s.id}>
                                                        <td style={{ fontWeight: 700 }}>{s.name}</td>
                                                        <td>{col ? <span style={{ background: col[0], color: col[1], borderRadius: 8, padding: '3px 10px', fontWeight: 700, fontSize: 13 }}>{ICON[g.text] || ''} {val}</span> : val}</td>
                                                        <td style={{ color: 'var(--muted)', fontSize: 13 }}>{g?.feedback || '—'}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field" style={{ margin: 0 }}><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
