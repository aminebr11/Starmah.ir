import { Head, useForm, router } from '@inertiajs/react';
import PasswordInput from '@/Components/PasswordInput';

/** تغییر اجباری رمز در اولین ورود (رمز موقت). */
export default function ForcePassword() {
    const { data, setData, post, processing, errors } = useForm({ password: '', password_confirmation: '' });
    const submit = (e) => { e.preventDefault(); post(route('password.force.update')); };

    // سنجش ساده‌ی قدرت رمز
    const pw = data.password;
    const score = [pw.length >= 6, pw.length >= 10, /[A-Za-z]/.test(pw), /\d/.test(pw), /[^A-Za-z0-9]/.test(pw)].filter(Boolean).length;
    const strength = pw ? Math.min(score, 4) : 0;
    const strengthLabel = ['خیلی ضعیف', 'ضعیف', 'متوسط', 'خوب', 'عالی'][strength];
    const strengthColor = ['#e8505b', '#e8505b', '#f5b53f', '#5b8def', '#2bb673'][strength];
    const match = data.password_confirmation.length > 0 && data.password === data.password_confirmation;

    return (
        <div dir="rtl" style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 20, background: 'radial-gradient(circle at 80% 10%,#16264f,#0a1733)' }}>
            <Head title="انتخاب رمز جدید" />
            <div className="card" style={{ width: '100%', maxWidth: 440, background: '#fff', padding: 32 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 18, fontWeight: 800, color: 'var(--navy-800)', fontSize: 20 }}>
                    <img src="/brand/logo-emblem.png" alt="" style={{ width: 44, height: 44, borderRadius: 12 }} /> ستاره ماه
                </div>
                <h1 className="auth-h" style={{ margin: '0 0 6px' }}>یک رمز جدید انتخاب کن 🔒</h1>
                <p className="auth-sub" style={{ margin: '0 0 22px' }}>برای امنیت حساب، در اولین ورود باید رمز موقت را تغییر دهی.</p>

                <form onSubmit={submit}>
                    <div className="field">
                        <label>رمز جدید</label>
                        <PasswordInput value={data.password} autoFocus autoComplete="new-password"
                            placeholder="حداقل ۶ کاراکتر" onChange={(e) => setData('password', e.target.value)} />
                        {pw && (
                            <div style={{ marginTop: 8 }}>
                                <div style={{ display: 'flex', gap: 4 }}>
                                    {[0, 1, 2, 3].map((i) => (
                                        <div key={i} style={{ flex: 1, height: 5, borderRadius: 4, background: i < strength ? strengthColor : 'var(--line)' }} />
                                    ))}
                                </div>
                                <div style={{ fontSize: 12, color: strengthColor, marginTop: 4, fontWeight: 700 }}>قدرت رمز: {strengthLabel}</div>
                            </div>
                        )}
                        {errors.password && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 6 }}>{errors.password}</div>}
                    </div>

                    <div className="field">
                        <label>تکرار رمز جدید</label>
                        <PasswordInput value={data.password_confirmation} autoComplete="new-password"
                            placeholder="رمز را دوباره وارد کن" onChange={(e) => setData('password_confirmation', e.target.value)} />
                        {data.password_confirmation.length > 0 && (
                            <div style={{ fontSize: 12, marginTop: 6, fontWeight: 700, color: match ? '#2bb673' : '#e8505b' }}>
                                {match ? '✅ رمزها یکسان‌اند' : '❌ رمزها یکسان نیستند'}
                            </div>
                        )}
                    </div>

                    <button type="submit" disabled={processing} className="btn" style={{ width: '100%', marginTop: 6 }}>
                        {processing ? 'در حال ثبت…' : 'ثبت رمز جدید و ادامه ←'}
                    </button>
                </form>

                <button onClick={() => router.post(route('logout'))} style={{ display: 'block', margin: '18px auto 0', background: 'none', border: 0, color: 'var(--muted)', cursor: 'pointer', fontFamily: 'inherit', fontSize: 13 }}>خروج از حساب</button>
            </div>
        </div>
    );
}
