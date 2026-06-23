import { useForm, Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import WebLayout from '@/Layouts/WebLayout';

export default function RegisterStudent() {
    const { schools = [], themes = [] } = usePage().props;

    const { data, setData, post, processing, errors } = useForm({
        first_name: '', last_name: '', phone: '', password: '',
        school_id: '', classroom_id: '', theme_id: '',
    });

    const classes = useMemo(
        () => schools.find((s) => String(s.id) === String(data.school_id))?.classes ?? [],
        [data.school_id, schools]
    );

    const submit = (e) => { e.preventDefault(); post(route('register.student.store')); };

    return (
        <WebLayout title="ثبت‌نام دانش‌آموز">
            <div className="container section">
                <div style={{ maxWidth: 560, margin: '0 auto' }}>
                    <div className="section-head" style={{ marginBottom: 22 }}>
                        <span className="eyebrow">🎓 خوش آمدی!</span>
                        <h2>ثبت‌نام دانش‌آموز</h2>
                        <p>مدرسه و معلمت را انتخاب کن و دنیای دلخواهت را بساز</p>
                    </div>

                    <form onSubmit={submit} className="card">
                        <div className="grid grid-2-form">
                            <Field label="نام" error={errors.first_name}>
                                <input className="input" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                            </Field>
                            <Field label="نام خانوادگی" error={errors.last_name}>
                                <input className="input" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                            </Field>
                        </div>
                        <div className="grid grid-2-form">
                            <Field label="شماره موبایل" error={errors.phone}>
                                <input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="09xxxxxxxxx" />
                            </Field>
                            <Field label="رمز عبور" error={errors.password}>
                                <input type="password" className="input" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                            </Field>
                        </div>

                        <Field label="مدرسه" error={errors.classroom_id}>
                            <select className="input" value={data.school_id} onChange={(e) => { setData('school_id', e.target.value); setData('classroom_id', ''); }}>
                                <option value="">— انتخاب مدرسه —</option>
                                {schools.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
                        </Field>

                        <Field label="معلم / کلاس">
                            <select className="input" value={data.classroom_id} onChange={(e) => setData('classroom_id', e.target.value)} disabled={!data.school_id}>
                                <option value="">{data.school_id ? '— انتخاب معلم —' : 'ابتدا مدرسه را انتخاب کن'}</option>
                                {classes.map((c) => <option key={c.classroom_id} value={c.classroom_id}>{c.teacher_name} — {c.class_name}</option>)}
                            </select>
                        </Field>

                        <div className="field">
                            <label>دنیای دلخواهت را انتخاب کن 🌟</label>
                            <div className="theme-pick">
                                {themes.map((t) => (
                                    <button type="button" key={t.id} onClick={() => setData('theme_id', t.id)}
                                        className={`theme-opt ${String(data.theme_id) === String(t.id) ? 'sel' : ''}`}
                                        style={{ background: `linear-gradient(135deg,${t.skin?.p2 ?? '#16264f'},${t.skin?.bg1 ?? '#0e1c3d'})` }}>
                                        <span style={{ fontSize: 26 }}>{t.emoji}</span>
                                        <b>{t.name}</b>
                                    </button>
                                ))}
                            </div>
                            {errors.theme_id && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>یک دنیا انتخاب کن</div>}
                        </div>

                        <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 6 }}>
                            ثبت‌نام و شروع 🚀
                        </button>
                        <p style={{ textAlign: 'center', marginTop: 14, color: 'var(--muted)', fontSize: 13 }}>
                            قبلاً ثبت‌نام کرده‌ای؟ <Link href={route('login')} style={{ color: 'var(--gold-2)', fontWeight: 700 }}>وارد شو</Link>
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
