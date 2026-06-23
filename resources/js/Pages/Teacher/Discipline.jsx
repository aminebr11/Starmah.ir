import { usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Discipline() {
    const { students = [], records = [], flash } = usePage().props;
    const [sel, setSel] = useState('');
    const [note, setNote] = useState('');
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const record = (type) => {
        if (!sel) { alert('یک دانش‌آموز انتخاب کن'); return; }
        router.post(route('teacher.discipline.store'), { student_id: sel, type, note }, {
            preserveScroll: true, onSuccess: () => setNote(''),
        });
    };

    return (
        <DashLayout title="انضباط" roleLabel="معلم" menu={teacherMenu} active="discipline">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{typeof banner === 'string' ? banner : banner.message}</b></div>}

            <div className="panel">
                <h3>⭐ ثبت رفتار</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                    <div className="field"><label>دانش‌آموز</label>
                        <select className="input" value={sel} onChange={(e) => setSel(e.target.value)}>
                            <option value="">— انتخاب —</option>
                            {students.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </div>
                    <div className="field"><label>یادداشت (اختیاری)</label>
                        <input className="input" value={note} onChange={(e) => setNote(e.target.value)} placeholder="مثلاً: کمک به هم‌کلاسی" />
                    </div>
                </div>
                <div style={{ display: 'flex', gap: 10 }}>
                    <button onClick={() => record('star')} className="btn btn-sm">🌟 ستاره‌ی تشویق (+۲۰ امتیاز)</button>
                    <button onClick={() => record('warning')} className="btn btn-ghost btn-sm">⚠️ تذکر</button>
                    <button onClick={() => record('note')} className="btn btn-ghost btn-sm">📝 یادداشت</button>
                </div>
            </div>

            <div className="panel">
                <h3>📋 آخرین ثبت‌ها</h3>
                {records.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز رکوردی ثبت نشده.</p>}
                {records.length > 0 && (
                    <table className="tbl">
                        <thead><tr><th>دانش‌آموز</th><th>نوع</th><th>یادداشت</th><th>تاریخ</th></tr></thead>
                        <tbody>{records.map((r, i) => (
                            <tr key={i}>
                                <td style={{ fontWeight: 700 }}>{r.student}</td>
                                <td>{r.type === 'star' ? '🌟 تشویق' : r.type === 'warning' ? '⚠️ تذکر' : '📝 یادداشت'}</td>
                                <td>{r.note ?? '—'}</td><td style={{ color: 'var(--muted)' }}>{fa(r.date)}</td>
                            </tr>
                        ))}</tbody>
                    </table>
                )}
            </div>
        </DashLayout>
    );
}
