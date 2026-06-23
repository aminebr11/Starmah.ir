import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

const ROLE_FA = { super_admin: 'ادمین کل', school_admin: 'مدیر مدرسه', teacher: 'معلم', student: 'دانش‌آموز', parent: 'والد' };

/** پروفایل من — مشاهده/ویرایش اطلاعات، آواتار و رمز. در دسترس همه‌ی نقش‌ها. */
export default function Edit() {
    const { profile, flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const fileRef = useRef();
    const [preview, setPreview] = useState(profile.avatar);
    const info = useForm({ name: profile.name || '', email: profile.email || '', avatar: null });
    const pass = useForm({ current_password: '', password: '', password_confirmation: '' });

    const onFile = (e) => {
        const f = e.target.files[0];
        if (f) { info.setData('avatar', f); setPreview(URL.createObjectURL(f)); }
    };
    const saveInfo = (e) => { e.preventDefault(); info.post(route('profile.update'), { forceFormData: true, preserveScroll: true }); };
    const savePass = (e) => { e.preventDefault(); pass.put(route('profile.password'), { preserveScroll: true, onSuccess: () => pass.reset() }); };

    return (
        <div dir="rtl" style={{ minHeight: '100vh', background: 'var(--cream)' }}>
            <Head title="پروفایل من" />
            <header className="nav"><div className="container nav-inner">
                <Link href="/dashboard" className="nav-logo"><img src="/brand/logo-emblem.png" alt="" /><span>ستاره<span style={{ color: 'var(--gold-2)' }}> ماه</span></span></Link>
                <Link href="/dashboard" className="btn btn-ghost btn-sm">← بازگشت به داشبورد</Link>
            </div></header>

            <div className="container" style={{ maxWidth: 720, padding: '30px 22px 60px' }}>
                <h1 style={{ color: 'var(--navy-800)', fontSize: 26 }}>پروفایل من 👤</h1>
                {banner && <div className="card" style={{ borderColor: 'var(--gold)', background: '#fff8e8', marginTop: 12 }}><b>{banner}</b></div>}

                <form onSubmit={saveInfo} className="card" style={{ marginTop: 16 }}>
                    <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>اطلاعات کاربری</h3>
                    <div style={{ display: 'flex', gap: 22, alignItems: 'center', flexWrap: 'wrap', marginBottom: 18 }}>
                        <div style={{ position: 'relative' }}>
                            <div style={{ width: 96, height: 96, borderRadius: '50%', overflow: 'hidden', background: 'linear-gradient(135deg,var(--gold),var(--gold-2))', display: 'grid', placeItems: 'center', fontSize: 40 }}>
                                {preview ? <img src={preview} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} /> : '🙂'}
                            </div>
                            <button type="button" onClick={() => fileRef.current.click()} style={{ position: 'absolute', bottom: 0, insetInlineStart: 0, background: 'var(--navy-700)', color: '#fff', border: '2px solid #fff', borderRadius: '50%', width: 32, height: 32, cursor: 'pointer' }}>📷</button>
                            <input ref={fileRef} type="file" accept="image/*" hidden onChange={onFile} />
                        </div>
                        <div>
                            <div style={{ fontWeight: 800, fontSize: 18 }}>{profile.name}</div>
                            <div style={{ color: 'var(--muted)', fontSize: 14, marginTop: 4 }}>
                                <span className="tag tag-info">{ROLE_FA[profile.role] ?? profile.role}</span>
                                {profile.school && <span style={{ marginInlineStart: 8 }}>🏫 {profile.school}</span>}
                            </div>
                            <div style={{ color: 'var(--muted)', fontSize: 13, marginTop: 6 }}>📱 {profile.phone}{profile.grade ? ` · پایه ${profile.grade}` : ''}</div>
                        </div>
                    </div>

                    <Field label="نام و نام خانوادگی" err={info.errors.name}>
                        <input className="input" value={info.data.name} onChange={(e) => info.setData('name', e.target.value)} />
                    </Field>
                    <Field label="ایمیل (اختیاری)" err={info.errors.email}>
                        <input className="input" value={info.data.email} onChange={(e) => info.setData('email', e.target.value)} />
                    </Field>
                    <div className="field"><label>شماره موبایل</label>
                        <input className="input" value={profile.phone || ''} disabled style={{ opacity: .6 }} />
                        <div style={{ color: 'var(--muted-2)', fontSize: 11, marginTop: 4 }}>تغییر موبایل از طریق مدرسه</div>
                    </div>
                    <button type="submit" disabled={info.processing} className="btn">💾 ذخیره اطلاعات</button>
                </form>

                <form onSubmit={savePass} className="card" style={{ marginTop: 18 }}>
                    <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>تغییر رمز عبور 🔒</h3>
                    <Field label="رمز فعلی" err={pass.errors.current_password}>
                        <input type="password" className="input" value={pass.data.current_password} onChange={(e) => pass.setData('current_password', e.target.value)} />
                    </Field>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                        <Field label="رمز جدید" err={pass.errors.password}>
                            <input type="password" className="input" value={pass.data.password} onChange={(e) => pass.setData('password', e.target.value)} />
                        </Field>
                        <Field label="تکرار رمز جدید">
                            <input type="password" className="input" value={pass.data.password_confirmation} onChange={(e) => pass.setData('password_confirmation', e.target.value)} />
                        </Field>
                    </div>
                    <button type="submit" disabled={pass.processing} className="btn btn-navy">تغییر رمز</button>
                </form>
            </div>
        </div>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
