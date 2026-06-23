import { usePage } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Students() {
    const { students = [] } = usePage().props;
    return (
        <DashLayout title="دانش‌آموزان" roleLabel="مدیر مدرسه" menu={schoolMenu} active="students">
            <div className="panel">
                <h3>🎓 دانش‌آموزان مدرسه ({fa(students.length)})</h3>
                {students.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز دانش‌آموزی ثبت‌نام نکرده.</p>}
                {students.length > 0 && (
                    <table className="tbl">
                        <thead><tr><th>#</th><th>نام</th><th>موبایل</th><th>کلاس</th><th>معلم</th><th>امتیاز</th></tr></thead>
                        <tbody>{students.map((s, i) => (
                            <tr key={s.id}><td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{s.name}</td><td>{s.phone}</td>
                                <td>{s.class ?? '—'}</td><td>{s.teacher ?? '—'}</td><td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)}</td></tr>
                        ))}</tbody>
                    </table>
                )}
            </div>
        </DashLayout>
    );
}
