import { usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const planSummary = (p) => {
    if (!p) return '';
    const c = p.max_classes ? `${fa(p.max_classes)} کلاس` : 'کلاس نامحدود';
    const s = p.max_students_per_class ? `${fa(p.max_students_per_class)} دانش‌آموز` : 'دانش‌آموز نامحدود';
    const d = p.duration_days ? `${fa(p.duration_days)} روز` : 'بدون انقضا';
    return `${c} · ${s} · ${d}`;
};

export default function Schools() {
    const { pending = [], schools = [], plans = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [cfg, setCfg] = useState({});
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const defaultPlan = plans[0]?.id ?? '';
    const get = (id) => cfg[id] ?? { plan_id: defaultPlan, mode: 'auto', password: '' };
    const set = (id, patch) => setCfg((c) => ({ ...c, [id]: { ...get(id), ...patch } }));

    const approve = (r) => {
        const c = get(r.id);
        if (!c.plan_id) { alert('یک طرح انتخاب کنید'); return; }
        if (c.mode === 'manual' && c.password.length < 6) { alert('رمز دستی حداقل ۶ کاراکتر باشد'); return; }
        router.post(route('admin.schools.approve', r.id), { plan_id: c.plan_id, password_mode: c.mode, password: c.password }, { preserveScroll: true });
    };
    const reject = (id) => { if (confirm('این درخواست رد شود؟')) router.post(route('admin.schools.reject', id), {}, { preserveScroll: true }); };
    const changePlan = (schoolId, planId) => {
        if (!planId) return;
        if (!confirm('طرح این مدرسه تغییر کند؟')) return;
        router.post(route('admin.schools.plan', schoolId), { plan_id: planId }, { preserveScroll: true });
    };

    return (
        <DashLayout title="مدیریت مدارس" roleLabel="ادمین کل" menu={adminMenu} active="schools">
            {banner && (
                <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}>
                    <b>{banner.message}</b>
                    {banner.type === 'credentials' && <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 6 }}>این اطلاعات را به مدیر مدرسه بدهید (یک‌بار نمایش داده می‌شود).</div>}
                </div>
            )}

            <div className="panel">
                <h3>⏳ درخواست‌های در انتظار تأیید ({fa(pending.length)})</h3>
                {pending.length === 0 && <p style={{ color: 'var(--muted)' }}>درخواست جدیدی نیست. 🎉</p>}
                {pending.map((r) => {
                    const c = get(r.id);
                    const selPlan = plans.find((p) => String(p.id) === String(c.plan_id));
                    return (
                        <div key={r.id} style={{ padding: '16px 0', borderBottom: '1px solid var(--line)' }}>
                            <div style={{ fontWeight: 800 }}>{r.school_name} <span style={{ color: 'var(--muted)', fontWeight: 400, fontSize: 13 }}>· {r.city}</span></div>
                            <div style={{ color: 'var(--muted)', fontSize: 13, marginBottom: 12 }}>مدیر: {r.manager_name} · {r.manager_phone} · درخواست {fa(r.classes_count)} کلاس</div>

                            <div style={{ background: 'var(--cream)', borderRadius: 14, padding: 14, display: 'grid', gap: 12 }}>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }} className="grid-2-form">
                                    <div className="field" style={{ margin: 0 }}>
                                        <label>طرح اشتراک</label>
                                        <select className="input" value={c.plan_id} onChange={(e) => set(r.id, { plan_id: e.target.value })}>
                                            {plans.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                        {selPlan && <div style={{ color: 'var(--muted-2)', fontSize: 11, marginTop: 4 }}>{planSummary(selPlan)}</div>}
                                    </div>
                                    <div className="field" style={{ margin: 0 }}>
                                        <label>رمز مدیر مدرسه</label>
                                        <div style={{ display: 'flex', gap: 6 }}>
                                            <button type="button" onClick={() => set(r.id, { mode: 'auto' })} className={`tag ${c.mode === 'auto' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 12px' }}>🎲 خودکار</button>
                                            <button type="button" onClick={() => set(r.id, { mode: 'manual' })} className={`tag ${c.mode === 'manual' ? 'tag-warn' : 'tag-info'}`} style={{ cursor: 'pointer', border: 0, fontFamily: 'inherit', padding: '9px 12px' }}>✍️ دستی</button>
                                        </div>
                                        {c.mode === 'manual' && (
                                            <input className="input" style={{ marginTop: 8 }} value={c.password} onChange={(e) => set(r.id, { password: e.target.value })} placeholder="رمز دلخواه (حداقل ۶ کاراکتر)" dir="ltr" />
                                        )}
                                    </div>
                                </div>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    <button onClick={() => approve(r)} className="btn btn-sm">✅ تأیید و ساخت حساب</button>
                                    <button onClick={() => reject(r.id)} className="btn btn-ghost btn-sm">رد درخواست</button>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="panel">
                <h3>🏫 مدارس ({fa(schools.length)})</h3>
                <table className="tbl">
                    <thead><tr><th>نام</th><th>شهر</th><th>طرح</th><th>انقضا</th><th>کاربر</th><th>کلاس</th><th>تغییر طرح</th></tr></thead>
                    <tbody>
                        {schools.length === 0 && <tr><td colSpan="7" style={{ color: 'var(--muted)' }}>مدرسه‌ای ثبت نشده.</td></tr>}
                        {schools.map((s) => (
                            <tr key={s.id}>
                                <td style={{ fontWeight: 700 }}>{s.name}</td><td>{s.city ?? '—'}</td>
                                <td><span className="tag tag-info">{s.plan}</span></td>
                                <td style={{ fontSize: 13 }}>{s.expires ? fa(s.expires) : 'بدون انقضا'}</td>
                                <td>{fa(s.users)}</td><td>{fa(s.classrooms)}</td>
                                <td>
                                    <select className="input" style={{ padding: '6px 8px', fontSize: 13 }} value={s.plan_id ?? ''} onChange={(e) => changePlan(s.id, e.target.value)}>
                                        <option value="">— انتخاب —</option>
                                        {plans.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </DashLayout>
    );
}
