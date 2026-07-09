import { usePage, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DashLayout, { adminMenu } from '@/Layouts/DashLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const money = (n) => (Number(n) ? fa(Number(n).toLocaleString('en-US')) + ' تومان' : 'رایگان');
const lim = (v) => (v === null || v === '' || v === undefined ? 'نامحدود' : fa(v));

export default function Plans() {
    const { plans = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    const [editing, setEditing] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const form = useForm({ key: '', name: '', description: '', max_classes: '', max_students_per_class: '', duration_days: '', price: 0, is_active: true });

    const startEdit = (p) => {
        setEditing(p.id);
        form.setData({
            key: p.key, name: p.name, description: p.description || '',
            max_classes: p.max_classes ?? '', max_students_per_class: p.max_students_per_class ?? '',
            duration_days: p.duration_days ?? '', price: p.price ?? 0, is_active: p.is_active,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    const cancel = () => { setEditing(null); form.reset(); form.clearErrors(); };

    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => cancel() };
        if (editing) form.put(route('admin.plans.update', editing), opts);
        else form.post(route('admin.plans.store'), opts);
    };

    const toggle = (id) => router.post(route('admin.plans.toggle', id), {}, { preserveScroll: true });
    const remove = (id) => { if (confirm('این طرح حذف شود؟')) router.delete(route('admin.plans.destroy', id), { preserveScroll: true }); };

    return (
        <DashLayout title="طرح‌های اشتراک" roleLabel="ادمین کل" menu={adminMenu} active="plans">
            {banner && <div className="panel" style={{ borderColor: 'var(--gold)', background: '#fff8e8' }}><b>{banner}</b></div>}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 20, alignItems: 'start' }} className="themes-grid">
                {/* فرم ساخت/ویرایش */}
                <form onSubmit={submit} className="panel">
                    <h3>{editing ? '✏️ ویرایش طرح' : '➕ طرح جدید'}</h3>
                    <Field label="نام طرح" err={form.errors.name}>
                        <input className="input" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="مثلاً نسخه‌ی طلایی" />
                    </Field>
                    <Field label="توضیح کوتاه" err={form.errors.description}>
                        <input className="input" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="حداکثر کلاس" err={form.errors.max_classes}>
                            <input type="number" min="1" className="input" value={form.data.max_classes} onChange={(e) => form.setData('max_classes', e.target.value)} placeholder="خالی = نامحدود" />
                        </Field>
                        <Field label="دانش‌آموز در هر کلاس" err={form.errors.max_students_per_class}>
                            <input type="number" min="1" className="input" value={form.data.max_students_per_class} onChange={(e) => form.setData('max_students_per_class', e.target.value)} placeholder="خالی = نامحدود" />
                        </Field>
                        <Field label="مدت (روز)" err={form.errors.duration_days}>
                            <input type="number" min="1" className="input" value={form.data.duration_days} onChange={(e) => form.setData('duration_days', e.target.value)} placeholder="خالی = بدون انقضا" />
                        </Field>
                        <Field label="قیمت (تومان)" err={form.errors.price}>
                            <input type="number" min="0" className="input" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
                        </Field>
                    </div>
                    {editing && (
                        <Field label="کلید (شناسه‌ی انگلیسی)" err={form.errors.key}>
                            <input className="input" value={form.data.key} onChange={(e) => form.setData('key', e.target.value)} dir="ltr" />
                        </Field>
                    )}
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '6px 0 14px', fontSize: 14 }}>
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} /> طرح فعال باشد
                    </label>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button type="submit" disabled={form.processing} className="btn">{editing ? '💾 ذخیره تغییرات' : '➕ ساخت طرح'}</button>
                        {editing && <button type="button" onClick={cancel} className="btn btn-ghost">انصراف</button>}
                    </div>
                    <p style={{ color: 'var(--muted)', fontSize: 12, marginTop: 10 }}>فیلدهای محدودیت را خالی بگذارید تا «نامحدود» شود.</p>
                </form>

                {/* لیست طرح‌ها */}
                <div className="panel">
                    <h3>🎟️ طرح‌های موجود ({fa(plans.length)})</h3>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        {plans.map((p) => (
                            <div key={p.id} style={{ border: '1px solid var(--line)', borderRadius: 16, padding: 16, opacity: p.is_active ? 1 : .6 }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                    <div style={{ fontWeight: 800, fontSize: 16 }}>
                                        {p.name} <span style={{ color: 'var(--muted)', fontWeight: 400, fontSize: 12 }}>({p.key})</span>
                                        {!p.is_active && <span className="tag tag-warn" style={{ marginInlineStart: 8 }}>غیرفعال</span>}
                                    </div>
                                    <div style={{ fontWeight: 800, color: 'var(--gold-2)' }}>{money(p.price)}</div>
                                </div>
                                {p.description && <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 4 }}>{p.description}</div>}
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', margin: '12px 0' }}>
                                    <span className="tag tag-info">🏛️ {lim(p.max_classes)} کلاس</span>
                                    <span className="tag tag-info">🎓 {lim(p.max_students_per_class)} دانش‌آموز/کلاس</span>
                                    <span className="tag tag-info">🗓️ {p.duration_days ? fa(p.duration_days) + ' روز' : 'بدون انقضا'}</span>
                                    <span className="tag tag-ok">🏫 {fa(p.schools)} مدرسه</span>
                                </div>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    <button onClick={() => startEdit(p)} className="btn btn-ghost btn-sm">✏️ ویرایش</button>
                                    <button onClick={() => toggle(p.id)} className="btn btn-ghost btn-sm">{p.is_active ? '⏸️ غیرفعال' : '▶️ فعال'}</button>
                                    {p.schools === 0 && <button onClick={() => remove(p.id)} className="btn btn-ghost btn-sm" style={{ color: '#e8505b' }}>حذف</button>}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </DashLayout>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
