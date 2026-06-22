import { usePage, Link } from '@inertiajs/react';
import Themed from '@/Layouts/Themed';
import { fa, ui } from '@/theme';

const nav = (a) => ([
    { key: 'home', label: 'کلاس‌ها', icon: '🏫', href: '/teacher' },
    { key: 'assign', label: 'تکلیف', icon: '📝', href: '/teacher/assignments/create' },
    { key: 'messages', label: 'پیام‌ها', icon: '💌', href: '/messages' },
]);

export default function TeacherDashboard() {
    const { classrooms, totals, flash } = usePage().props;

    return (
        <Themed title="معلم" nav={nav('home')} active="home">
            <div style={ui.h}>سلام معلم عزیز 👩‍🏫</div>
            {flash?.flash && <div style={{ ...ui.card, marginTop: 10, borderColor: 'var(--p1)' }}>{flash.flash}</div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginTop: 14 }}>
                <Stat b={fa(totals.classrooms)} s="کلاس" />
                <Stat b={fa(totals.students)} s="دانش‌آموز" />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '20px 4px 12px', fontWeight: 800 }}>
                <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />
                کلاس‌های من
            </div>

            {classrooms.map((c) => (
                <Link key={c.id} href={route('teacher.classroom', c.id)} style={{ ...ui.card, marginBottom: 10, display: 'flex', justifyContent: 'space-between', alignItems: 'center', textDecoration: 'none', color: '#fff' }}>
                    <div>
                        <div style={{ fontWeight: 800 }}>{c.name}</div>
                        <div style={ui.muted}>کد ورود: {c.join_code} • {fa(c.students)} دانش‌آموز</div>
                    </div>
                    <span style={{ fontSize: 20 }}>‹</span>
                </Link>
            ))}

            <Link href={route('teacher.assignments.create')} style={{ ...ui.btn, marginTop: 8, textDecoration: 'none' }}>➕ تکلیف/آزمون جدید</Link>
        </Themed>
    );
}
function Stat({ b, s }) {
    return <div style={{ ...ui.card, textAlign: 'center' }}><b style={{ display: 'block', fontSize: 24, color: 'var(--acc)' }}>{b}</b><span style={ui.muted}>{s}</span></div>;
}
