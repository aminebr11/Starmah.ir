import { usePage, Link } from '@inertiajs/react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const faGroup = (n) => fa(Number(n ?? 0).toLocaleString('en-US'));

export default function Overview() {
    const { auth, stats = {}, recent_schools = [], recent_requests = [] } = usePage().props;
    const name = auth?.user?.name || 'مدیر';

    const cards = [
        { ic: '🏫', lbl: 'کل مدارس', val: fa(stats.schools ?? 0), sub: `${fa(stats.active_schools ?? 0)} فعال`, c: '#fff3d6' },
        { ic: '⏳', lbl: 'در انتظار تأیید', val: fa(stats.pending ?? 0), sub: 'درخواست جدید', c: '#ffe0ec' },
        { ic: '🎓', lbl: 'دانش‌آموزان', val: fa(stats.students ?? 0), sub: 'در کل پلتفرم', c: '#dcebff' },
        { ic: '👩‍🏫', lbl: 'معلم‌ها', val: fa(stats.teachers ?? 0), sub: 'فعال', c: '#d4f5ef' },
        { ic: '🏛️', lbl: 'کلاس‌ها', val: fa(stats.classes ?? 0), sub: 'در همه‌ی مدارس', c: '#e9e4ff' },
        { ic: '⭐', lbl: 'مجموع امتیازها', val: faGroup(stats.total_xp ?? 0), sub: 'ستاره‌ی کسب‌شده', c: '#fff3d6' },
    ];

    const tools = [
        { href: route('admin.schools'), ic: '🏫', t: 'مدیریت مدارس', d: 'تأیید درخواست‌ها و مدارس', c: '#fff3d6' },
        { href: route('admin.themes'), ic: '🎨', t: 'تم‌ها (دنیاها)', d: 'ساخت و مدیریت دنیاهای علاقه', c: '#e9e4ff' },
        { href: route('admin.reports'), ic: '📈', t: 'گزارش‌ها', d: 'تحلیل کل پلتفرم', c: '#dcebff' },
        { href: route('admin.settings'), ic: '⚙️', t: 'تنظیمات پلتفرم', d: 'پیکربندی سرویس‌ها', c: '#d4f5ef' },
    ];

    return (
        <DashLayout title="پیشخوان ادمین کل" roleLabel="ادمین کل" menu={adminMenu} active="home"
            actions={<Link href={route('admin.schools')} className="btn btn-sm">🏫 مدیریت مدارس</Link>}>

            {/* خوش‌آمد */}
            <div className="panel" style={{ background: 'linear-gradient(135deg,#16264f,#0a1836)', border: 0, color: '#fff' }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 14, flexWrap: 'wrap' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                        <div style={{ fontSize: 40 }}>🛰️</div>
                        <div>
                            <div style={{ fontWeight: 800, fontSize: 20 }}>سلام {name}!</div>
                            <div style={{ color: '#c4d2f0', fontSize: 14 }}>نمای کلی پلتفرم ستاره ماه — همه‌چیز زیر نظر شماست.</div>
                        </div>
                    </div>
                    {stats.pending > 0 && (
                        <Link href={route('admin.schools')} className="btn btn-sm" style={{ background: 'linear-gradient(135deg,#ff6b9d,#e8505b)', color: '#fff' }}>
                            ⏳ {fa(stats.pending)} درخواست در انتظار
                        </Link>
                    )}
                </div>
            </div>

            {/* آمار */}
            <div className="dash-cards" style={{ gridTemplateColumns: 'repeat(3,1fr)', marginTop: 20 }}>
                {cards.map((c) => (
                    <div key={c.lbl} className="dcard">
                        <div className="ic" style={{ background: c.c }}>{c.ic}</div>
                        <div className="lbl">{c.lbl}</div>
                        <div className="val">{c.val}</div>
                        <div style={{ color: 'var(--muted-2)', fontSize: 12, marginTop: 2 }}>{c.sub}</div>
                    </div>
                ))}
            </div>

            {/* ابزارها */}
            <div className="panel" style={{ marginTop: 20 }}>
                <h3>🧰 مدیریت پلتفرم</h3>
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

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20, marginTop: 20, alignItems: 'start' }} className="themes-grid">
                {/* درخواست‌های در انتظار */}
                <div className="panel" style={{ margin: 0 }}>
                    <h3>⏳ درخواست‌های در انتظار تأیید
                        <Link href={route('admin.schools')} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto' }}>همه ←</Link>
                    </h3>
                    {recent_requests.length === 0 && <p style={{ color: 'var(--muted)' }}>درخواست جدیدی نیست. 🎉</p>}
                    {recent_requests.map((r) => (
                        <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '11px 0', borderBottom: '1px solid var(--line)', gap: 8 }}>
                            <span><b>{r.school_name}</b><br /><span style={{ color: 'var(--muted)', fontSize: 12 }}>{r.manager_name} · {r.manager_phone}</span></span>
                            <span className="tag tag-warn">{fa(r.classes_count)} کلاس</span>
                        </div>
                    ))}
                </div>

                {/* آخرین مدارس */}
                <div className="panel" style={{ margin: 0 }}>
                    <h3>🏫 آخرین مدارس</h3>
                    <table className="tbl">
                        <thead><tr><th>نام</th><th>شهر</th><th>پلن</th><th>وضعیت</th></tr></thead>
                        <tbody>
                            {recent_schools.length === 0 && <tr><td colSpan="4" style={{ color: 'var(--muted)' }}>مدرسه‌ای ثبت نشده.</td></tr>}
                            {recent_schools.map((s) => (
                                <tr key={s.id}><td style={{ fontWeight: 700 }}>{s.name}</td><td>{s.city ?? '—'}</td><td>{s.plan}</td>
                                    <td><span className={`tag ${s.status === 'active' ? 'tag-ok' : 'tag-warn'}`}>{s.status === 'active' ? 'فعال' : s.status}</span></td></tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </DashLayout>
    );
}
