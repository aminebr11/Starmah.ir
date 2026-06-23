import { Head, Link, useForm } from '@inertiajs/react';

/** فراموشی رمز — UI برند. (فعال‌سازی کامل بعداً با پیامک/ایمیل) */
export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.email')); };

    return (
        <div dir="rtl" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 24,
            background: 'radial-gradient(circle at 80% 10%,#16264f,#0a1733)' }}>
            <Head title="فراموشی رمز" />
            <div className="card" style={{ width: '100%', maxWidth: 420, background: '#fff' }}>
                <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 18, fontWeight: 800, color: 'var(--navy-800)', fontSize: 20 }}>
                    <img src="/brand/logo-emblem.png" alt="" style={{ width: 42, height: 42 }} /> ستاره ماه
                </Link>
                <h1 className="auth-h">فراموشی رمز عبور</h1>
                <p className="auth-sub">ایمیل یا موبایلت را وارد کن تا لینک بازیابی برایت ارسال شود.</p>

                {status && <div style={{ background: '#e3f7ec', color: '#176c45', padding: 12, borderRadius: 12, marginBottom: 16, fontSize: 13 }}>{status}</div>}

                <form onSubmit={submit}>
                    <div className="field">
                        <label>ایمیل یا موبایل</label>
                        <input className="input" value={data.email} autoFocus onChange={(e) => setData('email', e.target.value)} />
                        {errors.email && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{errors.email}</div>}
                    </div>
                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ارسال لینک بازیابی</button>
                </form>

                <div style={{ textAlign: 'center', marginTop: 18 }}>
                    <Link href={route('login')} className="link-gold" style={{ fontSize: 14 }}>← بازگشت به ورود</Link>
                </div>
            </div>
        </div>
    );
}
