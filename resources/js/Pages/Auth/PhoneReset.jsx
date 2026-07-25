import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

/** بازیابیِ رمز با کدِ پیامکی — دو گام: ارسالِ کد، سپس تأیید و رمزِ جدید. */
export default function PhoneReset() {
    const { smsEnabled, sent, phone: sentPhone, flash } = usePage().props;
    const [step, setStep] = useState(sent ? 2 : 1);
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) { setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); setStep(2); } }, [flash]);

    const sendForm = useForm({ phone: sentPhone || '' });
    const resetForm = useForm({ phone: sentPhone || '', code: '', password: '', password_confirmation: '' });

    const doSend = (e) => { e.preventDefault(); sendForm.post(route('password.phone.send'), { onSuccess: () => { resetForm.setData('phone', sendForm.data.phone); } }); };
    const doReset = (e) => { e.preventDefault(); resetForm.post(route('password.phone.reset')); };

    return (
        <div dir="rtl" className="auth-split">
            <Head title="بازیابیِ رمز با پیامک" />
            <div className="auth-side">
                <div className="stars-bg" />
                <span className="floatemoji" style={{ top: 50, insetInlineStart: 50, fontSize: 28 }}>🔑</span>
                <div className="brandrow">
                    <img src="/brand/logo-emblem.png" alt="ستاره ماه" />
                    <span style={{ fontWeight: 800, fontSize: 22 }}>ستاره ماه</span>
                </div>
                <h2>رمزت را فراموش کردی؟<br />با پیامک بازیابی کن 📱</h2>
                <p>کدِ تأیید به شماره‌ی موبایلت پیامک می‌شود؛ سپس رمزِ جدیدت را تنظیم کن.</p>
            </div>

            <div className="auth-form">
                <div className="auth-form-inner">
                    <Link href="/" className="logo-sm"><img src="/brand/logo-emblem.png" alt="" /> ستاره ماه</Link>
                    <h1 className="auth-h">بازیابیِ رمز با پیامک</h1>
                    <p className="auth-sub">{step === 1 ? 'شماره‌ی موبایلِ حسابت را وارد کن' : 'کدِ پیامک‌شده و رمزِ جدید را وارد کن'}</p>

                    {!smsEnabled && (
                        <div style={{ background: '#fdeedd', color: '#a05a12', padding: 12, borderRadius: 12, marginBottom: 14, fontSize: 12.5 }}>
                            ⚠️ سامانه‌ی پیامک هنوز توسطِ مدیر فعال نشده است. لطفاً از <Link href={route('password.request')} className="link-gold">بازیابیِ ایمیلی</Link> استفاده کنید.
                        </div>
                    )}
                    {banner && <div style={{ background: '#e3f7ec', color: '#176c45', padding: 12, borderRadius: 12, marginBottom: 14, fontSize: 13 }}>{banner}</div>}

                    {step === 1 ? (
                        <form onSubmit={doSend}>
                            <div className="field">
                                <label>شماره موبایل</label>
                                <input className="input" value={sendForm.data.phone} onChange={(e) => sendForm.setData('phone', e.target.value)} placeholder="09xxxxxxxxx" dir="ltr" inputMode="tel" autoFocus />
                                {sendForm.errors.phone && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{sendForm.errors.phone}</div>}
                            </div>
                            <button type="submit" disabled={sendForm.processing || !smsEnabled} className="btn" style={{ width: '100%' }}>ارسالِ کدِ پیامکی 📤</button>
                            <div style={{ textAlign: 'center', marginTop: 14 }}>
                                <button type="button" onClick={() => setStep(2)} className="link-gold" style={{ background: 'none', border: 0, cursor: 'pointer', fontSize: 13, fontFamily: 'inherit' }}>کد را قبلاً گرفته‌ام ←</button>
                            </div>
                        </form>
                    ) : (
                        <form onSubmit={doReset}>
                            <div className="field">
                                <label>شماره موبایل</label>
                                <input className="input" value={resetForm.data.phone} onChange={(e) => resetForm.setData('phone', e.target.value)} placeholder="09xxxxxxxxx" dir="ltr" inputMode="tel" />
                                {resetForm.errors.phone && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{resetForm.errors.phone}</div>}
                            </div>
                            <div className="field">
                                <label>کدِ پیامک‌شده</label>
                                <input className="input" value={resetForm.data.code} onChange={(e) => resetForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))} placeholder="۶ رقم" dir="ltr" inputMode="numeric" style={{ letterSpacing: 6, fontWeight: 800 }} />
                                {resetForm.errors.code && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{resetForm.errors.code}</div>}
                            </div>
                            <div className="field">
                                <label>رمزِ جدید</label>
                                <input type="password" className="input" value={resetForm.data.password} onChange={(e) => resetForm.setData('password', e.target.value)} />
                                {resetForm.errors.password && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{resetForm.errors.password}</div>}
                            </div>
                            <div className="field">
                                <label>تکرارِ رمزِ جدید</label>
                                <input type="password" className="input" value={resetForm.data.password_confirmation} onChange={(e) => resetForm.setData('password_confirmation', e.target.value)} />
                            </div>
                            <button type="submit" disabled={resetForm.processing} className="btn" style={{ width: '100%' }}>تغییرِ رمز 🔓</button>
                            <div style={{ textAlign: 'center', marginTop: 12 }}>
                                <button type="button" onClick={() => setStep(1)} className="link-gold" style={{ background: 'none', border: 0, cursor: 'pointer', fontSize: 13, fontFamily: 'inherit' }}>← ارسالِ دوباره‌ی کد</button>
                            </div>
                        </form>
                    )}

                    <div className="divider">یا</div>
                    <div style={{ textAlign: 'center', fontSize: 14 }}>
                        <Link href={route('login')} className="link-gold">بازگشت به ورود</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
