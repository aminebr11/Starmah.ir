import { usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Classroom() {
    const { classroom, students = [] } = usePage().props;
    const [openFor, setOpenFor] = useState(null);

    const record = (studentId, type) => {
        router.post(route('teacher.discipline.store'), { student_id: studentId, type }, { preserveScroll: true, onSuccess: () => setOpenFor(null) });
    };

    return (
        <DashLayout title={`کلاس ${classroom?.name ?? ''}`} roleLabel="معلم" menu={teacherMenu} active="class">
            <div className="panel">
                <h3>🏫 {classroom?.name} — کد ورود: <span className="tag tag-info" style={{ marginInlineStart: 8 }}>{classroom?.join_code}</span></h3>
                {students.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز دانش‌آموزی به این کلاس نپیوسته.</p>}
                <table className="tbl">
                    <tbody>
                        {students.map((s, i) => (
                            <tr key={s.id}>
                                <td style={{ width: 30 }}>{fa(i + 1)}</td>
                                <td style={{ fontWeight: 700 }}>{s.name}</td>
                                <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)} امتیاز</td>
                                <td>تسلط {fa(s.avg)}٪</td>
                                <td style={{ textAlign: 'left' }}>
                                    {openFor === s.id ? (
                                        <span style={{ display: 'inline-flex', gap: 6 }}>
                                            <button onClick={() => record(s.id, 'star')} className="btn btn-sm">🌟</button>
                                            <button onClick={() => record(s.id, 'warning')} className="btn btn-ghost btn-sm">⚠️</button>
                                        </span>
                                    ) : (
                                        <button onClick={() => setOpenFor(s.id)} className="btn btn-ghost btn-sm">ثبت رفتار</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
