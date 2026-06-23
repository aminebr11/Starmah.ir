import { usePage, Link } from '@inertiajs/react';
import DashLayout, { schoolMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Overview() {
    const { school, stats = {}, classes = [] } = usePage().props;
    const cards = [
        { ic: '👩‍🏫', lbl: 'معلم‌ها', val: stats.teachers, c: '#d4f5ef' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: stats.students, c: '#dcebff' },
        { ic: '🏛️', lbl: 'کلاس‌ها', val: stats.classes, c: '#fff3d6' },
    ];
    return (
        <DashLayout title={`پیشخان مدرسه${school ? ` — ${school.name}` : ''}`} roleLabel="مدیر مدرسه" menu={schoolMenu} active="home"
            actions={<Link href={route('school.teachers')} className="btn btn-sm">➕ معلم جدید</Link>}>
            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(3,1fr)' }}>
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard"><div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div><div className="val">{fa(c.val ?? 0)}</div></div>
                ))}
            </div>
            <div className="panel">
                <h3>🏛️ کلاس‌های مدرسه</h3>
                {classes.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کلاسی ساخته نشده. <Link href={route('school.teachers')} className="link-gold">یک معلم بساز ←</Link></p>}
                {classes.length > 0 && (
                    <table className="tbl">
                        <thead><tr><th>کلاس</th><th>معلم</th><th>دانش‌آموز</th><th>کد ورود</th></tr></thead>
                        <tbody>{classes.map((c, i) => (
                            <tr key={i}><td style={{ fontWeight: 700 }}>{c.name}</td><td>{c.teacher ?? '—'}</td><td>{fa(c.students)}</td>
                                <td><span className="tag tag-info">{c.code}</span></td></tr>
                        ))}</tbody>
                    </table>
                )}
            </div>
        </DashLayout>
    );
}
