import { Head, useForm, router } from '@inertiajs/react';

/** تغییر اجباری رمز در اولین ورود (رمز موقت). */
export default function ForcePassword() {
    const { data, setData, post, processing, errors } = useForm({ password: '', password_confirmation: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.force.update')); };

    return (
        <div dir="rtl" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 24, background: 'radial-gradient(circle at 80% 10%,#16264f,#0a1733)' }}>
            <Head title="تغییر رمز" />
            <div className="card" style={{ width: '100%', maxWidth: 420, background: '#fff' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 16, fontWeight: 800, color: 'var(--navy-800)', fontSize: 20 }}>
                    <img src="/brand/logo-emblem.png" alt="" style={{ width: 42, height: 42 }} /> ستاره ماه
                </div>
                <h1 className="auth-h">یک رمز جدید انتخاب کن 🔒</h1>
                <p className="auth-sub">برای امنیت حساب، در اولین ورود باید رمز موقت را تغییر دهی.</p>
                <form onSubmit={submit}>
                    <div className="field"><label>رمز جدید</label>
                        <input type="password" className="input" value={data.password} autoFocus onChange={(e) => setData('password', e.target.value)} />
                        {errors.password && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{errors.password}</div>}
                    </div>
                    <div className="field"><label>تکرار رمز جدید</label>
                        <input type="password" className="input" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                    </div>
                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%' }}>ثبت رمز جدید و ادامه</button>
                </form>
                <button onClick={() => router.post(route('logout'))} style={{ display: 'block', margin: '16px auto 0', background: 'none', border: 0, color: 'var(--muted)', cursor: 'pointer', fontFamily: 'inherit', fontSize: 13 }}>خروج</button>
            </div>
        </div>
    );
}
