import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const QUICK = [5, 10, 20, -5, -10];

/** مدیریت امتیازاتِ دانش‌آموز — افزودن/کسر امتیاز، حذف ردیف، پاک‌کردن کل سابقه. */
export default function StudentPoints() {
    const { students = [], selected, ledger = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const pick = (id) => router.get(route('teacher.points'), { student: id }, { preserveState: true, preserveScroll: true });

    const form = useForm({ student_id: selected?.id || '', amount: 10, reason: '' });
    useEffect(() => { form.setData('student_id', selected?.id || ''); }, [selected?.id]);
    const submit = (amt) => {
        const amount = amt ?? form.data.amount;
        if (!selected || !amount) return;
        router.post(route('teacher.points.adjust'), { student_id: selected.id, amount, reason: form.data.reason || (amount >= 0 ? 'امتیاز تشویقی معلم' : 'کسر امتیاز توسط معلم') }, { preserveScroll: true, onSuccess: () => form.setData('reason', '') });
    };
    const delEntry = (id) => { if (confirm('این ردیفِ امتیاز حذف شود؟')) router.delete(route('teacher.points.entry.destroy', id), { preserveScroll: true }); };
    const clearAll = () => { if (selected && confirm(`کلِ سابقه‌ی امتیازاتِ «${selected.name}» پاک شود؟ این کار برگشت‌پذیر نیست.`)) router.post(route('teacher.points.clear'), { student_id: selected.id }, { preserveScroll: true }); };

    return (
        <DashLayout title="مدیریت امتیازات" roleLabel="معلم" menu={teacherMenu} active="points">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(220px,300px) 1fr', gap: 16, alignItems: 'start' }} className="themes-grid">
                {/* فهرست دانش‌آموزان */}
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🎓 دانش‌آموزان</h3>
                    {students.length === 0 && <p style={{ color: 'var(--muted)' }}>دانش‌آموزی در کلاس‌های شما نیست.</p>}
                    <div style={{ display: 'grid', gap: 6 }}>
                        {students.map((s) => (
                            <button key={s.id} onClick={() => pick(s.id)}
                                style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, textAlign: 'start', border: selected?.id === s.id ? '2px solid var(--gold)' : '1px solid var(--line)', background: selected?.id === s.id ? '#fff8e8' : '#fff', borderRadius: 10, padding: '9px 12px', cursor: 'pointer', fontFamily: 'inherit' }}>
                                <span style={{ fontWeight: 700 }}>{s.name}</span>
                                <span className="tag" style={{ background: '#eef3ff', color: '#2555c0' }}>⚡{fa(s.total)}</span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* مدیریت امتیازِ دانش‌آموزِ انتخاب‌شده */}
                <div className="panel">
                    {!selected ? (
                        <div style={{ textAlign: 'center', padding: 30, color: 'var(--muted)' }}>
                            <div style={{ fontSize: 40 }}>⚡</div>
                            <p>یک دانش‌آموز را از فهرست انتخاب کنید تا امتیازاتش را مدیریت کنید.</p>
                        </div>
                    ) : (
                        <>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                <h3 style={{ margin: 0 }}>{selected.name}</h3>
                                <span className="tag tag-info">مجموع: ⚡{fa(selected.total)}</span>
                                <button onClick={clearAll} className="btn btn-ghost btn-sm" style={{ marginInlineStart: 'auto', color: '#e8505b' }}>🗑️ پاک‌کردن کل سابقه</button>
                            </div>

                            {/* افزودن/کسر امتیاز */}
                            <div style={{ background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginTop: 12 }}>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'end', flexWrap: 'wrap' }}>
                                    <div className="field" style={{ margin: 0 }}><label>مقدار (منفی = کسر)</label><input type="number" className="input" style={{ width: 110 }} value={form.data.amount} onChange={(e) => form.setData('amount', +e.target.value)} dir="ltr" /></div>
                                    <div className="field" style={{ margin: 0, flex: 1, minWidth: 160 }}><label>علت</label><input className="input" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="مثلاً: مشارکت در کلاس" /></div>
                                    <button onClick={() => submit()} className="btn">ثبت</button>
                                </div>
                                <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                    {QUICK.map((v) => (
                                        <button key={v} onClick={() => submit(v)} className="btn btn-sm" style={{ background: v >= 0 ? '#2bb673' : '#e8505b' }}>{v >= 0 ? '+' : ''}{fa(v)}</button>
                                    ))}
                                </div>
                            </div>

                            {/* سابقه‌ی امتیازات */}
                            <h3 style={{ marginBottom: 6 }}>📜 سابقه‌ی امتیازات ({fa(ledger.length)})</h3>
                            {ledger.length === 0 && <p style={{ color: 'var(--muted)' }}>سابقه‌ای ثبت نشده است.</p>}
                            <div style={{ display: 'grid', gap: 6 }}>
                                {ledger.map((e) => (
                                    <div key={e.id} style={{ display: 'flex', alignItems: 'center', gap: 10, border: '1px solid var(--line)', borderRadius: 10, padding: '8px 12px' }}>
                                        <span style={{ fontWeight: 900, minWidth: 48, color: e.kind === 'plus' ? '#16a34a' : '#dc2626' }}>{e.kind === 'plus' ? '+' : ''}{fa(e.amount)}</span>
                                        <span style={{ flex: 1, fontSize: 13.5 }}>{e.reason}</span>
                                        <span style={{ color: 'var(--muted)', fontSize: 12 }}>{e.date}</span>
                                        <button onClick={() => delEntry(e.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </DashLayout>
    );
}
