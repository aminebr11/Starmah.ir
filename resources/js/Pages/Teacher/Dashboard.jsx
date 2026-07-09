import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Dashboard() {
    const { classrooms = [], totals = {} } = usePage().props;
    const cards = [
        { ic: '🏛️', lbl: 'کلاس‌ها', val: totals.classrooms, c: '#fff3d6' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: totals.students, c: '#dcebff' },
        { ic: '📝', lbl: 'تکالیف', val: totals.assignments, c: '#e9e4ff' },
        { ic: '⭐', lbl: 'ستاره‌های داده‌شده', val: totals.stars, c: '#d4f5ef' },
    ];
    return (
        <DashLayout title="پیشخوان معلم" roleLabel="معلم" menu={teacherMenu} active="home"
            actions={<Link href={route('teacher.assignments.create')} className="btn btn-sm">➕ تکلیف جدید</Link>}>
            <div className="dash-cards">
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard"><div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div><div className="val">{fa(c.val ?? 0)}</div></div>
                ))}
            </div>

            <div id="class" className="panel">
                <h3>🏫 کلاس‌های من</h3>
                {classrooms.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کلاسی نداری.</p>}
                {classrooms.map((c) => (
                    <Link key={c.id} href={route('teacher.classroom', c.id)} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid var(--line)', color: 'var(--ink)' }}>
                        <div><div style={{ fontWeight: 800 }}>{c.name}</div><div style={{ color: 'var(--muted)', fontSize: 13 }}>کد: {c.join_code} · {fa(c.students)} دانش‌آموز</div></div>
                        <span style={{ fontSize: 20 }}>‹</span>
                    </Link>
                ))}
            </div>

            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(3,1fr)' }}>
                <Quick href={route('teacher.gradebook')} ic="📔" t="دفتر نمره" d="نمرات و تسلط دانش‌آموزان" />
                <Quick href={route('teacher.discipline')} ic="⭐" t="انضباط" d="ثبت ستاره و تذکر" />
                <Quick href={route('teacher.materials')} ic="📚" t="مطالب و محتوا" d="بارگذاری جزوه و فایل" />
            </div>
        </DashLayout>
    );
}
function Quick({ href, ic, t, d }) {
    return (
        <Link href={href} className="dcard" style={{ display: 'block' }}>
            <div style={{ fontSize: 26 }}>{ic}</div>
            <div style={{ fontWeight: 800, marginTop: 6, color: 'var(--navy-800)' }}>{t}</div>
            <div style={{ color: 'var(--muted)', fontSize: 13 }}>{d}</div>
        </Link>
    );
}
