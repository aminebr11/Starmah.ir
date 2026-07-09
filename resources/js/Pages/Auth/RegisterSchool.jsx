import { useForm, Link } from '@inertiajs/react';
import WebLayout from '@/Layouts/WebLayout';

export default function RegisterSchool() {
    const { data, setData, post, processing, errors } = useForm({
        school_name: '', manager_name: '', manager_phone: '', manager_email: '',
        city: '', level: 'دبستان', classes_count: 1, note: '',
    });
    const submit = (e) => { e.preventDefault(); post(route('register.school.store')); };

    return (
        <WebLayout title="ثبت‌نام مدرسه">
            <div className="container section">
                <div style={{ maxWidth: 560, margin: '0 auto' }}>
                    <div className="section-head" style={{ marginBottom: 22 }}>
                        <span className="eyebrow">🏫 ویژه‌ی مدیران مدرسه</span>
                        <h2>درخواست ثبت‌نام مدرسه</h2>
                        <p>درخواست شما پس از بررسی توسط تیم ستاره ماه تأیید و حساب مدرسه ساخته می‌شود</p>
                    </div>

                    <form onSubmit={submit} className="card">
                        <Field label="نام مدرسه" error={errors.school_name}>
                            <input className="input" value={data.school_name} onChange={(e) => setData('school_name', e.target.value)} placeholder="مثلاً: دبستان مهر" />
                        </Field>
                        <div className="grid grid-2-form">
                            <Field label="نام مدیر" error={errors.manager_name}>
                                <input className="input" value={data.manager_name} onChange={(e) => setData('manager_name', e.target.value)} />
                            </Field>
                            <Field label="شماره موبایل مدیر" error={errors.manager_phone}>
                                <input className="input" value={data.manager_phone} onChange={(e) => setData('manager_phone', e.target.value)} placeholder="09xxxxxxxxx" />
                            </Field>
                        </div>
                        <div className="grid grid-2-form">
                            <Field label="ایمیل (اختیاری)" error={errors.manager_email}>
                                <input className="input" value={data.manager_email} onChange={(e) => setData('manager_email', e.target.value)} />
                            </Field>
                            <Field label="شهر" error={errors.city}>
                                <input className="input" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                            </Field>
                        </div>
                        <div className="grid grid-2-form">
                            <Field label="مقطع تحصیلی" error={errors.level}>
                                <select className="input" value={data.level} onChange={(e) => setData('level', e.target.value)}>
                                    <option value="دبستان">دبستان</option>
                                    <option value="متوسطه اول">متوسطه اول</option>
                                    <option value="متوسطه دوم">متوسطه دوم</option>
                                </select>
                            </Field>
                            <Field label="تعداد کلاس‌ها" error={errors.classes_count}>
                                <input type="number" min="1" max="200" className="input" value={data.classes_count} onChange={(e) => setData('classes_count', e.target.value)} />
                            </Field>
                        </div>
                        <Field label="توضیحات (اختیاری)" error={errors.note}>
                            <textarea className="input" rows="3" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                        </Field>

                        <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 6 }}>
                            ارسال درخواست
                        </button>
                        <p style={{ textAlign: 'center', marginTop: 14, color: 'var(--muted)', fontSize: 13 }}>
                            دانش‌آموز هستی؟ <Link href="/register/student" style={{ color: 'var(--gold-2)', fontWeight: 700 }}>از اینجا ثبت‌نام کن</Link>
                        </p>
                    </form>
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
