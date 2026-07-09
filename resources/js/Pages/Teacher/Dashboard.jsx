import { usePage, Link } from '@inertiajs/react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';
const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Dashboard() {
    const { auth, classrooms = [], totals = {} } = usePage().props;
    const name = auth?.user?.name || 'معلم عزیز';

    const cards = [
        { ic: '🏛️', lbl: 'کلاس‌ها', val: totals.classrooms, c: '#fff3d6' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: totals.students, c: '#dcebff' },
        { ic: '📝', lbl: 'تکالیف', val: totals.assignments, c: '#e9e4ff' },
        { ic: '⭐', lbl: 'ستاره‌های داده‌شده', val: totals.stars, c: '#d4f5ef' },
    ];

    // ابزارهای معلم — مطابق امکانات وبسایت قبلی + امکانات فعلی
    const tools = [
        { href: route('teacher.activities'), ic: '🎯', t: 'فعالیت‌ها و امتیاز', d: 'ثبت بازی، آزمون و دادن ستاره', c: '#fff3d6' },
        { href: route('teacher.exams'), ic: '📝', t: 'آزمون‌ساز هوشمند', d: 'ساخت آزمون دستی یا با هوش مصنوعی', c: '#e9e4ff' },
        { href: route('teacher.gradebook'), ic: '📔', t: 'دفتر نمره', d: 'نمرات و تسلط دانش‌آموزان', c: '#dcebff' },
        { href: route('teacher.discipline'), ic: '⭐', t: 'انضباط', d: 'ثبت ستاره‌ی تشویقی و تذکر', c: '#d4f5ef' },
        { href: route('teacher.schedule'), ic: '🗓️', t: 'برنامه‌ی کلاسی', d: 'تنظیم برنامه‌ی هفتگی درس‌ها', c: '#ffe0ec' },
        { href: route('teacher.materials'), ic: '📚', t: 'محتوای کلاس', d: 'جزوه، پادکست، گالری و تکلیف', c: '#e6f0ff' },
        { href: route('teacher.reports'), ic: '📈', t: 'گزارش‌ها و تحلیل', d: 'عملکرد کلاس و پیشرفت', c: '#e9e4ff' },
        { href: route('teacher.assignments.create'), ic: '➕', t: 'تکلیف جدید', d: 'ایجاد تمرین و تکلیف برای کلاس', c: '#fff3d6' },
    ];

    return (
        <DashLayout title="پیشخوان معلم" roleLabel="معلم" menu={teacherMenu} active="home"
            actions={<Link href={route('teacher.assignments.create')} className="btn btn-sm">➕ تکلیف جدید</Link>}>

            {/* خوش‌آمد */}
            <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                    <div style={{ fontSize: 40 }}>👋</div>
                    <div>
                        <div style={{ fontWeight: 800, fontSize: 20 }}>سلام {name}!</div>
                        <div style={{ color: '#c4d2f0', fontSize: 14 }}>به پیشخوان کلاس خوش آمدی — همه‌ی ابزارهای تدریس این‌جاست.</div>
                    </div>
                </div>
            </div>

            {/* آمار */}
            <div className="dash-cards" style={{ marginTop: 20 }}>
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard"><div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div><div className="val">{fa(c.val ?? 0)}</div></div>
                ))}
            </div>

            {/* ابزارها */}
            <div className="panel" style={{ marginTop: 20 }}>
                <h3>🧰 ابزارهای من</h3>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 14 }} className="tools-grid">
                    {tools.map((t) => (
                        <Link key={t.t} href={t.href} className="tool-card">
                            <div className="tool-ic" style={{ background: t.c }}>{t.ic}</div>
                            <div style={{ fontWeight: 800, color: 'var(--navy-800)' }}>{t.t}</div>
                            <div style={{ color: 'var(--muted)', fontSize: 12.5, marginTop: 2 }}>{t.d}</div>
                        </Link>
                    ))}
                </div>
            </div>

            {/* کلاس‌های من */}
            <div id="class" className="panel" style={{ marginTop: 20 }}>
                <h3>🏫 کلاس‌های من</h3>
                {classrooms.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز کلاسی نداری.</p>}
                {classrooms.map((c) => (
                    <Link key={c.id} href={route('teacher.classroom', c.id)} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid var(--line)', color: 'var(--ink)' }}>
                        <div><div style={{ fontWeight: 800 }}>{c.name}</div><div style={{ color: 'var(--muted)', fontSize: 13 }}>کد: {c.join_code} · {fa(c.students)} دانش‌آموز</div></div>
                        <span style={{ fontSize: 20 }}>‹</span>
                    </Link>
                ))}
            </div>
        </DashLayout>
    );
}
