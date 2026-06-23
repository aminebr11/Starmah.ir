import { usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Schools() {
    const { pending = [], schools = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const act = (id, type) => {
        if (type === 'reject' && !confirm('این درخواست رد شود؟')) return;
        router.post(route(`admin.schools.${type}`, id), {}, { preserveScroll: true });
    };

    return (
        <DashLayout title="مدیریت مدارس" roleLabel="ادمین کل" menu={adminMenu} active="schools">
            {banner && (
                <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}>
                    <b>{banner.message}</b>
                    {banner.type === 'credentials' && <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 6 }}>این اطلاعات را به مدیر مدرسه بدهید.</div>}
                </div>
            )}

            <div className="panel">
                <h3>⏳ درخواست‌های در انتظار تأیید ({fa(pending.length)})</h3>
                {pending.length === 0 && <p style={{ color: 'var(--muted)' }}>درخواست جدیدی نیست.</p>}
                {pending.map((r) => (
                    <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12, alignItems: 'center', padding: '12px 0', borderBottom: '1px solid var(--line)' }}>
                        <div>
                            <div style={{ fontWeight: 800 }}>{r.school_name} <span style={{ color: 'var(--muted)', fontWeight: 400, fontSize: 13 }}>· {r.city}</span></div>
                            <div style={{ color: 'var(--muted)', fontSize: 13 }}>مدیر: {r.manager_name} · {r.manager_phone} · {fa(r.classes_count)} کلاس</div>
                        </div>
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button onClick={() => act(r.id, 'approve')} className="btn btn-sm">✅ تأیید و ساخت حساب</button>
                            <button onClick={() => act(r.id, 'reject')} className="btn btn-ghost btn-sm">رد</button>
                        </div>
                    </div>
                ))}
            </div>

            <div className="panel">
                <h3>🏫 مدارس ({fa(schools.length)})</h3>
                <table className="tbl">
                    <thead><tr><th>نام</th><th>شهر</th><th>پلن</th><th>کاربر</th><th>کلاس</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        {schools.map((s) => (
                            <tr key={s.id}>
                                <td style={{ fontWeight: 700 }}>{s.name}</td><td>{s.city ?? '—'}</td><td>{s.plan}</td>
                                <td>{fa(s.users)}</td><td>{fa(s.classrooms)}</td>
                                <td><span className={`tag ${s.status === 'active' ? 'tag-ok' : 'tag-warn'}`}>{s.status === 'active' ? 'فعال' : s.status}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
