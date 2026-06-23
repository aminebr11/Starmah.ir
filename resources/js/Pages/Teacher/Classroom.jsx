import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Classroom() {
    const { classroom, students = [] } = usePage().props;

    return (
        <DashLayout title={`کلاس ${classroom?.name ?? ''}`} roleLabel="معلم" menu={teacherMenu} active="class">
            <div className="panel">
                <h3>🏫 {classroom?.name} — کد ورود: <span className="tag tag-info" style={{ marginInlineStart: 8 }}>{classroom?.join_code}</span>
                    <Link href={route('teacher.discipline')} className="btn btn-sm" style={{ marginInlineStart: 'auto' }}>⭐ ثبت انضباط</Link>
                </h3>
                {students.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز دانش‌آموزی به این کلاس نپیوسته.</p>}
                <table className="tbl">
                    <thead><tr><th>#</th><th>نام</th><th>امتیاز</th><th>تسلط</th></tr></thead>
                    <tbody>
                        {students.map((s, i) => (
                            <tr key={s.id}>
                                <td style={{ width: 30 }}>{fa(i + 1)}</td>
                                <td style={{ fontWeight: 700 }}>{s.name}</td>
                                <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(s.xp)} امتیاز</td>
                                <td>تسلط {fa(s.avg)}٪</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
