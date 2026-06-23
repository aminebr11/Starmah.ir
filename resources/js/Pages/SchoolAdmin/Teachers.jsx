import { usePage, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import WebLayout from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

export default function Teachers() {
    const { teachers = [], school, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(flash.flash); }, [flash]);

    const { data, setData, post, processing, errors, reset } = useForm({ name: '', phone: '', class_name: '', password: '' });
    const submit = (e) => { e.preventDefault(); post(route('school.teachers.store'), { preserveScroll: true, onSuccess: () => reset() }); };

    return (
        <WebLayout title="مدیریت معلم‌ها">
            <div className="container section">
                <h2 style={{ color: 'var(--navy-800)' }}>پنل مدیر مدرسه{school ? ` — ${school.name}` : ''} 🏫</h2>
                <p style={{ color: 'var(--muted)' }}>برای هر کلاس یک معلم بساز؛ کد کلاس برای دانش‌آموزان تولید می‌شود.</p>

                {banner && (
                    <div className="card" style={{ borderColor: 'var(--gold)', background: '#fff8e8', marginTop: 14 }}>
                        <b>{banner.message}</b>
                    </div>
                )}

                <div className="grid" style={{ gridTemplateColumns: '1fr 1.4fr', gap: 22, marginTop: 18, alignItems: 'start' }}>
                    {/* فرم ساخت معلم */}
                    <form onSubmit={submit} className="card">
                        <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>➕ معلم جدید</h3>
                        <Field label="نام معلم" error={errors.name}>
                            <input className="input" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        </Field>
                        <Field label="شماره موبایل" error={errors.phone}>
                            <input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="09xxxxxxxxx" />
                        </Field>
                        <Field label="نام کلاس" error={errors.class_name}>
                            <input className="input" value={data.class_name} onChange={(e) => setData('class_name', e.target.value)} placeholder="مثلاً: چهارم الف" />
                        </Field>
                        <Field label="رمز عبور (اختیاری — خودکار ساخته می‌شود)" error={errors.password}>
                            <input className="input" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        </Field>
                        <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ساخت معلم و کلاس</button>
                    </form>

                    {/* لیست معلم‌ها */}
                    <div>
                        <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>معلم‌ها ({fa(teachers.length)})</h3>
                        {teachers.length === 0 && <p style={{ color: 'var(--muted)' }}>هنوز معلمی ساخته نشده.</p>}
                        {teachers.map((t) => (
                            <div key={t.id} className="card" style={{ marginBottom: 10, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                                <div>
                                    <div style={{ fontWeight: 800 }}>{t.name}</div>
                                    <div style={{ color: 'var(--muted)', fontSize: 13 }}>{t.phone} · {t.class_name ?? 'بدون کلاس'}</div>
                                </div>
                                <div style={{ textAlign: 'left' }}>
                                    <div style={{ fontWeight: 800, color: 'var(--gold-2)' }}>کد: {t.join_code}</div>
                                    <div style={{ color: 'var(--muted)', fontSize: 12 }}>{fa(t.students)} دانش‌آموز</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </WebLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div className="field">
            <label>{label}</label>
            {children}
            {error && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{error}</div>}
        </div>
    );
}
