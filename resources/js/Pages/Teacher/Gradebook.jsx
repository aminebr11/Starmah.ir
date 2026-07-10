import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Gradebook() {
    const { classroom, subjects = [], students = [], columns = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [showCol, setShowCol] = useState(false);
    const [edits, setEdits] = useState({});
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const colForm = useForm({ title: '', type: 'numeric', max: 20 });
    const addCol = (e) => { e.preventDefault(); colForm.post(route('teacher.gradebook.columns'), { preserveScroll: true, onSuccess: () => { colForm.reset(); setShowCol(false); } }); };

    const val = (c, sid, field) => edits[c.id]?.[sid]?.[field] ?? c.grades?.[sid]?.[field] ?? '';
    const setVal = (cid, sid, field, v) => setEdits((e) => ({ ...e, [cid]: { ...e[cid], [sid]: { ...e[cid]?.[sid], [field]: v } } }));

    const saveCol = (c) => {
        const colEdits = edits[c.id] ?? {};
        const grades = Object.entries(colEdits).map(([sid, v]) => ({ student_id: +sid, score: v.score ?? null, text: v.text ?? null }));
        if (!grades.length) return;
        router.post(route('teacher.gradebook.grades', c.id), { grades }, { preserveScroll: true, onSuccess: () => setEdits((e) => ({ ...e, [c.id]: {} })) });
    };
    const delCol = (id) => { if (confirm('این ستون نمره حذف شود؟')) router.delete(route('teacher.gradebook.columns.destroy', id), { preserveScroll: true }); };

    return (
        <DashLayout title="دفتر کلاسی" roleLabel="معلم" menu={teacherMenu} active="gradebook"
            actions={<><button onClick={() => window.print()} className="btn btn-ghost btn-sm no-print">🖨️ پرینت</button><button onClick={() => setShowCol(!showCol)} className="btn btn-sm no-print">➕ ستون نمره</button></>}>
            {banner && <div className="panel no-print" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            {showCol && (
                <form onSubmit={addCol} className="panel no-print">
                    <h3>➕ ستون نمره‌ی جدید</h3>
                    {subjects.length > 0 && (
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 10 }}>
                            {subjects.map((s) => (
                                <button type="button" key={s.name} className="tag tag-info" style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit' }}
                                    onClick={() => colForm.setData('title', s.name)}>{s.icon || '📘'} {s.name}</button>
                            ))}
                        </div>
                    )}
                    <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr auto', gap: 10, alignItems: 'end' }} className="sched-form">
                        <div className="field"><label>عنوان</label><input className="input" value={colForm.data.title} onChange={(e) => colForm.setData('title', e.target.value)} placeholder="مثلاً: آزمون ریاضی مهر" /></div>
                        <div className="field"><label>نوع</label>
                            <select className="input" value={colForm.data.type} onChange={(e) => colForm.setData('type', e.target.value)}>
                                <option value="numeric">عددی</option><option value="descriptive">توصیفی</option>
                            </select>
                        </div>
                        <div className="field"><label>بارم</label><input type="number" className="input" value={colForm.data.max} onChange={(e) => colForm.setData('max', e.target.value)} disabled={colForm.data.type === 'descriptive'} /></div>
                        <button type="submit" className="btn">ساخت</button>
                    </div>
                </form>
            )}

            <div className="panel printable">
                <h3 className="print-title">📔 دفتر کلاسی — {classroom?.name}</h3>
                {columns.length === 0 && <p style={{ color: 'var(--muted)' }} className="no-print">هنوز ستون نمره‌ای نساخته‌ای. روی «➕ ستون نمره» بزن.</p>}
                {columns.length > 0 && (
                    <div style={{ overflowX: 'auto' }}>
                        <table className="tbl gradebook-tbl">
                            <thead>
                                <tr>
                                    <th style={{ position: 'sticky', insetInlineStart: 0, background: '#fff', minWidth: 120 }}>دانش‌آموز</th>
                                    {columns.map((c) => (
                                        <th key={c.id} style={{ textAlign: 'center', minWidth: 130 }}>
                                            {c.title}<div style={{ fontWeight: 400, fontSize: 11, color: 'var(--muted)' }}>
                                                {c.type === 'numeric' ? `عددی (از ${fa(c.max)})` : 'توصیفی'}
                                                <button onClick={() => delCol(c.id)} className="no-print" style={{ border: 0, background: 'none', color: '#e8505b', cursor: 'pointer', marginInlineStart: 4 }}>✕</button>
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {students.map((s, i) => (
                                    <tr key={s.id}>
                                        <td style={{ fontWeight: 700, position: 'sticky', insetInlineStart: 0, background: '#fff' }}>{fa(i + 1)}. {s.name}</td>
                                        {columns.map((c) => (
                                            <td key={c.id} style={{ textAlign: 'center' }}>
                                                {c.type === 'numeric'
                                                    ? <input type="number" step="0.25" max={c.max} value={val(c, s.id, 'score')} onChange={(e) => setVal(c.id, s.id, 'score', e.target.value)} className="grade-input" placeholder="—" />
                                                    : <input value={val(c, s.id, 'text')} onChange={(e) => setVal(c.id, s.id, 'text', e.target.value)} className="grade-input" style={{ width: 110 }} placeholder="—" />}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                {columns.length > 0 && (
                    <div className="no-print" style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 14 }}>
                        {columns.map((c) => (
                            <button key={c.id} onClick={() => saveCol(c)} className="btn btn-sm" disabled={!edits[c.id] || !Object.keys(edits[c.id]).length}>
                                💾 ذخیره‌ی «{c.title}»
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </DashLayout>
    );
}
