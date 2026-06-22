import { usePage } from '@inertiajs/react';
import Themed from '@/Layouts/Themed';
import { fa, ui } from '@/theme';

const nav = () => ([
    { key: 'home', label: 'مدرسه', icon: '🏫', href: '/admin' },
    { key: 'messages', label: 'پیام‌ها', icon: '💌', href: '/messages' },
]);

export default function Overview() {
    const { school, totals, classrooms } = usePage().props;

    return (
        <Themed title="مدیریت مدرسه" nav={nav()} active="home">
            <div style={ui.h}>پیشخان مدرسه 🏫</div>

            {school && (
                <div style={{ ...ui.card, marginTop: 12 }}>
                    <div style={ui.row('space-between')}>
                        <div style={{ fontWeight: 800, fontSize: 16 }}>{school.name}</div>
                        <span style={{ ...ui.pill, ...ui.pillAcc }}>پلن {school.plan}</span>
                    </div>
                    <div style={{ ...ui.muted, marginTop: 6 }}>وضعیت: {school.status} • ظرفیت: {fa(school.seats)} • اعتبار تا: {fa(school.ends_at ?? '—')}</div>
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 14 }}>
                <Stat b={fa(totals.students)} s="دانش‌آموز" />
                <Stat b={fa(totals.teachers)} s="معلم" />
                <Stat b={fa(totals.classrooms)} s="کلاس" />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '20px 4px 12px', fontWeight: 800 }}>
                <span style={{ width: 5, height: 18, borderRadius: 6, background: 'linear-gradient(var(--p1),var(--acc))' }} />
                کلاس‌ها
            </div>
            {classrooms.map((c, i) => (
                <div key={i} style={{ ...ui.card, marginBottom: 8, display: 'flex', justifyContent: 'space-between' }}>
                    <div><b>{c.name}</b><div style={ui.muted}>معلم: {c.teacher ?? '—'}</div></div>
                    <span style={{ ...ui.pill }}>{fa(c.students)} دانش‌آموز</span>
                </div>
            ))}
        </Themed>
    );
}
function Stat({ b, s }) {
    return <div style={{ ...ui.card, textAlign: 'center', padding: '12px 8px' }}><b style={{ display: 'block', fontSize: 22, color: 'var(--acc)' }}>{b}</b><span style={ui.muted}>{s}</span></div>;
}
