import { usePage, router, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { teacherMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const QUICK = [10, 25, 50, -10, -25];

/** مدیریتِ امتیازِ گروه‌ها — کم/زیادِ گروهی + دفترِ ریزِ هر تیم (امتیاز از کجا آمده). */
export default function GroupPoints() {
    const { classroom, teams = [], selectedId, ledger = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const pick = (id) => router.get(route('teacher.groups'), { team: id }, { preserveState: true, preserveScroll: true });
    const selected = teams.find((t) => t.theme_id === selectedId);

    const form = useForm({ theme_id: selectedId || '', amount: 25, reason: '' });
    useEffect(() => { form.setData('theme_id', selectedId || ''); }, [selectedId]);
    const give = (amt) => {
        const amount = amt ?? form.data.amount;
        if (!selected || !amount) return;
        router.post(route('teacher.groups.adjust'), { theme_id: selected.theme_id, amount, reason: form.data.reason }, { preserveScroll: true, onSuccess: () => form.setData('reason', '') });
    };
    const delEntry = (id) => { if (confirm('این ردیفِ امتیازِ گروهی حذف شود؟')) router.delete(route('teacher.groups.entry.destroy', id), { preserveScroll: true }); };

    if (!classroom) {
        return <DashLayout title="امتیازِ گروه‌ها" roleLabel="معلم" menu={teacherMenu} active="groups"><div className="panel"><b>ابتدا باید یک کلاس داشته باشید.</b></div></DashLayout>;
    }

    return (
        <DashLayout title="امتیازِ گروه‌ها" roleLabel="معلم" menu={teacherMenu} active="groups">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: 'minmax(220px,320px) 1fr', gap: 16, alignItems: 'start' }} className="themes-grid">
                {/* فهرست تیم‌ها */}
                <div className="panel">
                    <h3 style={{ marginTop: 0 }}>🏆 تیم‌ها</h3>
                    {teams.length === 0 && <p style={{ color: 'var(--muted)' }}>دانش‌آموزی با تیم در کلاس نیست.</p>}
                    <div style={{ display: 'grid', gap: 8 }}>
                        {teams.map((t, i) => (
                            <button key={t.theme_id} onClick={() => pick(t.theme_id)}
                                style={{ display: 'flex', alignItems: 'center', gap: 10, textAlign: 'start', cursor: 'pointer', fontFamily: 'inherit',
                                    border: selectedId === t.theme_id ? '2px solid var(--gold)' : '1px solid var(--line)', background: selectedId === t.theme_id ? '#fff8e8' : '#fff', borderRadius: 12, padding: '10px 12px' }}>
                                <span style={{ fontSize: 22 }}>{t.emoji}</span>
                                <span style={{ flex: 1, minWidth: 0 }}>
                                    <span style={{ fontWeight: 800, display: 'block' }}>{i === 0 ? '🥇 ' : ''}{t.name}</span>
                                    <span style={{ fontSize: 11.5, color: 'var(--muted)' }}>{fa(t.count)} عضو{t.bonus ? ` · گروهی ${t.bonus > 0 ? '+' : ''}${fa(t.bonus)}` : ''}</span>
                                </span>
                                <span className="tag" style={{ background: '#eef3ff', color: '#2555c0', fontWeight: 800 }}>⚡{fa(t.total)}</span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* مدیریتِ تیمِ انتخاب‌شده */}
                <div className="panel">
                    {!selected ? (
                        <div style={{ textAlign: 'center', padding: 30, color: 'var(--muted)' }}><div style={{ fontSize: 40 }}>🏆</div><p>یک تیم را انتخاب کنید تا امتیازش را مدیریت و دفترش را ببینید.</p></div>
                    ) : (
                        <>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                <h3 style={{ margin: 0 }}>{selected.emoji} {selected.name}</h3>
                                <span className="tag tag-info">مجموع: ⚡{fa(selected.total)}</span>
                                <span className="tag" style={{ background: '#eef7ee', color: '#1a8a52' }}>اعضا: {fa(selected.members_xp)}</span>
                                {selected.bonus !== 0 && <span className="tag" style={{ background: '#fff3d6', color: '#b9831a' }}>گروهی: {selected.bonus > 0 ? '+' : ''}{fa(selected.bonus)}</span>}
                            </div>

                            {/* کم/زیادِ گروهی */}
                            <div style={{ background: '#f6f8fc', border: '1px solid var(--line)', borderRadius: 12, padding: 12, marginTop: 12 }}>
                                <div style={{ fontWeight: 700, fontSize: 13, marginBottom: 8 }}>➕➖ امتیازِ کلِ تیم (مثبت = افزودن، منفی = کسر)</div>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'end', flexWrap: 'wrap' }}>
                                    <div className="field" style={{ margin: 0 }}><label>مقدار</label><input type="number" className="input" style={{ width: 110 }} value={form.data.amount} onChange={(e) => form.setData('amount', +e.target.value)} dir="ltr" /></div>
                                    <div className="field" style={{ margin: 0, flex: 1, minWidth: 160 }}><label>علت</label><input className="input" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="مثلاً: برنده‌ی مسابقه‌ی کلاسی" /></div>
                                    <button onClick={() => give()} className="btn">ثبت</button>
                                </div>
                                <div style={{ display: 'flex', gap: 6, marginTop: 10, flexWrap: 'wrap' }}>
                                    {QUICK.map((v) => <button key={v} onClick={() => give(v)} className="btn btn-sm" style={{ background: v >= 0 ? '#2bb673' : '#e8505b' }}>{v >= 0 ? '+' : ''}{fa(v)}</button>)}
                                </div>
                            </div>

                            {/* دفترِ ریز */}
                            <h3 style={{ marginBottom: 6, marginTop: 16 }}>📜 امتیاز از کجا آمده ({fa(ledger.length)})</h3>
                            {ledger.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز امتیازی ثبت نشده.</p>}
                            <div style={{ display: 'grid', gap: 6 }}>
                                {ledger.map((e, i) => (
                                    <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, border: '1px solid var(--line)', borderRadius: 10, padding: '8px 12px', background: e.kind === 'team' ? '#fff8e8' : '#fff' }}>
                                        <span style={{ fontWeight: 900, minWidth: 48, color: e.amount >= 0 ? '#16a34a' : '#dc2626' }}>{e.amount >= 0 ? '+' : ''}{fa(e.amount)}</span>
                                        <span style={{ flex: 1, fontSize: 13 }}>{e.reason}<span style={{ color: 'var(--muted)' }}> · {e.who}</span></span>
                                        <span style={{ color: 'var(--muted-2)', fontSize: 11.5 }}>{e.date}</span>
                                        {e.kind === 'team' && <button onClick={() => delEntry(e.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>🗑️</button>}
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
