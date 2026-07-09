import { usePage, Link } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Overview() {
    const { stats = {}, recent_schools = [], recent_requests = [] } = usePage().props;

    const cards = [
        { ic: '🏫', lbl: 'مدارس', val: stats.schools, c: '#fff3d6' },
        { ic: '⏳', lbl: 'در انتظار تأیید', val: stats.pending, c: '#ffe0ec' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: stats.students, c: '#dcebff' },
        { ic: '👩‍🏫', lbl: 'معلم‌ها', val: stats.teachers, c: '#d4f5ef' },
        { ic: '🏛️', lbl: 'کلاس‌ها', val: stats.classes, c: '#e9e4ff' },
        { ic: '🎨', lbl: 'تم‌ها (دنیاها)', val: stats.themes, c: '#fff3d6' },
    ];

    return (
        <DashLayout title="پیشخوان ادمین کل" roleLabel="ادمین کل" menu={adminMenu} active="home">
            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(3,1fr)' }}>
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard">
                        <div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div>
                        <div className="val">{fa(c.val ?? 0)}</div>
                    </div>
                ))}
            </div>

            <div className="panel">
                <h3>⏳ درخواست‌های در انتظار تأیید
                    <Link href={route('admin.schools')} className="btn btn-sm" style={{ marginInlineStart: 'auto' }}>مدیریت مدارس ←</Link>
                </h3>
                {recent_requests.length === 0 && <p style={{ color: 'var(--muted)' }}>درخواست جدیدی نیست.</p>}
                {recent_requests.map((r) => (
                    <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid var(--line)' }}>
                        <span><b>{r.school_name}</b> — {r.manager_name} · {r.manager_phone}</span>
                        <span style={{ color: 'var(--muted)', fontSize: 13 }}>{fa(r.classes_count)} کلاس</span>
                    </div>
                ))}
            </div>

            <div className="panel">
                <h3>🏫 آخرین مدارس</h3>
                <table className="tbl">
                    <thead><tr><th>نام</th><th>شهر</th><th>پلن</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        {recent_schools.map((s) => (
                            <tr key={s.id}><td>{s.name}</td><td>{s.city ?? '—'}</td><td>{s.plan}</td>
                                <td><span className={`tag ${s.status === 'active' ? 'tag-ok' : 'tag-warn'}`}>{s.status === 'active' ? 'فعال' : s.status}</span></td></tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
