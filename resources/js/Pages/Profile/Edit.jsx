import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import PasswordInput from '@/Components/PasswordInput';
import JalaliDatePicker from '@/Components/JalaliDatePicker';

const fa = (n) => String(n ?? '').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
const ROLE_FA = { super_admin: 'ادمین کل', school_admin: 'مدیر مدرسه', teacher: 'معلم', student: 'دانش‌آموز', parent: 'والد' };

/** پروفایل من — مشاهده/ویرایش کامل اطلاعات، آواتار و رمز. در دسترس همه‌ی نقش‌ها. */
export default function Edit() {
    const { profile, stats = [], flash } = usePage().props;
    const [banner, setBanner] = useState(null);
    useEffect(() => { if (flash?.flash) setBanner(typeof flash.flash === 'string' ? flash.flash : flash.flash.message); }, [flash]);

    const fileRef = useRef();
    const [preview, setPreview] = useState(profile.avatar);
    const role = profile.role;
    const isTeacher = role === 'teacher';
    const isStudent = role === 'student';

    const info = useForm({
        name: profile.name || '', email: profile.email || '', national_id: profile.national_id || '',
        birth_date: profile.birth_date || '', bio: profile.bio || '', address: profile.address || '',
        guardian_name: profile.guardian_name || '', guardian_phone: profile.guardian_phone || '',
        specialty: profile.specialty || '', experience_years: profile.experience_years || '', education: profile.education || '',
        avatar: null,
    });
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
                <div style={{ display: 'flex', gap: 8 }}>
                    <Link href="/dashboard" className="btn btn-ghost btn-sm">← داشبورد</Link>
                    <button onClick={() => router.post(route('logout'))} className="btn btn-ghost btn-sm">خروج</button>
                </div>
            </div></header>

            <div className="container" style={{ maxWidth: 860, padding: '26px 20px 60px' }}>
                {banner && <div className="card" style={{ borderColor: 'var(--gold)', background: '#fff8e8', marginBottom: 16 }}><b>{banner}</b></div>}

                {/* ===== هدر کیهانی پروفایل ===== */}
                <div className="profile-hero">
                    <div className="profile-hero-stars" />
                    <div className="profile-avatar">
                        <div className="profile-avatar-img">
                            {preview ? <img src={preview} alt="" /> : <span>🙂</span>}
                        </div>
                        <button type="button" onClick={() => fileRef.current.click()} className="profile-avatar-btn" title="تغییر عکس">📷</button>
                        <input ref={fileRef} type="file" accept="image/*" hidden onChange={onFile} />
                    </div>
                    <div className="profile-hero-body">
                        <h1>{profile.name}</h1>
                        <div className="profile-hero-meta">
                            <span className="tag" style={{ background: 'rgba(245,181,63,.2)', color: '#ffd87a', border: '1px solid rgba(245,181,63,.4)' }}>{ROLE_FA[role] ?? role}</span>
                            {profile.school && <span>🏫 {profile.school}</span>}
                            <span>📱 {profile.phone}</span>
                            {profile.grade && <span>🎒 پایه {profile.grade}</span>}
                        </div>
                        {stats.length > 0 && (
                            <div className="profile-hero-stats">
                                {stats.map((s) => (
                                    <div key={s.label} className="profile-stat"><b>{s.ic} {fa(s.value)}</b><span>{s.label}</span></div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* ===== فرم اطلاعات (شخصی + نقش‌محور) ===== */}
                <form onSubmit={saveInfo}>
                    <div className="card" style={{ marginTop: 18 }}>
                        <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>📋 اطلاعات شخصی</h3>
                        <div className="profile-grid">
                            <Field label="نام و نام خانوادگی" err={info.errors.name}>
                                <input className="input" value={info.data.name} onChange={(e) => info.setData('name', e.target.value)} />
                            </Field>
                            <Field label="کد ملی" err={info.errors.national_id}>
                                <input className="input" value={info.data.national_id} onChange={(e) => info.setData('national_id', e.target.value)} placeholder="۱۰ رقم" inputMode="numeric" />
                            </Field>
                            <Field label={`تاریخ تولد${profile.jbirth ? ` (${profile.jbirth})` : ''}`} err={info.errors.birth_date}>
                                <JalaliDatePicker value={info.data.birth_date || ''} onChange={(v) => info.setData('birth_date', v)} placeholder="انتخاب تاریخ تولد" />
                            </Field>
                            <Field label="ایمیل (اختیاری)" err={info.errors.email}>
                                <input className="input" value={info.data.email} onChange={(e) => info.setData('email', e.target.value)} dir="ltr" />
                            </Field>
                            <div className="field profile-col-full"><label>شماره موبایل</label>
                                <input className="input" value={profile.phone || ''} disabled style={{ opacity: .6 }} />
                                <div style={{ color: 'var(--muted-2)', fontSize: 11, marginTop: 4 }}>تغییر موبایل از طریق مدرسه انجام می‌شود.</div>
                            </div>
                            <div className="field profile-col-full">
                                <label>{isStudent ? 'درباره‌ی من / علاقه‌مندی‌ها' : 'بیوگرافی / درباره‌ی من'}</label>
                                <textarea className="input" rows="3" value={info.data.bio} onChange={(e) => info.setData('bio', e.target.value)}
                                    placeholder={isStudent ? 'به چه چیزهایی علاقه داری؟' : 'چند خط درباره‌ی خودت بنویس…'} />
                                {info.errors.bio && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{info.errors.bio}</div>}
                            </div>
                        </div>
                    </div>

                    {/* اطلاعات نقش‌محور */}
                    {(isTeacher || isStudent || role === 'school_admin') && (
                        <div className="card" style={{ marginTop: 16 }}>
                            <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>
                                {isTeacher ? '👩‍🏫 اطلاعات آموزشی' : isStudent ? '🎓 اطلاعات دانش‌آموزی' : '🏫 اطلاعات تماس'}
                            </h3>
                            <div className="profile-grid">
                                {isTeacher && <>
                                    <Field label="تخصص / رشته" err={info.errors.specialty}>
                                        <input className="input" value={info.data.specialty} onChange={(e) => info.setData('specialty', e.target.value)} placeholder="مثلاً آموزش ابتدایی" />
                                    </Field>
                                    <Field label="سابقه‌ی تدریس (سال)" err={info.errors.experience_years}>
                                        <input className="input" value={info.data.experience_years} onChange={(e) => info.setData('experience_years', e.target.value)} inputMode="numeric" placeholder="مثلاً ۱۰" />
                                    </Field>
                                    <Field label="مدرک تحصیلی" err={info.errors.education}>
                                        <input className="input" value={info.data.education} onChange={(e) => info.setData('education', e.target.value)} placeholder="مثلاً کارشناسی علوم تربیتی" />
                                    </Field>
                                </>}
                                {isStudent && <>
                                    <Field label="نام ولی / سرپرست" err={info.errors.guardian_name}>
                                        <input className="input" value={info.data.guardian_name} onChange={(e) => info.setData('guardian_name', e.target.value)} />
                                    </Field>
                                    <Field label="تلفن ولی" err={info.errors.guardian_phone}>
                                        <input className="input" value={info.data.guardian_phone} onChange={(e) => info.setData('guardian_phone', e.target.value)} dir="ltr" inputMode="tel" />
                                    </Field>
                                </>}
                                <div className="field profile-col-full"><label>آدرس</label>
                                    <textarea className="input" rows="2" value={info.data.address} onChange={(e) => info.setData('address', e.target.value)} placeholder="نشانی…" />
                                </div>
                            </div>
                        </div>
                    )}

                    <button type="submit" disabled={info.processing} className="btn" style={{ marginTop: 16 }}>
                        {info.processing ? 'در حال ذخیره…' : '💾 ذخیره‌ی اطلاعات'}
                    </button>
                </form>

                {/* ===== تغییر رمز ===== */}
                <form onSubmit={savePass} className="card" style={{ marginTop: 22 }}>
                    <h3 style={{ marginTop: 0, color: 'var(--navy-700)' }}>🔒 تغییر رمز عبور</h3>
                    <Field label="رمز فعلی" err={pass.errors.current_password}>
                        <PasswordInput value={pass.data.current_password} autoComplete="current-password" onChange={(e) => pass.setData('current_password', e.target.value)} />
                    </Field>
                    <div className="profile-grid">
                        <Field label="رمز جدید" err={pass.errors.password}>
                            <PasswordInput value={pass.data.password} autoComplete="new-password" placeholder="حداقل ۶ کاراکتر" onChange={(e) => pass.setData('password', e.target.value)} />
                        </Field>
                        <Field label="تکرار رمز جدید">
                            <PasswordInput value={pass.data.password_confirmation} autoComplete="new-password" onChange={(e) => pass.setData('password_confirmation', e.target.value)} />
                        </Field>
                    </div>
                    <button type="submit" disabled={pass.processing} className="btn btn-navy" style={{ marginTop: 6 }}>تغییر رمز</button>
                </form>
            </div>
        </div>
    );
}

function Field({ label, err, children }) {
    return <div className="field"><label>{label}</label>{children}{err && <div style={{ color: '#e8505b', fontSize: 12, marginTop: 4 }}>{err}</div>}</div>;
}
