import { usePage } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Gradebook() {
    const { classroom, rows = [] } = usePage().props;
    return (
        <DashLayout title="دفتر نمره" roleLabel="معلم" menu={teacherMenu} active="gradebook">
            <div className="panel">
                <h3>📔 دفتر نمره {classroom ? `— ${classroom.name}` : ''}</h3>
                {rows.length === 0 && <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در کلاس نیست.</p>}
                {rows.length > 0 && (
                    <table className="tbl">
                        <thead><tr><th>#</th><th>دانش‌آموز</th><th>امتیاز کل</th><th>میانگین تسلط</th><th>ستاره انضباط</th></tr></thead>
                        <tbody>{rows.map((r, i) => (
                            <tr key={r.id}>
                                <td>{fa(i + 1)}</td><td style={{ fontWeight: 700 }}>{r.name}</td>
                                <td style={{ color: 'var(--gold-2)', fontWeight: 800 }}>{fa(r.xp)}</td>
                                <td><span className={`tag ${r.mastery >= 70 ? 'tag-ok' : r.mastery >= 40 ? 'tag-warn' : 'tag-info'}`}>{fa(r.mastery)}٪</span></td>
                                <td>⭐ {fa(r.stars)}</td>
                            </tr>
                        ))}</tbody>
                    </table>
                )}
                <p style={{ color: 'var(--muted)', fontSize: 13, marginTop: 14 }}>ثبت نمره‌ی آزمون‌های دستی در نسخه‌ی بعدی اضافه می‌شود.</p>
            </div>
        </DashLayout>
    );
}
