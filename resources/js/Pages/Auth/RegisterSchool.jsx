import { useForm, Link, usePage } from '@inertiajs/react';
import WebLayout from '@/Layouts/WebLayout';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const toman = (n) => fa(Number(n).toLocaleString('en-US'));

export default function RegisterSchool() {
    const { plans = [], selectedPlan } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        school_name: '', manager_name: '', manager_phone: '', manager_email: '',
        city: '', level: 'دبستان', classes_count: 1, plan_key: selectedPlan || (plans[0]?.key ?? ''), note: '',
    });
    const submit = (e) => { e.preventDefault(); post(route('register.school.store')); };
    const chosen = plans.find((p) => p.key === data.plan_key);

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
                        {/* انتخابِ طرح */}
                        {plans.length > 0 && (
                            <div className="field">
                                <label>انتخابِ طرح</label>
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(130px,1fr))', gap: 10 }}>
                                    {plans.map((p) => (
                                        <button type="button" key={p.key} onClick={() => setData('plan_key', p.key)}
                                            style={{ textAlign: 'center', cursor: 'pointer', fontFamily: 'inherit', borderRadius: 14, padding: '12px 10px',
                                                border: data.plan_key === p.key ? '2px solid var(--gold)' : '1px solid var(--line)',
                                                background: data.plan_key === p.key ? '#fff8e8' : '#fff' }}>
                                            <div style={{ fontWeight: 800, color: 'var(--navy-800)' }}>{p.name}{p.highlighted ? ' ⭐' : ''}</div>
                                            <div style={{ fontSize: 13, color: 'var(--gold-2)', fontWeight: 800, marginTop: 3 }}>
                                                {p.price === 0 ? 'رایگان' : `${toman(p.price)} ت`}
                                            </div>
                                            {p.period_label && p.price > 0 && <div style={{ fontSize: 11, color: 'var(--muted)' }}>{p.period_label}</div>}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <Field label="توضیحات (اختیاری)" error={errors.note}>
                            <textarea className="input" rows="3" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                        </Field>

                        <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 6 }}>
                            {chosen && chosen.price > 0 ? `پرداخت و ثبت‌نام (${toman(chosen.price)} تومان)` : 'ارسال درخواست'}
                        </button>
                        {chosen && chosen.price > 0 && (
                            <p style={{ textAlign: 'center', marginTop: 8, color: 'var(--muted)', fontSize: 12 }}>
                                پس از ثبت، به درگاهِ پرداخت هدایت می‌شوید. اگر درگاه فعال نباشد، درخواست برای بررسیِ دستیِ مدیر ثبت می‌شود.
                            </p>
                        )}
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
