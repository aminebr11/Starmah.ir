import { usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import WebLayout from '@/Layouts/WebLayout';

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
        <WebLayout title="مدیریت مدارس">
            <div className="container section">
                <h2 style={{ color: 'var(--navy-800)' }}>ادمین کل — مدیریت مدارس 🛡️</h2>

                {banner && (
                    <div className="card" style={{ borderColor: 'var(--gold)', background: '#fff8e8', marginTop: 14 }}>
                        <b>{banner.message}</b>
                        {banner.type === 'credentials' && <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 6 }}>این اطلاعات را به مدیر مدرسه بدهید.</div>}
                    </div>
                )}

                {/* درخواست‌های در انتظار */}
                <h3 style={{ marginTop: 26, color: 'var(--navy-700)' }}>درخواست‌های در انتظار تأیید ({fa(pending.length)})</h3>
                {pending.length === 0 && <p style={{ color: 'var(--muted)' }}>درخواست جدیدی نیست.</p>}
                {pending.map((r) => (
                    <div key={r.id} className="card" style={{ marginTop: 12, display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12, alignItems: 'center' }}>
                        <div>
                            <div style={{ fontWeight: 800, fontSize: 16 }}>{r.school_name} <span style={{ color: 'var(--muted)', fontWeight: 400, fontSize: 13 }}>· {r.city}</span></div>
                            <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 4 }}>
                                مدیر: {r.manager_name} · {r.manager_phone} · {fa(r.classes_count)} کلاس
                            </div>
                            {r.note && <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 4 }}>«{r.note}»</div>}
                        </div>
                        <div style={{ display: 'flex', gap: 8 }}>
                            <button onClick={() => act(r.id, 'approve')} className="btn btn-sm">✅ تأیید و ساخت حساب</button>
                            <button onClick={() => act(r.id, 'reject')} className="btn btn-ghost btn-sm">رد</button>
                        </div>
                    </div>
                ))}

                {/* مدارس فعال */}
                <h3 style={{ marginTop: 32, color: 'var(--navy-700)' }}>مدارس ({fa(schools.length)})</h3>
                <div className="grid grid-3" style={{ marginTop: 12 }}>
                    {schools.map((s) => (
                        <div key={s.id} className="card">
                            <div style={{ fontWeight: 800 }}>{s.name}</div>
                            <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 4 }}>{s.city} · پلن {s.plan}</div>
                            <div style={{ display: 'flex', gap: 14, marginTop: 10, fontSize: 13 }}>
                                <span>👥 {fa(s.users)} کاربر</span>
                                <span>🏫 {fa(s.classrooms)} کلاس</span>
                            </div>
                            <span className="pill" style={{ display: 'inline-block', marginTop: 10, background: s.status === 'active' ? '#e3f7ec' : '#fdeaea', color: s.status === 'active' ? '#2bb673' : '#e8505b', padding: '4px 12px', borderRadius: 20, fontSize: 12, fontWeight: 700 }}>
                                {s.status === 'active' ? 'فعال' : s.status}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </WebLayout>
    );
}
