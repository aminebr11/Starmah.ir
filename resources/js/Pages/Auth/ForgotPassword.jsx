import { Head, Link, useForm } from '@inertiajs/react';

/** فراموشی رمز — UI برند. */
export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.email')); };

    return (
        <div dir="rtl" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 20,
            background: 'radial-gradient(circle at 80% 10%,#16264f,#0a1733)' }}>
            <Head title="فراموشی رمز" />
            <div className="card" style={{ width: '100%', maxWidth: 430, textAlign: 'center' }}>
                <div style={{ width: 74, height: 74, margin: '0 auto 14px', borderRadius: '50%', display: 'grid', placeItems: 'center',
                    fontSize: 34, background: 'linear-gradient(135deg,#fff3d6,#ffe0a0)', boxShadow: '0 12px 30px -12px rgba(245,181,63,.7)' }}>🔑</div>
                <h1 className="auth-h" style={{ margin: '0 0 6px' }}>رمزت را فراموش کردی؟</h1>
                <p className="auth-sub" style={{ margin: '0 0 22px' }}>نگران نباش! ایمیل یا شماره‌ی موبایلت را وارد کن تا لینک بازیابی برایت بفرستیم.</p>

                {status && <div style={{ background: '#e3f7ec', color: '#176c45', padding: 12, borderRadius: 12, marginBottom: 16, fontSize: 13, fontWeight: 700 }}>✅ {status}</div>}

                <form onSubmit={submit} style={{ textAlign: 'right' }}>
                    <div className="field">
                        <label>ایمیل یا موبایل</label>
                        <input className="input" value={data.email} autoFocus onChange={(e) => setData('email', e.target.value)} placeholder="example@mail.com یا 09xxxxxxxxx" />
                        {errors.email && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{errors.email}</div>}
                    </div>
                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 4 }}>
                        {processing ? 'در حال ارسال…' : '📩 ارسال لینک بازیابی'}
                    </button>
                </form>

                <div style={{ marginTop: 20, paddingTop: 16, borderTop: '1px solid var(--line)', fontSize: 14, color: 'var(--muted)' }}>
                    رمزت یادت آمد؟ <Link href={route('login')} className="link-gold" style={{ fontWeight: 700 }}>بازگشت به ورود ←</Link>
                </div>
            </div>
        </div>
    );
}
