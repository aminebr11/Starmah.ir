import { Head, Link, useForm } from '@inertiajs/react';

/** ورود — طراحی دونیمه‌ی کیهانی، با لینک فراموشی رمز. */
export default function Login({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        login: '', password: '', remember: false,
    });
    const submit = (e) => { e.preventDefault(); post(route('login'), { onFinish: () => reset('password') }); };

    return (
        <div dir="rtl" className="auth-split">
            <Head title="ورود" />

            {/* سمت تصویری/برند */}
            <div className="auth-side">
                <div className="stars-bg" />
                <span className="floatemoji" style={{ top: 40, insetInlineStart: 50, fontSize: 30 }}>⭐</span>
                <span className="floatemoji" style={{ bottom: 60, insetInlineEnd: 70, fontSize: 26, animationDelay: '-2s' }}>🚀</span>
                <span className="floatemoji" style={{ top: '40%', insetInlineEnd: 40, fontSize: 22, animationDelay: '-4s' }}>🌙</span>
                <div className="brandrow">
                    <img src="/brand/logo-emblem.png" alt="ستاره ماه" />
                    <span style={{ fontWeight: 800, fontSize: 22 }}>ستاره ماه</span>
                </div>
                <h2>به دنیای یادگیریِ خودت<br />خوش برگشتی! 🌟</h2>
                <p>وارد شو و سفر آموزشی‌ات را بر اساس علاقه‌ات ادامه بده.</p>
                <div className="chips"><span>🎮 بازی و آزمون</span><span>🏆 امتیاز</span><span>📈 پیشرفت</span></div>
            </div>

            {/* فرم ورود */}
            <div className="auth-form">
                <div className="auth-form-inner">
                    <Link href="/" className="logo-sm"><img src="/brand/logo-emblem.png" alt="" /> ستاره ماه</Link>
                    <h1 className="auth-h">ورود به حساب</h1>
                    <p className="auth-sub">با شماره موبایل یا ایمیل وارد شو</p>

                    {status && <div style={{ background: '#e3f7ec', color: '#176c45', padding: 12, borderRadius: 12, marginBottom: 16, fontSize: 13 }}>{status}</div>}

                    <form onSubmit={submit}>
                        <div className="field">
                            <label>موبایل یا ایمیل</label>
                            <input id="login" className="input" value={data.login} autoFocus
                                onChange={(e) => setData('login', e.target.value)} placeholder="09xxxxxxxxx" />
                            {errors.login && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{errors.login}</div>}
                        </div>

                        <div className="field">
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <label>رمز عبور</label>
                                <Link href={route('password.request')} className="link-gold" style={{ fontSize: 12 }}>فراموشی رمز؟</Link>
                            </div>
                            <input id="password" type="password" className="input" value={data.password}
                                onChange={(e) => setData('password', e.target.value)} />
                            {errors.password && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{errors.password}</div>}
                        </div>

                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--muted)', margin: '4px 0 18px', cursor: 'pointer' }}>
                            <input type="checkbox" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                            مرا به خاطر بسپار
                        </label>

                        <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ورود 🚀</button>
                    </form>

                    <div className="divider">یا</div>
                    <div style={{ textAlign: 'center', color: 'var(--muted)', fontSize: 14 }}>
                        حساب نداری؟ <Link href="/register" className="link-gold">ثبت‌نام کن</Link>
                    </div>
                    <div style={{ textAlign: 'center', marginTop: 18 }}>
                        <Link href="/" style={{ color: 'var(--muted-2)', fontSize: 13 }}>← بازگشت به صفحه‌ی اصلی</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
