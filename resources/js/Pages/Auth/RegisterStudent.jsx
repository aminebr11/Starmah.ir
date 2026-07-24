import { useForm, Link, usePage, Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const GRADES = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'];

/** ثبت‌نامِ کاملِ دانش‌آموز — طراحیِ دونیمه‌ی کیهانی، اطلاعاتِ کامل + سرپرست/والدین. */
export default function RegisterStudent() {
    const { schools = [], themes = [] } = usePage().props;
    const [showPass, setShowPass] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        first_name: '', last_name: '', gender: '', national_id: '', birth_date: '', grade: '',
        school_id: '', classroom_id: '', theme_id: '',
        father_name: '', mother_name: '', parent_relation: 'پدر', parent_phone: '', address: '',
        parent_pin: String(Math.floor(10000 + Math.random() * 90000)),
        phone: '', password: '', password_confirmation: '',
    });

    const classes = useMemo(
        () => schools.find((s) => String(s.id) === String(data.school_id))?.classes ?? [],
        [data.school_id, schools]
    );
    const pickedTheme = themes.find((t) => String(t.id) === String(data.theme_id));

    const submit = (e) => { e.preventDefault(); post(route('register.student.store')); };

    return (
        <div dir="rtl" className="auth-split">
            <Head title="ثبت‌نام دانش‌آموز" />

            {/* سمتِ برند/کیهانی */}
            <div className="auth-side">
                <div className="stars-bg" />
                <span className="floatemoji" style={{ top: 44, insetInlineStart: 52, fontSize: 30 }}>⭐</span>
                <span className="floatemoji" style={{ bottom: 70, insetInlineEnd: 72, fontSize: 26, animationDelay: '-2s' }}>🚀</span>
                <span className="floatemoji" style={{ top: '44%', insetInlineEnd: 40, fontSize: 22, animationDelay: '-4s' }}>🌙</span>
                <div className="brandrow">
                    <img src="/brand/logo-emblem.png" alt="ستاره ماه" />
                    <span style={{ fontWeight: 800, fontSize: 22 }}>ستاره ماه</span>
                </div>
                <h2>به دنیای یادگیریِ بازی‌گونه<br />خوش اومدی! 🌟</h2>
                <p>فرم را کامل پر کن، دنیای دلخواهت را انتخاب کن و همین حالا سفرِ یادگیری‌ات را شروع کن.</p>
                {pickedTheme && (
                    <div style={{ position: 'relative', zIndex: 2, marginTop: 24, display: 'inline-flex', alignItems: 'center', gap: 10, background: 'rgba(255,255,255,.1)', border: '1px solid rgba(255,255,255,.2)', borderRadius: 16, padding: '10px 16px' }}>
                        <span style={{ fontSize: 26 }}>{pickedTheme.emoji}</span>
                        <div><div style={{ fontSize: 11, opacity: .8 }}>دنیای انتخابیِ تو</div><b>{pickedTheme.name}</b></div>
                    </div>
                )}
                <div className="chips"><span>🎮 بازی و آزمون</span><span>🏆 امتیازِ گروهی</span><span>📈 پیشرفت</span></div>
            </div>

            {/* فرمِ ثبت‌نام */}
            <div className="auth-form">
                <div className="auth-form-inner auth-form--wide">
                    <Link href="/" className="logo-sm"><img src="/brand/logo-emblem.png" alt="" /> ستاره ماه</Link>
                    <h1 className="auth-h">ثبت‌نامِ دانش‌آموز</h1>
                    <p className="auth-sub">مشخصاتت را کامل وارد کن تا حسابت ساخته شود</p>

                    <form onSubmit={submit}>
                        {/* بخش ۱ — مشخصاتِ دانش‌آموز */}
                        <div className="auth-sec">
                            <div className="auth-sec-h"><span className="n">۱</span> مشخصاتِ دانش‌آموز</div>
                            <div className="grid-2-form">
                                <Field label="نام" error={errors.first_name} req>
                                    <input className="input" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                                </Field>
                                <Field label="نام خانوادگی" error={errors.last_name} req>
                                    <input className="input" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                                </Field>
                            </div>
                            <Field label="جنسیت" error={errors.gender}>
                                <div className="pick-row">
                                    {['پسر', 'دختر'].map((g) => (
                                        <button type="button" key={g} className={`pick-chip ${data.gender === g ? 'on' : ''}`} onClick={() => setData('gender', g)}>
                                            {g === 'پسر' ? '👦' : '👧'} {g}
                                        </button>
                                    ))}
                                </div>
                            </Field>
                            <div className="grid-2-form">
                                <Field label="تاریخِ تولد" error={errors.birth_date}>
                                    <JalaliDatePicker value={data.birth_date} onChange={(v) => setData('birth_date', v)} placeholder="۱۳۹۰/۰۱/۰۱" />
                                </Field>
                                <Field label="کدِ ملی" error={errors.national_id}>
                                    <input className="input" value={data.national_id} onChange={(e) => setData('national_id', e.target.value.replace(/\D/g, '').slice(0, 10))} placeholder="۱۰ رقم" dir="ltr" inputMode="numeric" />
                                </Field>
                            </div>
                            <Field label="پایه‌ی تحصیلی" error={errors.grade}>
                                <select className="input" value={data.grade} onChange={(e) => setData('grade', e.target.value)}>
                                    <option value="">— انتخاب پایه —</option>
                                    {GRADES.map((g) => <option key={g} value={g}>{g}</option>)}
                                </select>
                            </Field>
                        </div>

                        {/* بخش ۲ — مدرسه و دنیا */}
                        <div className="auth-sec">
                            <div className="auth-sec-h"><span className="n">۲</span> مدرسه و دنیای دلخواه</div>
                            <Field label="مدرسه" error={errors.classroom_id} req>
                                <select className="input" value={data.school_id} onChange={(e) => { setData('school_id', e.target.value); setData('classroom_id', ''); }}>
                                    <option value="">— انتخاب مدرسه —</option>
                                    {schools.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </Field>
                            <Field label="معلم / کلاس" req>
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
                                            style={{ background: `linear-gradient(135deg,${t.skin?.p2 ?? '#16264f'},${t.skin?.bg1 ?? '#0e1c3d'})`, color: '#fff' }}>
                                            <span style={{ fontSize: 26 }}>{t.emoji}</span>
                                            <b>{t.name}</b>
                                        </button>
                                    ))}
                                </div>
                                {errors.theme_id && <div className="err-msg">یک دنیا انتخاب کن</div>}
                            </div>
                        </div>

                        {/* بخش ۳ — اطلاعاتِ سرپرست */}
                        <div className="auth-sec">
                            <div className="auth-sec-h"><span className="n">۳</span> اطلاعاتِ والدین / سرپرست</div>
                            <div className="grid-2-form">
                                <Field label="نامِ پدر" error={errors.father_name}>
                                    <input className="input" value={data.father_name} onChange={(e) => setData('father_name', e.target.value)} />
                                </Field>
                                <Field label="نامِ مادر" error={errors.mother_name}>
                                    <input className="input" value={data.mother_name} onChange={(e) => setData('mother_name', e.target.value)} />
                                </Field>
                            </div>
                            <div className="grid-2-form">
                                <Field label="نسبتِ سرپرست" error={errors.parent_relation}>
                                    <select className="input" value={data.parent_relation} onChange={(e) => setData('parent_relation', e.target.value)}>
                                        <option value="پدر">پدر</option>
                                        <option value="مادر">مادر</option>
                                        <option value="ولی">ولیِّ دیگر</option>
                                    </select>
                                </Field>
                                <Field label="موبایلِ والد / سرپرست" error={errors.parent_phone} req>
                                    <input className="input" value={data.parent_phone} onChange={(e) => setData('parent_phone', e.target.value)} placeholder="09xxxxxxxxx" dir="ltr" inputMode="tel" />
                                </Field>
                            </div>
                            <Field label="آدرسِ منزل" error={errors.address}>
                                <textarea className="input" rows={2} value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="اختیاری" style={{ resize: 'vertical' }} />
                            </Field>
                            <div style={{ background: '#fff8e8', border: '1px solid rgba(245,181,63,.5)', borderRadius: 14, padding: '12px 14px' }}>
                                <Field label="🔐 رمزِ بخشِ والدین (این رمز را فقط والدین بدانند)" error={errors.parent_pin}>
                                    <input className="input" value={data.parent_pin} dir="ltr" inputMode="numeric" style={{ letterSpacing: 4, fontWeight: 800, maxWidth: 180 }}
                                        onChange={(e) => setData('parent_pin', e.target.value.replace(/\D/g, '').slice(0, 8))} />
                                </Field>
                                <div style={{ fontSize: 11.5, color: 'var(--muted)', lineHeight: 1.9 }}>
                                    والدین با این رمز، از داخلِ حسابِ دانش‌آموز واردِ «بخشِ والدین» می‌شوند و
                                    گزارش‌های محرمانه و پیام‌های معلم/مدیر را می‌بینند. آن را یادداشت کنید ✍️
                                </div>
                            </div>
                        </div>

                        {/* بخش ۴ — ورود به حساب */}
                        <div className="auth-sec">
                            <div className="auth-sec-h"><span className="n">۴</span> اطلاعاتِ ورود به حساب</div>
                            <Field label="شماره موبایلِ دانش‌آموز (برای ورود)" error={errors.phone} req>
                                <input className="input" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="09xxxxxxxxx" dir="ltr" inputMode="tel" />
                            </Field>
                            <div className="grid-2-form">
                                <Field label="رمزِ عبور" error={errors.password} req>
                                    <div style={{ position: 'relative' }}>
                                        <input type={showPass ? 'text' : 'password'} className="input" value={data.password} onChange={(e) => setData('password', e.target.value)} style={{ paddingInlineEnd: 40 }} />
                                        <button type="button" onClick={() => setShowPass(!showPass)} title={showPass ? 'پنهان' : 'نمایش'}
                                            style={{ position: 'absolute', insetInlineEnd: 8, top: '50%', transform: 'translateY(-50%)', background: 'none', border: 0, cursor: 'pointer', fontSize: 16 }}>
                                            {showPass ? '🙈' : '👁️'}
                                        </button>
                                    </div>
                                </Field>
                                <Field label="تکرارِ رمزِ عبور" error={errors.password_confirmation} req>
                                    <input type={showPass ? 'text' : 'password'} className="input" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                                </Field>
                            </div>
                            <div style={{ fontSize: 11.5, color: 'var(--muted-2)', marginTop: -4 }}>حداقل ۶ کاراکتر. این شماره و رمز برای ورودِ دانش‌آموز استفاده می‌شود.</div>
                        </div>

                        <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 18 }}>
                            {processing ? 'در حالِ ثبت…' : 'ثبت‌نام و شروع 🚀'}
                        </button>
                        <p style={{ textAlign: 'center', marginTop: 16, color: 'var(--muted)', fontSize: 13 }}>
                            قبلاً ثبت‌نام کرده‌ای؟ <Link href={route('login')} className="link-gold" style={{ fontWeight: 700 }}>وارد شو</Link>
                        </p>
                        <div style={{ textAlign: 'center', marginTop: 10 }}>
                            <Link href="/register" style={{ color: 'var(--muted-2)', fontSize: 13 }}>← بازگشت به انتخابِ نوعِ ثبت‌نام</Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}

function Field({ label, error, children, req = false }) {
    return (
        <div className="field">
            <label>{label}{req && <span style={{ color: '#e8505b', marginInlineStart: 3 }}>*</span>}</label>
            {children}
            {error && <div className="err-msg">{error}</div>}
        </div>
    );
}
